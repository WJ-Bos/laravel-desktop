import type { EChartsCoreOption } from 'echarts/core';
import type { ChartTokens } from '@/composables/useTheme';
import { fmt } from '@/lib/format';
import type { DashboardData } from '@/types/erp';

/**
 * ECharts option builders. Conventions shared with the meter and report
 * panels: thin 2px lines, rounded bar ends anchored at the baseline, a 2px
 * surface gap between filled marks, recessive grid and axes, text in text
 * colors (never series colors), one y-axis per chart, hover on every mark.
 */

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export function baseTextStyle(t: ChartTokens) {
    return { fontFamily: t.fontFamily, color: t.textMuted, fontSize: 11 };
}

export function tooltipBase(t: ChartTokens) {
    return {
        backgroundColor: t.tooltipBg,
        borderColor: t.tooltipBorder,
        borderWidth: 1,
        padding: [6, 8],
        textStyle: { color: t.text, fontFamily: t.fontFamily, fontSize: 12 },
        extraCssText:
            'box-shadow: 0 2px 8px rgba(0,0,0,.12); border-radius: 3px;',
    };
}

export function valueAxis(t: ChartTokens, formatter: (v: number) => string) {
    return {
        type: 'value' as const,
        axisLabel: { color: t.textMuted, fontSize: 11, formatter },
        splitLine: { lineStyle: { color: t.grid } },
        axisLine: { show: false },
        axisTick: { show: false },
    };
}

export function categoryAxis(
    t: ChartTokens,
    data: string[],
    extra: Record<string, unknown> = {},
) {
    return {
        type: 'category' as const,
        data,
        axisLabel: { color: t.textMuted, fontSize: 11, hideOverlap: true },
        axisLine: { lineStyle: { color: t.axis } },
        axisTick: { show: false },
        ...extra,
    };
}

