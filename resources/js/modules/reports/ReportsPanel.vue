<script setup lang="ts">
import type { ColDef } from 'ag-grid-community';
import { AgGridVue } from 'ag-grid-vue3';
import type { DockviewApi, DockviewPanelApi } from 'dockview-vue';
import type { EChartsCoreOption } from 'echarts/core';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import { computed, onMounted, ref, shallowRef, watch } from 'vue';
import EChart from '@/components/erp/EChart.vue';
import { useTheme } from '@/composables/useTheme';
import { usePanelActions } from '@/composables/useWorkspace';
import { RIGHT, gridTheme } from '@/lib/agGrid';
import { api, buildQuery, download } from '@/lib/api';
import { fmt, isoDate } from '@/lib/format';
import {
    baseTextStyle,
    categoryAxis,
    tooltipBase,
    valueAxis,
} from '@/modules/dashboard/chartOptions';
import type { ReportColumnType, ReportData } from '@/types/erp';

const props = defineProps<{
    params: {
        params: Record<string, unknown>;
        api: DockviewPanelApi;
        containerApi: DockviewApi;
    };
}>();

const reportTypes = [
    {
        value: 'consumption-by-meter',
        label: 'Consumption by meter',
        description:
            'Energy, reactive energy, maximum demand, power factor and load factor per meter.',
    },
    {
        value: 'consumption-by-headend',
        label: 'Consumption by headend',
        description:
            'Energy imported and exported per headend, with its share of the network.',
    },
    {
        value: 'daily-consumption',
        label: 'Daily consumption',
        description:
            'Network energy per day with the coincident peak half-hour.',
    },
    {
        value: 'maximum-demand',
        label: 'Maximum demand',
        description:
            'The 25 meters with the highest half-hourly kVA demand and when it occurred.',
    },
    {
        value: 'data-completeness',
        label: 'Data completeness',
        description:
            'Expected vs received half-hourly readings per gateway, worst first.',
    },
    {
        value: 'power-quality',
        label: 'Power quality',
        description:
            'Voltage range per meter and intervals more than 10% from nominal.',
    },
];

const quickRanges = [
    { label: '1d', value: 1 },
    { label: '7d', value: 7 },
    { label: '14d', value: 14 },
    { label: '30d', value: 30 },
    { label: '60d', value: 60 },
];

function daysBack(days: number): (Date | null)[] {
    const to = new Date();
    to.setHours(0, 0, 0, 0);
    const from = new Date(to);
    from.setDate(from.getDate() - (days - 1));

    return [from, to];
}

const type = ref('consumption-by-meter');
const quick = ref<number | null>(14);
const dates = ref<(Date | null)[] | null>(daysBack(14));
const report = shallowRef<ReportData | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const { chart: tokens } = useTheme();

const range = computed(() => {
    const [from, to] = dates.value ?? [];

    return from && to ? { from: isoDate(from), to: isoDate(to) } : null;
});

watch(quick, (days) => {
    if (days) {
        dates.value = daysBack(days);
    }
});

