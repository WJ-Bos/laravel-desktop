<script setup lang="ts">
import type { ColDef, RowDoubleClickedEvent } from 'ag-grid-community';
import { AgGridVue } from 'ag-grid-vue3';
import type { DockviewApi, DockviewPanelApi } from 'dockview-vue';
import { GridItem, GridLayout } from 'grid-layout-plus';
import type { Layout } from 'grid-layout-plus';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import EChart from '@/components/erp/EChart.vue';
import { useLayoutPersistence } from '@/composables/useLayoutPersistence';
import { useTheme } from '@/composables/useTheme';
import {
    onRecordChange,
    openHeadend,
    openModule,
    openRecord,
    usePanelActions,
} from '@/composables/useWorkspace';
import { col, dateTime, gridTheme } from '@/lib/agGrid';
import { api, buildQuery } from '@/lib/api';
import { fmt, isoDate } from '@/lib/format';
import {
    heatmapOption,
    networkLoadOption,
    topMetersOption,
    typeDonutOption,
} from '@/modules/dashboard/chartOptions';
import KpiCard from '@/modules/dashboard/KpiCard.vue';
import type { DashboardData, MeterEvent } from '@/types/erp';

const props = defineProps<{
    params: {
        params: Record<string, unknown>;
        api: DockviewPanelApi;
        containerApi: DockviewApi;
    };
}>();

/* ---- widget layout ------------------------------------------------ */

type WidgetId =
    | 'kpi-energy'
    | 'kpi-demand'
    | 'kpi-complete'
    | 'kpi-alarms'
    | 'load'
    | 'types'
    | 'network'
    | 'meters'
    | 'heatmap'
    | 'events';

const titles: Record<WidgetId, string> = {
    'kpi-energy': 'Energy imported',
    'kpi-demand': 'Network peak demand',
    'kpi-complete': 'Data completeness',
    'kpi-alarms': 'Alarms',
    load: 'Network load',
    types: 'Energy by meter type',
    network: 'Headends',
    meters: 'Top 10 meters by energy',
    heatmap: 'Average load by weekday and hour',
    events: 'Recent events',
};

const defaultWidgets: Layout = [
    { i: 'kpi-energy', x: 0, y: 0, w: 3, h: 4, minW: 2, minH: 3 },
    { i: 'kpi-demand', x: 3, y: 0, w: 3, h: 4, minW: 2, minH: 3 },
    { i: 'kpi-complete', x: 6, y: 0, w: 3, h: 4, minW: 2, minH: 3 },
    { i: 'kpi-alarms', x: 9, y: 0, w: 3, h: 4, minW: 2, minH: 3 },
    { i: 'load', x: 0, y: 4, w: 8, h: 9, minW: 4, minH: 6 },
    { i: 'types', x: 8, y: 4, w: 4, h: 9, minW: 3, minH: 6 },
    { i: 'network', x: 0, y: 13, w: 6, h: 10, minW: 4, minH: 6 },
    { i: 'meters', x: 6, y: 13, w: 6, h: 10, minW: 3, minH: 6 },
    { i: 'heatmap', x: 0, y: 23, w: 7, h: 10, minW: 4, minH: 6 },
    { i: 'events', x: 7, y: 23, w: 5, h: 10, minW: 3, minH: 5 },
];

interface DashboardLayout {
    widgets: Layout;
    preset?: string;
    from?: string;
    to?: string;
}

const persisted = useLayoutPersistence<DashboardLayout>('dashboard:v2');
const saved = persisted.initial();

function mergeWithDefaults(stored: Layout | undefined): Layout {
    const byId = new Map((stored ?? []).map((w) => [String(w.i), w]));

    return defaultWidgets.map((d) => {
        const s = byId.get(String(d.i));

        return s ? { ...d, x: s.x, y: s.y, w: s.w, h: s.h } : { ...d };
    });
}

const widgets = ref<Layout>(mergeWithDefaults(saved?.widgets));

/* ---- date range --------------------------------------------------- */

type Preset = 'today' | '2d' | '7d' | '14d' | '30d' | '60d' | 'custom';

const presets: { label: string; value: Preset }[] = [
    { label: 'Today', value: 'today' },
    { label: 'Last 2 days', value: '2d' },
    { label: 'Last 7 days', value: '7d' },
    { label: 'Last 14 days', value: '14d' },
    { label: 'Last 30 days', value: '30d' },
    { label: 'Last 60 days', value: '60d' },
    { label: 'Custom range', value: 'custom' },
];

const presetDays: Record<Exclude<Preset, 'custom'>, number> = {
    today: 1,
    '2d': 2,
    '7d': 7,
    '14d': 14,
    '30d': 30,
    '60d': 60,
};

