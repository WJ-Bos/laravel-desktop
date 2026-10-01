<script setup lang="ts">
import { computed } from 'vue';

/** Tiny inline trend line: 2px stroke, last value marked. */
const props = withDefaults(
    defineProps<{ values: number[]; color: string; height?: number }>(),
    { height: 28 },
);

const WIDTH = 120;

const geometry = computed(() => {
    const values =
        props.values.length > 1
            ? props.values
            : [0, ...(props.values.length ? props.values : [0])];
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;
    const pad = 3;
    const step = (WIDTH - pad * 2) / (values.length - 1);
    const points = values.map(
        (v, i) =>
            [
                pad + i * step,
                pad + (props.height - pad * 2) * (1 - (v - min) / span),
            ] as const,
    );

    return {
        line: points
            .map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`)
            .join(' '),
        area: `${pad},${props.height} ${points.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ')} ${WIDTH - pad},${props.height}`,
        last: points[points.length - 1],
    };
});
</script>

<template>
    <svg
        class="erp-spark"
        :viewBox="`0 0 ${WIDTH} ${height}`"
        preserveAspectRatio="none"
        :height="height"
        aria-hidden="true"
    >
        <polygon :points="geometry.area" :fill="color" opacity="0.08" />
        <polyline
            :points="geometry.line"
            fill="none"
            :stroke="color"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
            vector-effect="non-scaling-stroke"
        />
        <circle
            :cx="geometry.last[0]"
            :cy="geometry.last[1]"
            r="2.5"
            :fill="color"
        />
    </svg>
</template>
