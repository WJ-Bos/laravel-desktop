<script setup lang="ts">
import type {
    CellContextMenuEvent,
    ColDef,
    Column,
    ColumnResizedEvent,
    GetRowIdParams,
    RowDoubleClickedEvent,
} from 'ag-grid-community';
import { AgGridVue } from 'ag-grid-vue3';
import type { DockviewApi, DockviewPanelApi } from 'dockview-vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ContextMenu from 'primevue/contextmenu';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import type { MenuItem } from 'primevue/menuitem';
import MultiSelect from 'primevue/multiselect';
import Popover from 'primevue/popover';
import { useToast } from 'primevue/usetoast';
import { computed, ref, shallowRef } from 'vue';
import { useGrid } from '@/composables/useGrid';
import { useLookups, useNetwork } from '@/composables/useLookups';
import {
    confirmAction,
    emitRecordChange,
    onRecordChange,
    openHeadend,
    openNew,
    openRecord,
    openScopedGrid,
    usePanelActions,
} from '@/composables/useWorkspace';
import type { GridPanelParams } from '@/composables/useWorkspace';
import { gridTheme } from '@/lib/agGrid';
import { ApiError, api, saveText } from '@/lib/api';
import { fmt } from '@/lib/format';
import { entities } from '@/lib/modules';
import { gridConfigs } from '@/modules/grids/gridConfigs';
import type { AnyRecord, EditableEntity, MeterEvent } from '@/types/erp';

const props = defineProps<{
    params: {
        params: GridPanelParams;
        api: DockviewPanelApi;
        containerApi: DockviewApi;
    };
}>();

const entity = props.params.params.module;
const panelId = props.params.api.id;
const def = entities[entity];
const editable = def.editable;
const config = gridConfigs[entity];
const toast = useToast();
const { lookups } = useLookups();
const { refresh: refreshNetwork } = useNetwork();

const grid = useGrid<AnyRecord>({
    entity,
    panelId,
    baseFilter: props.params.params.baseFilter,
    toolbarField: config.toolbarFilter?.field,
});

const { search, toolbarValues, selected, stats, error } = grid;

const columnDefs: ColDef[] = config.columns;
const defaultColDef: ColDef<AnyRecord> = {
    sortable: true,
    resizable: true,
    minWidth: 60,
};
const floatingFilters = ref(false);
const contextMenu = ref<InstanceType<typeof ContextMenu> | null>(null);
const columnPopover = ref<InstanceType<typeof Popover> | null>(null);
const contextRow = shallowRef<AnyRecord | null>(null);
const allColumns = shallowRef<Column[]>([]);
const visibleColumnIds = ref<string[]>([]);

const toolbarOptions = computed(() =>
    config.toolbarFilter && lookups.value
        ? config.toolbarFilter.options(lookups.value)
        : [],
);

const scopeLabel = props.params.params.scopeLabel;

const rangeLabel = computed(() => {
    if (stats.total === 0) {
        return stats.loading ? 'Loading…' : 'No records';
    }

    return `${fmt.int(Math.min(stats.loaded, stats.total))} of ${fmt.int(stats.total)} loaded`;
});

/* ---- helpers -------------------------------------------------------- */

const asRecord = (row: AnyRecord) => row as unknown as Record<string, unknown>;

/** The meter a reading or event belongs to (or the meter itself). */
function meterOf(row: AnyRecord | null): { id: number; serial: string } | null {
    if (!row) {
        return null;
    }

    if (entity === 'meters') {
        return { id: row.id, serial: String(asRecord(row).serial) };
    }

    const r = asRecord(row);

    return typeof r.meter_id === 'number'
        ? { id: r.meter_id, serial: String(r.meter_serial ?? r.meter_id) }
        : null;
}

/* ---- record actions ------------------------------------------------ */

function newRecord(): void {
    if (!editable) {
        return;
    }

    const preset: Record<string, unknown> = {};

    for (const [field, model] of Object.entries(
        props.params.params.baseFilter ?? {},
    )) {
        const value = (model as { filter?: unknown }).filter;

        if (value !== undefined) {
            preset[field] = value;
        }
    }

    openNew(
        entity as EditableEntity,
        Object.keys(preset).length ? preset : undefined,
    );
}

function open(row: AnyRecord | null, focus = false): void {
    if (!row) {
        return;
    }

    if (entity === 'headends' && !focus) {
        openHeadend(row.id, def.title(row));

        return;
    }

    if (editable) {
        openRecord(entity, row.id, def.title(row), { focus });

        return;
    }

    const meter = meterOf(row);

    if (meter) {
        openRecord('meters', meter.id, `Meter ${meter.serial}`);
    }
}

