<script setup lang="ts">
import { BarChart, HeatmapChart, LineChart, PieChart } from 'echarts/charts';
import {
    AxisPointerComponent,
    GridComponent,
    LegendComponent,
    TooltipComponent,
    VisualMapComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import type { EChartsCoreOption } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';

use([
    CanvasRenderer,
    LineChart,
    BarChart,
    PieChart,
    HeatmapChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    VisualMapComponent,
    AxisPointerComponent,
]);

const emit = defineEmits<{
    chartClick: [params: { dataIndex: number; name: string }];
}>();

function onClick(params: { dataIndex: number; name: string }): void {
    emit('chartClick', params);
}

defineProps<{
    option: EChartsCoreOption;
    label: string;
    loading?: boolean;
}>();
</script>

<template>
    <div class="erp-chart" role="img" :aria-label="label">
        <VChart
            @click="onClick"
            :option="option"
            autoresize
            :update-options="{ notMerge: true }"
            class="erp-chart-canvas"
        />
        <div v-if="loading" class="erp-chart-loading" aria-hidden="true">
            <i class="pi pi-spin pi-spinner" />
        </div>
    </div>
</template>