async function run(): Promise<void> {
    if (!range.value) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        report.value = await api.get<ReportData>(
            `/api/reports/${type.value}${buildQuery(range.value)}`,
        );
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

watch([type, range], () => void run());

function exportCsv(): void {
    if (range.value) {
        download(`/api/reports/${type.value}/export${buildQuery(range.value)}`);
    }
}

function formatValue(value: unknown, kind: ReportColumnType): string {
    if (typeof value !== 'number') {
        return String(value ?? '');
    }

    switch (kind) {
        case 'kwh':
            return fmt.energy(value);
        case 'kva':
            return fmt.power(value, 'kVA');
        case 'decimal':
            return fmt.dec1(value);
        case 'number3':
            return fmt.dec3(value);
        case 'percent':
            return fmt.pct(value);
        case 'number':
            return fmt.int(value);
        default:
            return String(value);
    }
}

const columnDefs = computed<ColDef[]>(() =>
    (report.value?.columns ?? []).map((c) => ({
        field: c.field,
        headerName: c.header,
        sortable: true,
        resizable: true,
        flex: c.field === 'name' || c.field === 'message' ? 1 : undefined,
        minWidth: c.field === 'name' ? 180 : 90,
        type: c.type === 'text' ? undefined : 'rightAligned',
        cellClass:
            c.type === 'text'
                ? c.field === 'serial' ||
                  c.field === 'gateway' ||
                  c.field === 'code'
                    ? 'erp-code'
                    : undefined
                : RIGHT,
        valueFormatter: (p) => formatValue(p.value, c.type),
    })),
);

const chartOption = computed<EChartsCoreOption>(() => {
    const r = report.value;
    const t = tokens.value;

    if (!r || r.rows.length === 0) {
        return {};
    }

    const spec = r.chart;
    const series = spec.series[0];
    const valueType =
        r.columns.find((c) => c.field === series.field)?.type ?? 'number';
    const unit = spec.unit ?? '';
    const format = (v: number) =>
        unit === 'kWh'
            ? fmt.energy(v)
            : unit === 'kVA'
              ? fmt.power(v, 'kVA')
              : `${fmt.compact(v)}${unit === '%' ? '%' : ''}`;
    const rows = spec.limit ? r.rows.slice(0, spec.limit) : r.rows;
    const tooltip = { ...tooltipBase(t) };

    if (spec.kind === 'pie') {
        // Largest slices keep the first validated slots; the tail folds into "Other".
        const head = rows.slice(0, t.series.length - 1);
        const tail = rows.slice(t.series.length - 1);
        const data = head.map((row, i) => ({
            name: String(row[spec.category]),
            value: Number(row[series.field]),
            itemStyle: { color: t.series[i] },
        }));

        if (tail.length) {
            data.push({
                name: `Other (${tail.length})`,
                value: tail.reduce(
                    (s, row) => s + Number(row[series.field]),
                    0,
                ),
                itemStyle: { color: t.other },
            });
        }

        return {
            textStyle: baseTextStyle(t),
            tooltip: {
                ...tooltip,
                trigger: 'item',
                formatter: (p: {
                    name: string;
                    value: number;
                    percent: number;
                    marker: string;
                }) =>
                    `${p.marker}<b>${p.name}</b><br/>${format(p.value)} · ${p.percent.toFixed(1)}%`,
            },
            legend: {
                type: 'scroll',
                orient: 'vertical',
                right: 8,
                top: 'middle',
                icon: 'roundRect',
                itemWidth: 10,
                itemHeight: 10,
                textStyle: { color: t.text, fontSize: 11 },
            },
            series: [
                {
                    type: 'pie',
                    radius: ['50%', '78%'],
                    center: ['35%', '50%'],
                    itemStyle: {
                        borderColor: t.surface,
                        borderWidth: 2,
                        borderRadius: 2,
                    },
                    label: {
                        show: true,
                        color: t.text,
                        fontSize: 11,
                        formatter: '{d}%',
                    },
                    labelLine: { lineStyle: { color: t.axis } },
                    data,
                },
            ],
        };
    }

    const categories = rows.map((row) => String(row[spec.category]));
    const values = rows.map((row) => Number(row[series.field]));

    if (spec.kind === 'line') {
        return {
            textStyle: baseTextStyle(t),
            grid: {
                left: 8,
                right: 16,
                top: 16,
                bottom: 4,
                containLabel: true,
            },
            tooltip: {
                ...tooltip,
                trigger: 'axis',
                axisPointer: { type: 'line', lineStyle: { color: t.axis } },
                valueFormatter: (v: number) => formatValue(v, valueType),
            },
            xAxis: categoryAxis(t, categories, { boundaryGap: false }),
            yAxis: valueAxis(t, format),
            series: [
                {
                    name: series.name,
                    type: 'line',
                    data: values,
                    showSymbol: values.length <= 31,
                    symbolSize: 8,
                    lineStyle: { width: 2, color: t.series[0] },
                    itemStyle: {
                        color: t.series[0],
                        borderColor: t.surface,
                        borderWidth: 2,
                    },
                    areaStyle: { color: t.series[0], opacity: 0.06 },
                },
            ],
        };
    }

    return {
        textStyle: baseTextStyle(t),
        grid: { left: 8, right: 20, top: 12, bottom: 4, containLabel: true },
        tooltip: {
            ...tooltip,
            trigger: 'item',
            formatter: (p: { name: string; value: number }) =>
                `<b>${p.name}</b><br/>${series.name}: ${formatValue(p.value, valueType)}`,
        },
        xAxis: valueAxis(t, format),
        yAxis: categoryAxis(t, [...categories].reverse(), {
            axisLabel: {
                color: t.text,
                fontSize: 11,
                width: 170,
                overflow: 'truncate',
            },
        }),
        series: [
            {
                name: series.name,
                type: 'bar',
                data: [...values].reverse(),
                barMaxWidth: 14,
                itemStyle: { color: t.series[0], borderRadius: [0, 4, 4, 0] },
            },
        ],
    };
});

const description = computed(
    () => reportTypes.find((r) => r.value === type.value)?.description ?? '',
);

usePanelActions(props.params.api.id, {
    onRefresh: () => void run(),
    onExport: exportCsv,
});

onMounted(() => void run());
</script>

<template>
    <div class="erp-module erp-reports">
        <div class="erp-toolbar" role="toolbar" aria-label="Report options">
            <label class="erp-toolbar-label" for="report-type">Report</label>
            <Select
                v-model="type"
                label-id="report-type"
                :options="reportTypes"
                option-label="label"
                option-value="value"
                size="small"
                class="erp-toolbar-select erp-toolbar-select--wide"
            />
            <span class="erp-toolbar-sep" />
            <SelectButton
                v-model="quick"
                :options="quickRanges"
                option-label="label"
                option-value="value"
                size="small"
                aria-label="Quick range"
            />
            <DatePicker
                v-model="dates"
                selection-mode="range"
                date-format="yy-mm-dd"
                :manual-input="false"
                show-icon
                icon-display="input"
                size="small"
                class="erp-toolbar-range"
                aria-label="Date range"
                @update:model-value="quick = null"
            />
            <span class="erp-toolbar-fill" />
            <Button
                label="Run"
                icon="pi pi-play"
                size="small"
                severity="secondary"
                text
                :loading="loading"
                @click="run"
            />
            <Button
                label="Export CSV"
                icon="pi pi-download"
                size="small"
                :disabled="!report"
                @click="exportCsv"
            />
        </div>

        <div v-if="error" class="erp-inline-error" role="alert">
            <i class="pi pi-exclamation-triangle" /> Couldn't run the report:
            {{ error }}
        </div>

        <div class="erp-report-body">
            <div class="erp-report-head">
                <div>
                    <h2 class="erp-record-title">
                        {{ report?.title ?? 'Report' }}
                    </h2>
                    <p class="erp-muted">
                        {{ description }}
                        <template v-if="report"
                            >{{ fmt.date(report.from) }} –
                            {{ fmt.date(report.to) }}.</template
                        >
                    </p>
                </div>
                <dl v-if="report" class="erp-summary">
                    <div v-for="item in report.summary" :key="item.label">
                        <dt>{{ item.label }}</dt>
                        <dd>{{ formatValue(item.value, item.type) }}</dd>
                    </div>
                </dl>
            </div>
            <div class="erp-report-chart">
                <EChart
                    v-if="report && report.rows.length"
                    :option="chartOption"
                    :loading="loading"
                    :label="`Chart for ${report.title}`"
                />
                <p v-else-if="report" class="erp-empty">
                    No readings in this date range. Pick a wider range to see
                    results.
                </p>
            </div>
            <div class="erp-report-table">
                <AgGridVue
                    class="erp-grid"
                    :theme="gridTheme"
                    :row-data="report?.rows ?? []"
                    :column-defs="columnDefs"
                    :loading="loading"
                />
            </div>
        </div>
    </div>
</template>