function openReadings(row: AnyRecord | null): void {
    const meter = meterOf(row);

    if (meter) {
        openScopedGrid(
            'readings',
            `meter-${meter.id}`,
            `Readings – ${meter.serial}`,
            {
                meter_id: {
                    filterType: 'number',
                    type: 'equals',
                    filter: meter.id,
                },
            },
        );
    }
}

async function acknowledge(row: AnyRecord | null, value = true): Promise<void> {
    if (!row || entity !== 'events') {
        return;
    }

    try {
        await api.patch(`/api/events/${row.id}`, { acknowledged: value });
        grid.refresh();
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Not updated',
            detail: (e as Error).message,
            life: 5000,
        });
    }
}

async function duplicate(row: AnyRecord | null): Promise<void> {
    if (!row || !editable) {
        return;
    }

    try {
        const { data } = await api.post<{ data: AnyRecord }>(
            `${def.endpoint}/${row.id}/duplicate`,
        );
        toast.add({
            severity: 'success',
            summary: `${def.singular} duplicated`,
            detail: `Created ${def.ref(data)}.`,
            life: 3000,
        });
        grid.refresh();
        void refreshNetwork();
        emitRecordChange('saved', { entity, id: data.id });
        openRecord(entity, data.id, def.title(data), { focus: true });
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Duplicate failed',
            detail: (e as Error).message,
            life: 5000,
        });
    }
}

async function remove(row: AnyRecord | null): Promise<void> {
    if (!row || !editable) {
        return;
    }

    const ok = await confirmAction({
        header: `Delete ${def.singular.toLowerCase()}?`,
        message: `Delete ${def.ref(row)}? This can't be undone.`,
        acceptLabel: 'Delete',
        danger: true,
    });

    if (!ok) {
        return;
    }

    try {
        await api.delete(`${def.endpoint}/${row.id}`);
        toast.add({
            severity: 'success',
            summary: `${def.singular} deleted`,
            detail: def.ref(row),
            life: 3000,
        });
        grid.gridApi.value?.deselectAll();
        grid.refresh();
        void refreshNetwork();
        emitRecordChange('deleted', { entity, id: row.id });
    } catch (e) {
        const message =
            e instanceof ApiError
                ? e.message
                : 'The server could not delete this record.';
        toast.add({
            severity: 'warn',
            summary: 'Not deleted',
            detail: message,
            life: 6000,
        });
    }
}

function exportRow(row: AnyRecord | null): void {
    const gridApi = grid.gridApi.value;

    if (!row || !gridApi) {
        return;
    }

    const node = gridApi.getRowNode(String(row.id));
    const cols = gridApi.getAllDisplayedColumns();
    const quote = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const header = cols
        .map((c) => quote(c.getColDef().headerName ?? c.getColId()))
        .join(',');
    const values = cols
        .map((c) => {
            const raw = asRecord(row)[c.getColId()];
            const formatted = node
                ? gridApi.getCellValue({
                      rowNode: node,
                      colKey: c,
                      useFormatter: true,
                  })
                : raw;

            return quote(typeof raw === 'number' ? raw : (formatted ?? raw));
        })
        .join(',');

    saveText(`${entity}-${row.id}.csv`, `${header}\r\n${values}\r\n`);
}

/* ---- grid events --------------------------------------------------- */

function onRowDoubleClicked(event: RowDoubleClickedEvent<AnyRecord>): void {
    open(event.data ?? null);
}

const contextItems = computed<MenuItem[]>(() => {
    const row = contextRow.value;
    const items: MenuItem[] = [];

    if (entity === 'headends') {
        items.push({
            label: 'Open headend window',
            icon: 'pi pi-window-maximize',
            command: () => open(row),
        });
    } else {
        items.push({
            label: editable ? 'Open' : 'Open meter',
            icon: 'pi pi-external-link',
            command: () => open(row),
        });
    }

    if (editable) {
        items.push(
            {
                label: 'Edit',
                icon: 'pi pi-pencil',
                command: () => open(row, true),
            },
            {
                label: 'Duplicate',
                icon: 'pi pi-copy',
                command: () => duplicate(row),
            },
        );
    }

    if (entity !== 'headends' && entity !== 'gateways') {
        items.push({
            label: 'Readings for this meter',
            icon: 'pi pi-chart-line',
            command: () => openReadings(row),
        });
    }

    if (entity === 'events' && row) {
        const acked = (row as MeterEvent).acknowledged;
        items.push({
            label: acked ? 'Mark unacknowledged' : 'Acknowledge',
            icon: acked ? 'pi pi-undo' : 'pi pi-check',
            command: () => acknowledge(row, !acked),
        });
    }

    items.push(
        { separator: true },
        {
            label: 'Export row',
            icon: 'pi pi-file-export',
            command: () => exportRow(row),
        },
    );

    if (editable) {
        items.push(
            { separator: true },
            {
                label: 'Delete',
                icon: 'pi pi-trash',
                class: 'erp-menu-danger',
                command: () => remove(row),
            },
        );
    }

    return items;
});