function rangeFor(preset: Exclude<Preset, 'custom'>): [Date, Date] {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const from = new Date(today);
    from.setDate(from.getDate() - (presetDays[preset] - 1));

    return [from, today];
}

const preset = ref<Preset>((saved?.preset as Preset | undefined) ?? '14d');
const customRange = ref<(Date | null)[] | null>(
    saved?.preset === 'custom' && saved.from && saved.to
        ? [new Date(`${saved.from}T00:00:00`), new Date(`${saved.to}T00:00:00`)]
        : rangeFor('14d'),
);

const range = computed<[string, string] | null>(() => {
    if (preset.value !== 'custom') {
        const [from, to] = rangeFor(preset.value);

        return [isoDate(from), isoDate(to)];
    }

    const [from, to] = customRange.value ?? [];

    return from && to ? [isoDate(from), isoDate(to)] : null;
});

/* ---- data ----------------------------------------------------------- */

const { chart } = useTheme();
const data = shallowRef<DashboardData | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
let controller: AbortController | null = null;

async function load(): Promise<void> {
    if (!range.value) {
        return;
    }

    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;

    try {
        data.value = await api.get<DashboardData>(
            `/api/dashboard${buildQuery({ from: range.value[0], to: range.value[1] })}`,
            { signal: controller.signal },
        );
    } catch (e) {
        if ((e as Error).name !== 'AbortError') {
            error.value = (e as Error).message;
        }
    } finally {
        loading.value = false;
    }
}

watch(range, (value, previous) => {
    if (value && value.join() !== previous?.join()) {
        void load();
    }
});

watch([preset, range], () => {
    persisted.save({
        widgets: widgets.value,
        preset: preset.value,
        from: range.value?.[0],
        to: range.value?.[1],
    });
});

function onLayoutUpdated(layout: Layout): void {
    persisted.save({
        widgets: layout.map(({ i, x, y, w, h }) => ({ i, x, y, w, h })),
        preset: preset.value,
        from: range.value?.[0],
        to: range.value?.[1],
    });
}

async function resetWidgets(): Promise<void> {
    widgets.value = mergeWithDefaults(undefined);
    await persisted.reset();
}

/* ---- derived widget content -------------------------------------- */

const comparison = computed(() => {
    const days = data.value?.range.days ?? 0;

    return days ? `vs previous ${days === 1 ? 'day' : `${days} days`}` : '';
});

const kpis = computed(() => {
    const k = data.value?.kpis;

    if (!k) {
        return null;
    }

    return {
        energy: {
            value: fmt.energy(k.energy.value),
            delta: k.energy.delta,
            spark: k.energy.spark,
            footnote:
                k.energy.export > 0
                    ? `${fmt.energy(k.energy.export)} exported by PV meters`
                    : 'No export recorded',
        },
        demand: {
            value: fmt.power(k.peakDemand.value, 'kVA'),
            delta: k.peakDemand.delta,
            spark: k.peakDemand.spark,
            footnote: k.peakDemand.at
                ? `Peak half-hour ending ${fmt.dateTime(k.peakDemand.at)}`
                : '',
        },
        complete: {
            value:
                k.completeness.value === null
                    ? '—'
                    : `${k.completeness.value.toFixed(2)}%`,
            delta: k.completeness.delta,
            spark: k.completeness.spark,
            footnote: `${fmt.int(k.completeness.missing)} intervals missing · ${fmt.int(k.completeness.estimated)} estimated`,
        },
        alarms: {
            value: fmt.int(k.alarms.value),
            delta: k.alarms.delta,
            spark: k.alarms.spark,
            footnote: `${fmt.int(k.alarms.open)} unacknowledged · ${fmt.int(k.alarms.critical)} critical`,
        },
    };
});

const loadOption = computed(() =>
    data.value ? networkLoadOption(data.value, chart.value) : {},
);
const typesOption = computed(() =>
    data.value ? typeDonutOption(data.value, chart.value) : {},
);
const metersOption = computed(() =>
    data.value ? topMetersOption(data.value, chart.value) : {},
);
const heatOption = computed(() =>
    data.value ? heatmapOption(data.value, chart.value) : {},
);

function onMeterBarClick(params: { dataIndex: number }): void {
    const meters = [...(data.value?.topMeters ?? [])].reverse();
    const meter = meters[params.dataIndex];

    if (meter) {
        openRecord('meters', meter.id, `Meter ${meter.serial}`);
    }
}

