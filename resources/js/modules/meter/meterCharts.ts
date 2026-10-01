import type { EChartsCoreOption } from 'echarts/core';
import type { ChartTokens } from '@/composables/useTheme';
import { fmt } from '@/lib/format';
import {
    baseTextStyle,
    categoryAxis,
    tooltipBase,
    valueAxis,
} from '@/modules/dashboard/chartOptions';
import type { MeterProfile } from '@/types/erp';

/** Chart builders for the meter panel; same conventions as the dashboard. */

export function bucketLabel(
    bucket: string,
    granularity: MeterProfile['range']['granularity'],
): string {
    const d = new Date(
        bucket.length === 10 ? `${bucket}T00:00:00` : bucket.replace(' ', 'T'),
    );

    if (granularity === 'day') {
        return d.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
        });
    }

    const day = d.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
    });
    const time = d.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return `${day} ${time}`;
}

function axisTooltip(
    t: ChartTokens,
    labels: string[],
    unit: string,
    digits = 2,
) {
    return {
        ...tooltipBase(t),
        trigger: 'axis',
        axisPointer: { type: 'line', lineStyle: { color: t.axis, width: 1 } },
        formatter: (
            params: {
                dataIndex: number;
                seriesName: string;
                value: number | null;
                marker: string;
            }[],
        ) => {
            const i = params[0]?.dataIndex ?? 0;
            const rows = params
                .filter((p) => p.value !== null && p.value !== undefined)
                .map(
                    (p) =>
                        `${p.marker}${p.seriesName} <b style="float:right;margin-left:16px">${p.value!.toFixed(digits)} ${unit}</b>`,
                )
                .join('<br/>');

            return `<div style="font-weight:600;margin-bottom:2px">${labels[i]}</div>${rows}`;
        },
    };
}

export function loadProfileOption(
    p: MeterProfile,
    t: ChartTokens,
    hasExport: boolean,
): EChartsCoreOption {
    const labels = p.series.buckets.map((b) =>
        bucketLabel(b, p.range.granularity),
    );
    const series: Record<string, unknown>[] = [
        {
            name: 'Import',
            type: 'line',
            data: p.series.kw,
            showSymbol: false,
            symbolSize: 8,
            step: p.range.granularity === 'interval' ? 'end' : undefined,
            lineStyle: { width: 2, color: t.series[0] },
            itemStyle: { color: t.series[0] },
            areaStyle: { color: t.series[0], opacity: 0.08 },
        },
    ];

    if (hasExport) {
        series.push({
            name: 'Export (PV)',
            type: 'line',
            data: p.series.kwExport,
            showSymbol: false,
            symbolSize: 8,
            step: p.range.granularity === 'interval' ? 'end' : undefined,
            lineStyle: { width: 2, color: t.series[2] },
            itemStyle: { color: t.series[2] },
        });
    }

    return {
        textStyle: baseTextStyle(t),
        grid: {
            left: 8,
            right: 16,
            top: hasExport ? 30 : 12,
            bottom: 4,
            containLabel: true,
        },
        legend: hasExport
            ? {
                  top: 0,
                  left: 0,
                  icon: 'rect',
                  itemWidth: 16,
                  itemHeight: 2,
                  textStyle: { color: t.text, fontSize: 11 },
              }
            : undefined,
        tooltip: axisTooltip(t, labels, 'kW'),
        xAxis: categoryAxis(t, labels, { boundaryGap: false }),
        yAxis: valueAxis(t, (v) => fmt.power(v)),
        series,
    };
}

export function voltageOption(
    p: MeterProfile,
    t: ChartTokens,
    phases: number,
): EChartsCoreOption {
    const labels = p.series.buckets.map((b) =>
        bucketLabel(b, p.range.granularity),
    );
    const names = ['L1', 'L2', 'L3'].slice(0, phases);
    const data = [p.series.v1, p.series.v2, p.series.v3];
    const values = data
        .slice(0, phases)
        .flat()
        .filter((v): v is number => v !== null);
    const min = values.length ? Math.floor(Math.min(...values) - 2) : 0;
    const max = values.length ? Math.ceil(Math.max(...values) + 2) : 1;

    return {
        textStyle: baseTextStyle(t),
        grid: { left: 8, right: 16, top: 30, bottom: 4, containLabel: true },
        legend: {
            top: 0,
            left: 0,
            icon: 'rect',
            itemWidth: 16,
            itemHeight: 2,
            textStyle: { color: t.text, fontSize: 11 },
        },
        tooltip: axisTooltip(t, labels, 'V', 1),
        xAxis: categoryAxis(t, labels, { boundaryGap: false }),
        yAxis: { ...valueAxis(t, (v) => `${v} V`), min, max, scale: true },
        series: names.map((name, i) => ({
            name,
            type: 'line',
            data: data[i],
            showSymbol: false,
            symbolSize: 8,
            lineStyle: { width: 2, color: t.series[i] },
            itemStyle: { color: t.series[i] },
        })),
    };
}

export function dailyEnergyOption(
    p: MeterProfile,
    t: ChartTokens,
): EChartsCoreOption {
    const labels = p.daily.map((d) => bucketLabel(d.day, 'day'));

    return {
        textStyle: baseTextStyle(t),
        grid: { left: 8, right: 16, top: 12, bottom: 4, containLabel: true },
        tooltip: {
            ...tooltipBase(t),
            trigger: 'item',
            formatter: (params: { dataIndex: number }) => {
                const d = p.daily[params.dataIndex];

                return `<b>${labels[params.dataIndex]}</b><br/>${fmt.energy(d.kwh_import)} imported${d.kwh_export > 0 ? `<br/>${fmt.energy(d.kwh_export)} exported` : ''}<br/>Max demand ${fmt.power(d.max_kva, 'kVA')}<br/><span style="color:${t.textMuted}">${d.intervals} of 48 intervals</span>`;
            },
        },
        xAxis: categoryAxis(t, labels),
        yAxis: valueAxis(t, (v) => fmt.energy(v)),
        series: [
            {
                type: 'bar',
                data: p.daily.map((d) => d.kwh_import),
                barMaxWidth: 22,
                itemStyle: { color: t.series[0], borderRadius: [4, 4, 0, 0] },
            },
        ],
    };
}