function onCellContextMenu(event: CellContextMenuEvent<AnyRecord>): void {
    if (!event.data || !event.event) {
        return;
    }

    contextRow.value = event.data;
    event.node.setSelected(true, true);
    contextMenu.value?.show(event.event as MouseEvent);
}

function onColumnResized(event: ColumnResizedEvent<AnyRecord>): void {
    if (event.finished) {
        grid.saveColumns();
    }
}

function getRowId(params: GetRowIdParams<AnyRecord>): string {
    return String(params.data.id);
}

/* ---- column chooser ---------------------------------------------- */

function openColumns(event: MouseEvent): void {
    const gridApi = grid.gridApi.value;

    if (!gridApi) {
        return;
    }

    allColumns.value = gridApi.getColumns() ?? [];
    visibleColumnIds.value = allColumns.value
        .filter((c) => c.isVisible())
        .map((c) => c.getColId());
    columnPopover.value?.toggle(event);
}

function toggleColumn(colId: string, visible: boolean): void {
    grid.gridApi.value?.setColumnsVisible([colId], visible);
}

async function resetColumns(): Promise<void> {
    await grid.resetColumns();
    visibleColumnIds.value = (grid.gridApi.value?.getColumns() ?? [])
        .filter((c) => c.isVisible())
        .map((c) => c.getColId());
    toast.add({
        severity: 'info',
        summary: 'Columns reset',
        detail: `${def.plural} columns are back to the default layout.`,
        life: 2500,
    });
}

function toggleFloatingFilters(): void {
    floatingFilters.value = !floatingFilters.value;
    grid.gridApi.value?.setGridOption('defaultColDef', {
        ...defaultColDef,
        floatingFilter: floatingFilters.value,
    });
}

function clearFilters(): void {
    search.value = '';
    toolbarValues.value = [];
    grid.gridApi.value?.setFilterModel(null);
}

/* ---- wiring -------------------------------------------------------- */

usePanelActions(panelId, {
    onNew: editable ? newRecord : undefined,
    onRefresh: () => grid.refresh(),
    onExport: () => grid.exportCsv(),
});

onRecordChange('saved', () => grid.refresh());
onRecordChange('deleted', (e) => {
    if (e.entity === entity) {
        grid.refresh();
    }
});
</script>