function bucketLabel(
    bucket: string,
    granularity: DashboardData['range']['granularity'],
): string {
    const d = new Date(
        bucket.length === 10 ? `${bucket}T00:00:00` : bucket.replace(' ', 'T'),
    );
    const day = d.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
    });

    return granularity === 'day'
        ? day
        : `${day} ${d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
}

export function networkLoadOption(
    data: DashboardData,
    t: ChartTokens,
): EChartsCoreOption {
    const { buckets, kw, previousKw } = data.loadProfile;
    const labels = buckets.map((b) => bucketLabel(b, data.range.granularity));

    return {
        textStyle: baseTextStyle(t),
        grid: { left: 8, right: 16, top: 34, bottom: 4, containLabel: true },
        legend: {
            top: 0,
            left: 0,
            itemWidth: 16,
            itemHeight: 2,
            icon: 'rect',
            textStyle: { color: t.text, fontSize: 11 },
            data: ['This period', 'Previous period'],
        },
        tooltip: {
            ...tooltipBase(t),
            trigger: 'axis',
            axisPointer: {
                type: 'line',
                lineStyle: { color: t.axis, width: 1 },
            },
            formatter: (
                params: {
                    dataIndex: number;
                    seriesName: string;
                    value: number;
                    marker: string;
                }[],
            ) => {
                const i = params[0]?.dataIndex ?? 0;
                const rows = params
                    .filter((p) => typeof p.value === 'number')
                    .map(
                        (p) =>
                            `${p.marker}${p.seriesName} <b style="float:right;margin-left:16px">${fmt.power(p.value)}</b>`,
                    )
                    .join('<br/>');

                return `<div style="font-weight:600;margin-bottom:2px">${labels[i]}</div>${rows}`;
            },
        },
        xAxis: categoryAxis(t, labels, { boundaryGap: false }),
        yAxis: valueAxis(t, (v) => fmt.power(v)),
        series: [
            {
                name: 'Previous period',
                type: 'line',
                data: previousKw,
                showSymbol: false,
                lineStyle: { width: 2, type: 'dashed', color: t.comparison },
                itemStyle: { color: t.comparison },
                emphasis: { disabled: true },
                z: 1,
            },
            {
                name: 'This period',
                type: 'line',
                data: kw,
                showSymbol: false,
                symbolSize: 8,
                lineStyle: { width: 2, color: t.series[0] },
                itemStyle: {
                    color: t.series[0],
                    borderColor: t.surface,
                    borderWidth: 2,
                },
                areaStyle: { color: t.series[0], opacity: 0.07 },
                z: 2,
            },
        ],
    };
}

/** Energy by meter type. Types always come in the same order, so each keeps its color. */
export function typeDonutOption(
    data: DashboardData,
    t: ChartTokens,
): EChartsCoreOption {
    const slices = data.byType.map((s, i) => ({
        name: s.label,
        value: s.kwh,
        meters: s.meters,
        color: t.series[i],
    }));
    const total = slices.reduce((sum, s) => sum + s.value, 0);

    return {
        textStyle: baseTextStyle(t),
        tooltip: {
            ...tooltipBase(t),
            trigger: 'item',
            formatter: (p: {
                name: string;
                value: number;
                percent: number;
                marker: string;
                dataIndex: number;
            }) =>
                `${p.marker}<b>${p.name}</b><br/>${fmt.energy(p.value)} · ${p.percent.toFixed(1)}%<br/><span style="color:${t.textMuted}">${slices[p.dataIndex].meters} meters</span>`,
        },
        legend: {
            orient: 'vertical',
            right: 0,
            top: 'middle',
            itemWidth: 10,
            itemHeight: 10,
            itemGap: 7,
            icon: 'roundRect',
            textStyle: { color: t.text, fontSize: 11 },
            formatter: (name: string) => {
                const slice = slices.find((s) => s.name === name);

                return slice && total
                    ? `${name}  ${((slice.value / total) * 100).toFixed(0)}%`
                    : name;
            },
        },
        series: [
            {
                type: 'pie',
                radius: ['52%', '78%'],
                center: ['32%', '50%'],
                startAngle: 90,
                itemStyle: {
                    borderColor: t.surface,
                    borderWidth: 2,
                    borderRadius: 2,
                },
                label: {
                    show: true,
                    position: 'center',
                    formatter: () => `{v|${fmt.energy(total)}}\n{l|imported}`,
                    rich: {
                        v: {
                            fontSize: 15,
                            fontWeight: 600,
                            color: t.text,
                            lineHeight: 20,
                        },
                        l: { fontSize: 11, color: t.textMuted },
                    },
                },
                labelLine: { show: false },
                emphasis: { scale: true, scaleSize: 4 },
                data: slices.map((s) => ({
                    name: s.name,
                    value: s.value,
                    itemStyle: { color: s.color },
                })),
            },
        ],
    };
}

export function topMetersOption(
    data: DashboardData,
    t: ChartTokens,
): EChartsCoreOption {
    const meters = [...data.topMeters].reverse();

    return {
        textStyle: baseTextStyle(t),
        grid: { left: 8, right: 20, top: 6, bottom: 4, containLabel: true },
        tooltip: {
            ...tooltipBase(t),
            trigger: 'item',
            formatter: (p: { dataIndex: number }) => {
                const m = meters[p.dataIndex];

                return `<b>${m.serial}</b><br/>${m.name}<br/>${fmt.energy(m.kwh)} · max ${fmt.power(m.max_kva, 'kVA')}<br/><span style="color:${t.textMuted}">Click to open the meter</span>`;
            },
        },
        xAxis: valueAxis(t, (v) => fmt.energy(v)),
        yAxis: categoryAxis(
            t,
            meters.map((m) => m.serial),
            {
                axisLabel: {
                    color: t.text,
                    fontSize: 11,
                    fontFamily: t.fontFamily,
                },
            },
        ),
        series: [
            {
                type: 'bar',
                data: meters.map((m) => m.kwh),
                barMaxWidth: 14,
                cursor: 'pointer',
                itemStyle: { color: t.series[0], borderRadius: [0, 4, 4, 0] },
            },
        ],
    };
}

export function heatmapOption(
    data: DashboardData,
    t: ChartTokens,
): EChartsCoreOption {
    const hours = Array.from({ length: 24 }, (_, i) => i);
    const cells = new Map<string, number>();
    data.heatmap.forEach(([wd, hr, kw]) => cells.set(`${wd}:${hr}`, kw));

    const values = WEEKDAYS.flatMap((_, wd) =>
        hours.map((hr) => [hr, wd, cells.get(`${wd}:${hr}`) ?? 0]),
    );
    const max = Math.max(1, ...values.map((v) => v[2]));
    const min = Math.min(...values.map((v) => v[2]));

    return {
        textStyle: baseTextStyle(t),
        grid: { left: 8, right: 8, top: 4, bottom: 34, containLabel: true },
        tooltip: {
            ...tooltipBase(t),
            formatter: (p: { value: [number, number, number] }) =>
                `<b>${WEEKDAYS[p.value[1]]} ${String(p.value[0]).padStart(2, '0')}:00</b><br/>Average load ${fmt.power(p.value[2])}`,
        },
        xAxis: categoryAxis(
            t,
            hours.map((h) => String(h).padStart(2, '0')),
        ),
        yAxis: categoryAxis(t, WEEKDAYS, {
            inverse: true,
            axisLine: { show: false },
        }),
        visualMap: {
            min,
            max,
            calculable: false,
            orient: 'horizontal',
            left: 'center',
            bottom: 0,
            itemHeight: 140,
            itemWidth: 10,
            text: [fmt.power(max), fmt.power(min)],
            textStyle: { color: t.textMuted, fontSize: 11 },
            inRange: { color: t.sequential },
        },
        series: [
            {
                type: 'heatmap',
                data: values,
                itemStyle: {
                    borderColor: t.surface,
                    borderWidth: 2,
                    borderRadius: 2,
                },
                emphasis: {
                    itemStyle: { borderColor: t.text, borderWidth: 1 },
                },
            },
        ],
    };
}
