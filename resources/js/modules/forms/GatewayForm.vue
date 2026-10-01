<script setup lang="ts">
import type { DockviewPanelApi } from 'dockview-vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, nextTick, onMounted, ref } from 'vue';
import FormField from '@/components/erp/FormField.vue';
import RecordShell from '@/components/erp/RecordShell.vue';
import { useLookups, useNetwork } from '@/composables/useLookups';
import { useRecordForm } from '@/composables/useRecordForm';
import {
    onRecordChange,
    openHeadend,
    openNew,
    openRecord,
} from '@/composables/useWorkspace';
import type { RecordPanelParams } from '@/composables/useWorkspace';
import { focusInside } from '@/lib/dom';
import { fmt } from '@/lib/format';
import { meterTypeIcons } from '@/lib/modules';
import type { DeviceStatus, Gateway } from '@/types/erp';

const props = defineProps<{
    panel: DockviewPanelApi;
    params: RecordPanelParams;
}>();

interface GatewayModel {
    headend_id: number | null;
    serial: string;
    name: string;
    comms: string;
    firmware: string;
    signal_dbm: number | null;
    status: DeviceStatus;
}

const form = useRecordForm<'gateways', GatewayModel>({
    entity: 'gateways',
    panel: props.panel,
    params: props.params,
    empty: () => ({
        headend_id: null,
        serial: '',
        name: '',
        comms: 'HD-PLC/RF',
        firmware: '4.3.2',
        signal_dbm: null,
        status: 'online',
    }),
    fromRecord: (g: Gateway) => ({
        headend_id: g.headend_id,
        serial: g.serial,
        name: g.name,
        comms: g.comms,
        firmware: g.firmware ?? '',
        signal_dbm: g.signal_dbm,
        status: g.status,
    }),
    toPayload: (m) => ({ ...m, firmware: m.firmware || null }),
});

const { model, errors, record } = form;
const { lookups } = useLookups();
const { refresh: refreshNetwork } = useNetwork();
const uid = props.panel.id.replace(/[^\w-]/g, '_');
const firstInput = ref<InstanceType<typeof InputText> | null>(null);

const headendOptions = computed(() =>
    (lookups.value?.headends ?? []).map((h) => ({
        value: h.id,
        label: `${h.code} ${h.name}`,
    })),
);
const statusOptions = computed(() =>
    (lookups.value?.deviceStatuses ?? []).map((s) => ({
        value: s,
        label: fmt.titleCase(s),
    })),
);