<template>
    <div class="erp-module">
        <div
            class="erp-toolbar"
            role="toolbar"
            :aria-label="`${def.plural} toolbar`"
        >
            <template v-if="editable">
                <Button
                    v-tooltip.bottom="'New record (Ctrl+N)'"
                    label="New"
                    icon="pi pi-plus"
                    size="small"
                    @click="newRecord"
                />
                <Button
                    v-tooltip.bottom="'Edit the selected record'"
                    label="Edit"
                    icon="pi pi-pencil"
                    size="small"
                    severity="secondary"
                    text
                    :disabled="!selected"
                    @click="open(selected, true)"
                />
                <Button
                    v-tooltip.bottom="'Duplicate the selected record'"
                    icon="pi pi-copy"
                    size="small"
                    severity="secondary"
                    text
                    aria-label="Duplicate"
                    :disabled="!selected"
                    @click="duplicate(selected)"
                />
                <Button
                    v-tooltip.bottom="'Delete the selected record'"
                    label="Delete"
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    text
                    :disabled="!selected"
                    @click="remove(selected)"
                />
            </template>
            <template v-else>
                <Button
                    v-tooltip.bottom="'Open the meter of the selected row'"
                    label="Open meter"
                    icon="pi pi-bolt"
                    size="small"
                    severity="secondary"
                    text
                    :disabled="!selected"
                    @click="open(selected)"
                />
                <Button
                    v-if="entity === 'events'"
                    label="Acknowledge"
                    icon="pi pi-check"
                    size="small"
                    severity="secondary"
                    text
                    :disabled="
                        !selected || (selected as MeterEvent).acknowledged
                    "
                    @click="acknowledge(selected)"
                />
            </template>
            <Button
                v-if="entity === 'headends'"
                v-tooltip.bottom="
                    'Open the selected headend in a floating window'
                "
                label="Open window"
                icon="pi pi-window-maximize"
                size="small"
                severity="secondary"
                text
                :disabled="!selected"
                @click="open(selected)"
            />
            <Button
                v-if="entity === 'meters'"
                v-tooltip.bottom="'Raw readings for the selected meter'"
                label="Readings"
                icon="pi pi-chart-line"
                size="small"
                severity="secondary"
                text
                :disabled="!selected"
                @click="openReadings(selected)"
            />
            <span class="erp-toolbar-sep" />
            <Button
                v-tooltip.bottom="'Refresh from the server'"
                label="Refresh"
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                text
                :loading="stats.loading"
                @click="grid.refresh()"
            />
            <Button
                v-tooltip.bottom="
                    'Download every row matching the current filters as CSV'
                "
                label="Export"
                icon="pi pi-download"
                size="small"
                severity="secondary"
                text
                @click="grid.exportCsv()"
            />
            <span class="erp-toolbar-sep" />
            <Button
                v-tooltip.bottom="'Show or hide columns'"
                label="Columns"
                icon="pi pi-table"
                size="small"
                severity="secondary"
                text
                aria-haspopup="true"
                @click="openColumns"
            />
            <Button
                v-tooltip.bottom="'Toggle the filter row under the headers'"
                icon="pi pi-filter"
                size="small"
                :severity="floatingFilters ? 'primary' : 'secondary'"
                :text="!floatingFilters"
                aria-label="Filter row"
                @click="toggleFloatingFilters"
            />
            <span v-if="scopeLabel" class="erp-scope-chip" :title="scopeLabel"
                ><i class="pi pi-link" /> {{ scopeLabel }}</span
            >
            <span class="erp-toolbar-fill" />
            <MultiSelect
                v-if="config.toolbarFilter"
                v-model="toolbarValues"
                :options="toolbarOptions"
                option-label="label"
                option-value="value"
                :placeholder="config.toolbarFilter.label"
                :max-selected-labels="2"
                size="small"
                class="erp-toolbar-select"
                show-clear
            />
            <IconField class="erp-search">
                <InputIcon class="pi pi-search" />
                <InputText
                    v-model="search"
                    size="small"
                    :placeholder="config.searchPlaceholder"
                    :aria-label="`Search ${def.plural.toLowerCase()}`"
                />
            </IconField>
            <Button
                v-if="grid.hasActiveFilters.value"
                v-tooltip.bottom="'Clear search and filters'"
                icon="pi pi-filter-slash"
                size="small"
                severity="secondary"
                text
                aria-label="Clear filters"
                @click="clearFilters"
            />
        </div>

        <div v-if="error" class="erp-inline-error" role="alert">
            <i class="pi pi-exclamation-triangle" />
            Couldn't load {{ def.plural.toLowerCase() }}: {{ error }}
            <Button label="Retry" size="small" text @click="grid.reload()" />
        </div>

        <div class="erp-grid-host">
            <AgGridVue
                class="erp-grid"
                :theme="gridTheme"
                :column-defs="columnDefs"
                :default-col-def="defaultColDef"
                row-model-type="infinite"
                :cache-block-size="100"
                :max-blocks-in-cache="20"
                :cache-overflow-size="2"
                :infinite-initial-row-count="50"
                :get-row-id="getRowId"
                :row-selection="{
                    mode: 'singleRow',
                    checkboxes: false,
                    enableClickSelection: true,
                }"
                :prevent-default-on-context-menu="true"
                :animate-rows="false"
                @grid-ready="grid.onGridReady"
                @selection-changed="grid.onSelectionChanged"
                @row-double-clicked="onRowDoubleClicked"
                @cell-context-menu="onCellContextMenu"
                @column-moved="grid.saveColumns"
                @column-resized="onColumnResized"
                @column-visible="grid.saveColumns"
                @column-pinned="grid.saveColumns"
                @sort-changed="grid.saveColumns"
            />
        </div>

        <div class="erp-module-foot">
            <span>{{ rangeLabel }}</span>
            <span v-if="stats.selected">· {{ stats.selected }} selected</span>
            <span class="erp-toolbar-fill" />
            <span class="erp-hint">
                {{
                    entity === 'headends'
                        ? 'Double-click to open the headend window'
                        : editable
                          ? 'Double-click a row to open it'
                          : 'Double-click a row to open its meter'
                }}
                · right-click for more
            </span>
        </div>

        <ContextMenu ref="contextMenu" :model="contextItems" />

        <Popover ref="columnPopover">
            <div class="erp-column-chooser">
                <div class="erp-column-chooser-head">
                    <strong>Columns</strong>
                    <Button
                        label="Reset"
                        size="small"
                        text
                        @click="resetColumns"
                    />
                </div>
                <label
                    v-for="column in allColumns"
                    :key="column.getColId()"
                    class="erp-column-option"
                >
                    <Checkbox
                        v-model="visibleColumnIds"
                        :value="column.getColId()"
                        :input-id="`${panelId}-col-${column.getColId()}`"
                        @change="
                            toggleColumn(
                                column.getColId(),
                                visibleColumnIds.includes(column.getColId()),
                            )
                        "
                    />
                    <span>{{ column.getColDef().headerName }}</span>
                </label>
            </div>
        </Popover>
    </div>
</template>