const eventColumns: ColDef<MeterEvent>[] = [
    col.date('occurred_at', 'Occurred', {
        width: 125,
        valueFormatter: dateTime,
        filter: false,
    }),
    col.status('severity', 'Severity', { width: 90 }),
    col.text('message', 'Message', { flex: 1, minWidth: 160, filter: false }),
    col.text('meter_serial', 'Meter', {
        width: 105,
        cellClass: 'erp-code',
        filter: false,
    }),
];

function onEventDoubleClick(event: RowDoubleClickedEvent<MeterEvent>): void {
    if (event.data) {
        openRecord(
            'meters',
            event.data.meter_id,
            `Meter ${event.data.meter_serial}`,
        );
    }
}

/* ---- wiring --------------------------------------------------------- */

usePanelActions(props.params.api.id, { onRefresh: () => void load() });
onRecordChange('saved', () => void load());

let refreshTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    void load();
    // New half-hourly readings arrive every 30 minutes; aggregates are cached for 60 s.
    refreshTimer = setInterval(() => {
        if (
            document.visibilityState === 'visible' &&
            props.params.api.isVisible
        ) {
            void load();
        }
    }, 120_000);
});

onUnmounted(() => {
    controller?.abort();

    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});
</script>

<template>
    <div class="erp-module erp-dashboard">
        <div class="erp-toolbar" role="toolbar" aria-label="Dashboard filters">
            <label class="erp-toolbar-label" for="dash-preset">Period</label>
            <Select
                v-model="preset"
                label-id="dash-preset"
                :options="presets"
                option-label="label"
                option-value="value"
                size="small"
                class="erp-toolbar-select"
            />
            <DatePicker
                v-if="preset === 'custom'"
                v-model="customRange"
                selection-mode="range"
                date-format="yy-mm-dd"
                :manual-input="false"
                show-icon
                icon-display="input"
                size="small"
                class="erp-toolbar-range"
                aria-label="Custom date range"
            />
            <span v-if="data" class="erp-muted erp-toolbar-text">
                {{ fmt.date(data.range.from) }} – {{ fmt.date(data.range.to) }},
                compared with {{ fmt.date(data.range.previousFrom) }} –
                {{ fmt.date(data.range.previousTo) }}
            </span>
            <span class="erp-toolbar-fill" />
            <span v-if="data" class="erp-muted erp-toolbar-text"
                >Updated {{ fmt.time(new Date(data.generatedAt)) }}</span
            >
            <Button
                v-tooltip.bottom="'Refresh (Alt+R)'"
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                text
                aria-label="Refresh dashboard"
                :loading="loading"
                @click="load"
            />
            <Button
                v-tooltip.bottom="
                    'Put every widget back in its default place and size'
                "
                label="Reset widgets"
                icon="pi pi-th-large"
                size="small"
                severity="secondary"
                text
                @click="resetWidgets"
            />
        </div>

        <div v-if="error" class="erp-inline-error" role="alert">
            <i class="pi pi-exclamation-triangle" /> Couldn't load the
            dashboard: {{ error }}
            <Button label="Retry" size="small" text @click="load" />
        </div>

        <div class="erp-dashboard-scroll">
            <GridLayout
                v-model:layout="widgets"
                :col-num="12"
                :row-height="24"
                :margin="[8, 8]"
                :is-draggable="true"
                :is-resizable="true"
                :vertical-compact="true"
                :use-css-transforms="true"
                @layout-updated="onLayoutUpdated"
            >
                <GridItem
                    v-for="item in widgets"
                    :key="item.i"
                    :x="item.x"
                    :y="item.y"
                    :w="item.w"
                    :h="item.h"
                    :i="item.i"
                    :min-w="item.minW"
                    :min-h="item.minH"
                    drag-allow-from=".erp-widget-head"
                    drag-ignore-from=".erp-widget-body"
                >
                    <section
                        class="erp-widget"
                        :aria-label="titles[item.i as WidgetId]"
                    >
                        <header class="erp-widget-head">
                            <i
                                class="pi pi-ellipsis-v erp-widget-grip"
                                aria-hidden="true"
                            />
                            <h3>{{ titles[item.i as WidgetId] }}</h3>
                            <span class="erp-toolbar-fill" />
                            <button
                                v-if="
                                    item.i === 'events' ||
                                    item.i === 'kpi-alarms'
                                "
                                type="button"
                                class="erp-link-btn"
                                @click="openModule('events')"
                            >
                                All events
                            </button>
                            <button
                                v-if="item.i === 'meters'"
                                type="button"
                                class="erp-link-btn"
                                @click="openModule('meters')"
                            >
                                All meters
                            </button>
                            <button
                                v-if="
                                    item.i === 'load' || item.i === 'kpi-energy'
                                "
                                type="button"
                                class="erp-link-btn"
                                @click="openModule('readings')"
                            >
                                Raw readings
                            </button>
                            <button
                                v-if="item.i === 'kpi-complete'"
                                type="button"
                                class="erp-link-btn"
                                @click="openModule('reports')"
                            >
                                Report
                            </button>
                        </header>
                        <div class="erp-widget-body">
                            <template v-if="!data">
                                <div class="erp-skeleton" aria-hidden="true" />
                            </template>
                            <KpiCard
                                v-else-if="kpis && item.i === 'kpi-energy'"
                                label="Energy imported"
                                v-bind="kpis.energy"
                                :comparison="comparison"
                                neutral
                            />
                            <KpiCard
                                v-else-if="kpis && item.i === 'kpi-demand'"
                                label="Network peak demand"
                                v-bind="kpis.demand"
                                :comparison="comparison"
                                inverse
                            />
                            <KpiCard
                                v-else-if="kpis && item.i === 'kpi-complete'"
                                label="Data completeness"
                                v-bind="kpis.complete"
                                :comparison="`pts ${comparison}`"
                                absolute
                            />
                            <KpiCard
                                v-else-if="kpis && item.i === 'kpi-alarms'"
                                label="Alarms"
                                v-bind="kpis.alarms"
                                :comparison="comparison"
                                inverse
                            />
                            <EChart
                                v-else-if="item.i === 'load'"
                                :option="loadOption"
                                :loading="loading"
                                label="Line chart of total network load in kilowatts compared with the previous period"
                            />
                            <EChart
                                v-else-if="item.i === 'types'"
                                :option="typesOption"
                                :loading="loading"
                                label="Donut chart of energy imported by meter type"
                            />
                            <EChart
                                v-else-if="item.i === 'meters'"
                                :option="metersOption"
                                :loading="loading"
                                label="Bar chart of the ten meters with the most energy imported"
                                @chart-click="onMeterBarClick"
                            />
                            <EChart
                                v-else-if="item.i === 'heatmap'"
                                :option="heatOption"
                                :loading="loading"
                                label="Heatmap of average network load by weekday and hour"
                            />
                            <div
                                v-else-if="item.i === 'network'"
                                class="erp-net-list"
                            >
                                <table class="erp-gw-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Headend</th>
                                            <th scope="col">Status</th>
                                            <th scope="col" class="num">
                                                Gateways
                                            </th>
                                            <th scope="col" class="num">
                                                Meters reporting
                                            </th>
                                            <th scope="col" class="num">
                                                Energy
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="h in data.network"
                                            :key="h.id"
                                            tabindex="0"
                                            :title="`Open ${h.code} ${h.name}`"
                                            @click="
                                                openHeadend(
                                                    h.id,
                                                    `${h.code} ${h.name}`,
                                                )
                                            "
                                            @keydown.enter="
                                                openHeadend(
                                                    h.id,
                                                    `${h.code} ${h.name}`,
                                                )
                                            "
                                        >
                                            <td>
                                                <span class="erp-code">{{
                                                    h.code
                                                }}</span>
                                                {{ h.name }}
                                                <span class="erp-muted"
                                                    >· {{ h.city }}</span
                                                >
                                            </td>
                                            <td>
                                                <span
                                                    :class="`erp-tag erp-tag--${h.status}`"
                                                    >{{ h.status }}</span
                                                >
                                            </td>
                                            <td
                                                class="num"
                                                :class="{
                                                    'erp-num-alert':
                                                        h.gatewaysOnline <
                                                        h.gateways,
                                                }"
                                            >
                                                {{ h.gatewaysOnline }}/{{
                                                    h.gateways
                                                }}
                                            </td>
                                            <td
                                                class="num"
                                                :class="{
                                                    'erp-num-alert':
                                                        h.metersReporting <
                                                        h.meters,
                                                }"
                                            >
                                                {{ h.metersReporting }}/{{
                                                    h.meters
                                                }}
                                            </td>
                                            <td class="num">
                                                {{ fmt.energy(h.kwh) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <AgGridVue
                                v-else-if="item.i === 'events'"
                                class="erp-grid"
                                :theme="gridTheme"
                                :row-data="data.recentEvents"
                                :column-defs="eventColumns"
                                :default-col-def="{
                                    sortable: true,
                                    resizable: true,
                                }"
                                @row-double-clicked="onEventDoubleClick"
                            />
                        </div>
                    </section>
                </GridItem>
            </GridLayout>
        </div>
    </div>
</template>
