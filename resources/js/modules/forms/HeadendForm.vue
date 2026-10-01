<script setup lang="ts">
import type { DockviewPanelApi } from 'dockview-vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, nextTick, onMounted, ref } from 'vue';
import FormField from '@/components/erp/FormField.vue';
import RecordShell from '@/components/erp/RecordShell.vue';
import { useLookups, useNetwork } from '@/composables/useLookups';
import { useRecordForm } from '@/composables/useRecordForm';
import { onRecordChange, openHeadend } from '@/composables/useWorkspace';
import type { RecordPanelParams } from '@/composables/useWorkspace';
import { focusInside } from '@/lib/dom';
import { fmt } from '@/lib/format';
import type { DeviceStatus, Headend } from '@/types/erp';

const props = defineProps<{
    panel: DockviewPanelApi;
    params: RecordPanelParams;
}>();

interface HeadendModel {
    name: string;
    site: string;
    city: string;
    protocol: string;
    ip_address: string;
    poll_interval_min: number;
    status: DeviceStatus;
}

const form = useRecordForm<'headends', HeadendModel>({
    entity: 'headends',
    panel: props.panel,
    params: props.params,
    empty: () => ({
        name: '',
        site: '',
        city: '',
        protocol: 'DLMS/COSEM',
        ip_address: '',
        poll_interval_min: 30,
        status: 'online',
    }),
    fromRecord: (h: Headend) => ({
        name: h.name,
        site: h.site ?? '',
        city: h.city ?? '',
        protocol: h.protocol,
        ip_address: h.ip_address ?? '',
        poll_interval_min: h.poll_interval_min,
        status: h.status,
    }),
    toPayload: (m) => ({
        ...m,
        site: m.site || null,
        city: m.city || null,
        ip_address: m.ip_address || null,
    }),
});

const { model, errors, record } = form;
const { lookups } = useLookups();
const { refresh: refreshNetwork } = useNetwork();
const uid = props.panel.id.replace(/[^\w-]/g, '_');
const firstInput = ref<InstanceType<typeof InputText> | null>(null);
const statusOptions = computed(() =>
    (lookups.value?.deviceStatuses ?? []).map((s) => ({
        value: s,
        label: fmt.titleCase(s),
    })),
);

onRecordChange('saved', (e) => {
    if (e.entity === 'headends') {
        void refreshNetwork();
    }
});

onMounted(async () => {
    await form.load();

    if (props.params.focus) {
        await nextTick();
        focusInside(firstInput.value);
    }
});
</script>

<template>
    <RecordShell
        :title="
            form.isNew.value
                ? 'New headend'
                : `${record?.code ?? ''} ${record?.name ?? ''}`
        "
        :subtitle="
            record
                ? `${record.gateways_count} gateways · ${record.meters_count} meters`
                : 'A headend polls its gateways and stores their readings.'
        "
        icon="pi pi-server"
        :dirty="form.dirty.value"
        :saving="form.saving.value"
        :loading="form.loading.value"
        :is-new="form.isNew.value"
        :load-error="form.loadError.value"
        :error-count="Object.keys(errors).length"
        @save="form.save()"
        @revert="form.revert()"
        @retry="form.load()"
    >
        <template v-if="record" #actions>
            <Button
                label="Open window"
                icon="pi pi-window-maximize"
                size="small"
                severity="secondary"
                text
                @click="openHeadend(record.id, `${record.code} ${record.name}`)"
            />
        </template>

        <fieldset class="erp-fieldset">
            <legend>Headend</legend>
            <FormField
                label="Name"
                :input-id="`${uid}-name`"
                :error="errors.name"
                required
            >
                <InputText
                    :id="`${uid}-name`"
                    ref="firstInput"
                    v-model="model.name"
                    size="small"
                    fluid
                    :invalid="!!errors.name"
                />
            </FormField>
            <FormField
                label="Site"
                :input-id="`${uid}-site`"
                :error="errors.site"
            >
                <InputText
                    :id="`${uid}-site`"
                    v-model="model.site"
                    size="small"
                    fluid
                />
            </FormField>
            <FormField
                label="City"
                :input-id="`${uid}-city`"
                :error="errors.city"
            >
                <InputText
                    :id="`${uid}-city`"
                    v-model="model.city"
                    size="small"
                    fluid
                />
            </FormField>
            <FormField
                label="Status"
                :input-id="`${uid}-status`"
                :error="errors.status"
                required
            >
                <Select
                    v-model="model.status"
                    :label-id="`${uid}-status`"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    size="small"
                    fluid
                />
            </FormField>
        </fieldset>
        <fieldset class="erp-fieldset">
            <legend>Communication</legend>
            <FormField
                label="Protocol"
                :input-id="`${uid}-protocol`"
                :error="errors.protocol"
                required
            >
                <Select
                    v-model="model.protocol"
                    :label-id="`${uid}-protocol`"
                    :options="['DLMS/COSEM', 'IEC 62056-21', 'Modbus TCP']"
                    size="small"
                    fluid
                />
            </FormField>
            <FormField
                label="IP address"
                :input-id="`${uid}-ip`"
                :error="errors.ip_address"
            >
                <InputText
                    :id="`${uid}-ip`"
                    v-model="model.ip_address"
                    size="small"
                    fluid
                    class="erp-code"
                    :invalid="!!errors.ip_address"
                />
            </FormField>
            <FormField
                label="Poll every"
                :input-id="`${uid}-poll`"
                :error="errors.poll_interval_min"
                required
            >
                <Select
                    v-model="model.poll_interval_min"
                    :label-id="`${uid}-poll`"
                    :options="
                        [5, 15, 30, 60].map((m) => ({
                            value: m,
                            label: `${m} minutes`,
                        }))
                    "
                    option-label="label"
                    option-value="value"
                    size="small"
                    fluid
                    :invalid="!!errors.poll_interval_min"
                />
            </FormField>
        </fieldset>
    </RecordShell>
</template>
