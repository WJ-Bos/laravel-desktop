<script setup lang="ts">
import type { DockviewApi, DockviewPanelApi } from 'dockview-vue';
import type { Component } from 'vue';
import type { RecordPanelParams } from '@/composables/useWorkspace';
import GatewayForm from '@/modules/forms/GatewayForm.vue';
import HeadendForm from '@/modules/forms/HeadendForm.vue';
import MeterForm from '@/modules/forms/MeterForm.vue';
import type { EditableEntity } from '@/types/erp';

const props = defineProps<{
    params: {
        params: RecordPanelParams;
        api: DockviewPanelApi;
        containerApi: DockviewApi;
    };
}>();

const forms: Record<EditableEntity, Component> = {
    headends: HeadendForm,
    gateways: GatewayForm,
    meters: MeterForm,
};

// Params are read once: saving a new record updates them in place without remounting the form.
const initial = { ...props.params.params };
</script>

<template>
    <component
        :is="forms[initial.entity]"
        :panel="params.api"
        :params="initial"
    />
</template>