onRecordChange('saved', (e) => {
    if (e.entity === 'gateways') {
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
            form.isNew.value ? 'New gateway' : `Gateway ${record?.serial ?? ''}`
        "
        :subtitle="
            record
                ? `${record.name} · ${record.headend_code} ${record.headend_name}`
                : 'Gateways collect readings from their meters for a headend.'
        "
        icon="pi pi-sitemap"
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
        <template #badges>
            <span v-if="record" :class="`erp-tag erp-tag--${record.status}`">{{
                record.status
            }}</span>
        </template>

        <fieldset class="erp-fieldset">
            <legend>Gateway</legend>
            <FormField
                label="Serial"
                :input-id="`${uid}-serial`"
                :error="errors.serial"
                required
            >
                <InputText
                    :id="`${uid}-serial`"
                    ref="firstInput"
                    v-model="model.serial"
                    size="small"
                    fluid
                    class="erp-code"
                    :invalid="!!errors.serial"
                />
            </FormField>
            <FormField
                label="Name"
                :input-id="`${uid}-name`"
                :error="errors.name"
                required
            >
                <InputText
                    :id="`${uid}-name`"
                    v-model="model.name"
                    size="small"
                    fluid
                    :invalid="!!errors.name"
                />
            </FormField>
            <FormField
                label="Headend"
                :input-id="`${uid}-headend`"
                :error="errors.headend_id"
                required
            >
                <Select
                    v-model="model.headend_id"
                    :label-id="`${uid}-headend`"
                    :options="headendOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Choose a headend"
                    size="small"
                    fluid
                    :invalid="!!errors.headend_id"
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
            <FormField
                label="Comms"
                :input-id="`${uid}-comms`"
                :error="errors.comms"
                required
            >
                <Select
                    v-model="model.comms"
                    :label-id="`${uid}-comms`"
                    :options="lookups?.comms ?? []"
                    size="small"
                    fluid
                    :invalid="!!errors.comms"
                />
            </FormField>
            <FormField
                label="Firmware"
                :input-id="`${uid}-fw`"
                :error="errors.firmware"
            >
                <InputText
                    :id="`${uid}-fw`"
                    v-model="model.firmware"
                    size="small"
                    fluid
                    :invalid="!!errors.firmware"
                />
            </FormField>
            <FormField
                label="Signal"
                :input-id="`${uid}-signal`"
                :error="errors.signal_dbm"
                hint="Received signal strength."
            >
                <InputNumber
                    v-model="model.signal_dbm"
                    :input-id="`${uid}-signal`"
                    :min="-130"
                    :max="0"
                    suffix=" dBm"
                    size="small"
                    fluid
                    :invalid="!!errors.signal_dbm"
                />
            </FormField>
        </fieldset>

        <fieldset v-if="record" class="erp-fieldset erp-fieldset--stack">
            <legend>
                Meters on this gateway ({{ record.meters?.length ?? 0 }})
            </legend>
            <table class="erp-gw-table">
                <thead>
                    <tr>
                        <th scope="col">Meter</th>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Load now</th>
                        <th scope="col" class="num">Today</th>
                        <th scope="col">Last reading</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="meter in record.meters"
                        :key="meter.id"
                        tabindex="0"
                        @click="
                            openRecord(
                                'meters',
                                meter.id,
                                `Meter ${meter.serial}`,
                            )
                        "
                        @keydown.enter="
                            openRecord(
                                'meters',
                                meter.id,
                                `Meter ${meter.serial}`,
                            )
                        "
                    >
                        <td class="erp-code">{{ meter.serial }}</td>
                        <td>{{ meter.name }}</td>
                        <td>
                            <i
                                :class="meterTypeIcons[meter.type]"
                                class="erp-muted"
                                aria-hidden="true"
                            />
                            {{ meter.type_label }}
                        </td>
                        <td>
                            <span :class="`erp-tag erp-tag--${meter.status}`">{{
                                fmt.status(meter.status)
                            }}</span>
                        </td>
                        <td class="num">
                            {{
                                meter.kw_now !== null
                                    ? fmt.power(meter.kw_now)
                                    : '—'
                            }}
                        </td>
                        <td class="num">{{ fmt.energy(meter.kwh_today) }}</td>
                        <td>{{ fmt.dateTime(meter.last_reading_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <div>
                <Button
                    label="Add meter"
                    icon="pi pi-plus"
                    size="small"
                    severity="secondary"
                    outlined
                    @click="openNew('meters', { gateway_id: record.id })"
                />
            </div>
        </fieldset>

        <template v-if="record" #side>
            <dl class="erp-stats">
                <div>
                    <dt>Meters</dt>
                    <dd>{{ record.meters_count }}</dd>
                </div>
                <div>
                    <dt>Last seen</dt>
                    <dd>{{ fmt.dateTime(record.last_seen_at) }}</dd>
                </div>
            </dl>
            <div class="erp-side-actions">
                <Button
                    :label="`Headend ${record.headend_code}`"
                    icon="pi pi-server"
                    size="small"
                    severity="secondary"
                    outlined
                    fluid
                    @click="
                        openHeadend(
                            record.headend_id,
                            `${record.headend_code} ${record.headend_name}`,
                        )
                    "
                />
            </div>
        </template>
    </RecordShell>
</template>
