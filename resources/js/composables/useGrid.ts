import type {
    ColumnState,
    GridApi,
    GridReadyEvent,
    IDatasource,
    IGetRowsParams,
} from 'ag-grid-community';
import { computed, onUnmounted, reactive, ref, shallowRef, watch } from 'vue';
import { useLayoutPersistence } from '@/composables/useLayoutPersistence';
import { workspace } from '@/composables/useWorkspace';
import { api, buildQuery, download } from '@/lib/api';
import { entities } from '@/lib/modules';
import type { EntityKey, GridResponse } from '@/types/erp';

/**
 * Wires an AG Grid (Community, infinite row model) to a Laravel grid
 * endpoint: server-side paging/sort/filter, a quick-search box, a toolbar
 * value filter, row counts for the status bar, CSV export of the current
 * view and per-user column state persistence.
 */

export interface UseGridOptions {
    entity: EntityKey;
    panelId: string;
    /** Always-applied filter model, for scoped grids. */
    baseFilter?: Record<string, unknown>;
    /** Field the toolbar multi-select filters on (e.g. status). */
    toolbarField?: string;
    blockSize?: number;
}

export function useGrid<T extends { id: number }>(options: UseGridOptions) {
    const endpoint = entities[options.entity].endpoint;
    const gridApi = shallowRef<GridApi<T> | null>(null);
    const search = ref('');
    const toolbarValues = ref<(string | number)[]>([]);
    const selected = shallowRef<T | null>(null);
    const error = ref<string | null>(null);
    const stats = reactive({
        loaded: 0,
        total: 0,
        selected: 0,
        loading: false,
    });
    const columns = useLayoutPersistence<ColumnState[]>(
        `grid:${options.entity}`,
    );

    let lastSort: unknown[] = [];
    let lastFilter: Record<string, unknown> = {};
    let restoring = false;
    let controller: AbortController | null = null;

    function effectiveFilter(
        model: Record<string, unknown>,
    ): Record<string, unknown> {
        const filter: Record<string, unknown> = {
            ...model,
            ...options.baseFilter,
        };

        if (options.toolbarField && toolbarValues.value.length > 0) {
            filter[options.toolbarField] = {
                filterType: 'set',
                values: toolbarValues.value,
            };
        }

        return filter;
    }

    const datasource: IDatasource = {
        getRows: async (params: IGetRowsParams) => {
            lastSort = params.sortModel;
            lastFilter = effectiveFilter(
                params.filterModel as Record<string, unknown>,
            );
            stats.loading = true;
            error.value = null;

            controller = new AbortController();

            try {
                const response = await api.get<GridResponse<T>>(
                    endpoint +
                        buildQuery({
                            start: params.startRow,
                            end: params.endRow,
                            sort: lastSort.length ? lastSort : null,
                            filter: Object.keys(lastFilter).length
                                ? lastFilter
                                : null,
                            q: search.value.trim(),
                        }),
                    { signal: controller.signal },
                );

                params.successCallback(
                    response.data,
                    response.meta.lastRow ?? undefined,
                );
                stats.total = response.meta.total;
                stats.loaded = Math.min(
                    response.meta.total,
                    Math.max(stats.loaded, response.meta.end),
                );

                if (params.startRow === 0) {
                    stats.loaded = response.meta.end;
                }
            } catch (e) {
                if ((e as Error).name !== 'AbortError') {
                    error.value = (e as Error).message;
                }

                params.failCallback();
            } finally {
                stats.loading = false;
            }
        },
    };

    function onGridReady(event: GridReadyEvent<T>): void {
        gridApi.value = event.api;

        const saved = columns.initial();

        if (saved && saved.length) {
            restoring = true;
            event.api.applyColumnState({ state: saved, applyOrder: true });
            restoring = false;
        }

        event.api.setGridOption('datasource', datasource);
    }

    /** Re-fetch the loaded blocks in place (keeps scroll position). */
    function refresh(): void {
        gridApi.value?.refreshInfiniteCache();
    }

    /** Drop everything and start from the first row (after a filter change). */
    function reload(): void {
        stats.loaded = 0;
        gridApi.value?.purgeInfiniteCache();
        gridApi.value?.ensureIndexVisible(0, 'top');
    }

    let searchTimer: ReturnType<typeof setTimeout> | null = null;

    watch(search, () => {
        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(reload, 300);
    });

    watch(toolbarValues, reload, { deep: true });

    function onSelectionChanged(): void {
        const rows = gridApi.value?.getSelectedRows() ?? [];
        selected.value = rows[0] ?? null;
        stats.selected = rows.length;
    }

    function saveColumns(): void {
        if (restoring || !gridApi.value) {
            return;
        }

        columns.save(gridApi.value.getColumnState());
    }

    async function resetColumns(): Promise<void> {
        restoring = true;
        gridApi.value?.resetColumnState();
        restoring = false;
        await columns.reset();
    }

    function exportUrl(): string {
        return (
            `${endpoint}/export` +
            buildQuery({
                sort: lastSort.length ? lastSort : null,
                filter: Object.keys(lastFilter).length ? lastFilter : null,
                q: search.value.trim(),
            })
        );
    }

    function exportCsv(): void {
        download(exportUrl());
    }

    const hasActiveFilters = computed(
        () => search.value.trim() !== '' || toolbarValues.value.length > 0,
    );

    watch(
        () => ({ ...stats }),
        (s) => {
            workspace.gridStats[options.panelId] = {
                loaded: s.loaded,
                total: s.total,
                selected: s.selected,
            };
        },
        { deep: true },
    );

    onUnmounted(() => {
        controller?.abort();
        delete workspace.gridStats[options.panelId];
    });

    return {
        gridApi,
        search,
        toolbarValues,
        selected,
        stats,
        error,
        hasActiveFilters,
        onGridReady,
        onSelectionChanged,
        saveColumns,
        resetColumns,
        refresh,
        reload,
        exportCsv,
        exportUrl,
    };
}
