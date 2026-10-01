<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { fmt } from '@/lib/format';
import Sparkline from '@/modules/dashboard/Sparkline.vue';

/**
 * A headline number with its change against the previous period. The delta
 * pairs color with an arrow and a word, so it never relies on color alone;
 * `inverse` flips good/bad for metrics where up is worse (peak demand,
 * alarms), `neutral` shows direction only (energy use is neither), and
 * `absolute` treats the delta as percentage points rather than percent.
 */
const props = defineProps<{
    label: string;
    value: string;
    delta: number | null;
    spark: number[];
    comparison: string;
    footnote?: string;
    inverse?: boolean;
    neutral?: boolean;
    absolute?: boolean;
}>();

const { chart } = useTheme();

const tone = computed(() => {
    if (props.delta === null || Math.abs(props.delta) < 0.05) {
        return 'flat';
    }

    if (props.neutral) {
        return 'neutral';
    }

    const up = props.delta > 0;

    return up !== Boolean(props.inverse) ? 'good' : 'bad';
});

const arrow = computed(() =>
    props.delta === null || Math.abs(props.delta) < 0.05
        ? 'pi-minus'
        : props.delta > 0
          ? 'pi-arrow-up-right'
          : 'pi-arrow-down-right',
);
const word = computed(() =>
    tone.value === 'good'
        ? 'better'
        : tone.value === 'bad'
          ? 'worse'
          : tone.value === 'neutral'
            ? props.delta! > 0
                ? 'higher'
                : 'lower'
            : 'flat',
);
const deltaText = computed(() =>
    props.absolute && props.delta !== null
        ? `${props.delta > 0 ? '+' : ''}${props.delta.toFixed(2)}`
        : fmt.delta(props.delta),
);
</script>

<template>
    <div class="erp-kpi" :aria-label="label">
        <div class="erp-kpi-value">{{ value }}</div>
        <div
            class="erp-kpi-delta"
            :class="`is-${tone}`"
            :title="`${deltaText} ${comparison} (${word})`"
        >
            <i :class="['pi', arrow]" aria-hidden="true" />
            <span>{{ deltaText }}</span>
            <span class="erp-kpi-vs">{{ comparison }}</span>
        </div>
        <Sparkline
            class="erp-kpi-spark"
            :values="spark"
            :color="chart.series[0]"
        />
        <div v-if="footnote" class="erp-kpi-foot">{{ footnote }}</div>
    </div>
</template>
