<script setup lang="ts">
import type { DockviewPanelApi } from 'dockview-vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed, nextTick, onMounted, ref, shallowRef, watch } from 'vue';
import EChart from '@/components/erp/EChart.vue';
import FormField from '@/components/erp/FormField.vue';
import RecordShell from '@/components/erp/RecordShell.vue';
import { useLookups, useNetwork } from '@/composables/useLookups';
import { useRecordForm } from '@/composables/useRecordForm';
import { useTheme } from '@/composables/useTheme';
import {
    onRecordChange,
    openHeadend,
    openRecord,
    openScopedGrid,
} from '@/composables/useWorkspace';
import type { RecordPanelParams } from '@/composables/useWorkspace';
import { api, buildQuery } from '@/lib/api';
import { focusInside } from '@/lib/dom';
import { fmt, isoDate, parseDate } from '@/lib/format';
import {
    dailyEnergyOption,
    loadProfileOption,
    voltageOption,
} from '@/modules/meter/meterCharts';
import type { Meter, MeterProfile, MeterStatus, MeterType } from '@/types/erp';

const props = defineProps<{
    panel: DockviewPanelApi;
    params: RecordPanelParams;
}>();

interface MeterModel {
    gateway_id: number | null;
    serial: string;
    name: string;
    account: string;
    type: MeterType;
    manufacturer: string;
    model: string;
    accuracy_class: string;
    ct_ratio: string;
    vt_ratio: string;
    tariff: string;
    has_generation: boolean;
    status: MeterStatus;
    installed_at: string | null;
}

const form = useRecordForm<'meters', MeterModel>({
    entity: 'meters',
    panel: props.panel,
    params: props.params,
    empty: () => ({
        gateway_id: null,
        serial: '',
        name: '',
        account: '',
        type: 'three_phase',
        manufacturer: 'PMT',
        model: 'MOL4000',
        accuracy_class: '1',
        ct_ratio: '',
        vt_ratio: '',
        tariff: '',
        has_generation: false,
        status: 'active',
        installed_at: isoDate(new Date()),
    }),
    fromRecord: (m: Meter) => ({
        gateway_id: m.gateway_id,
        serial: m.serial,
        name: m.name,
        account: m.account ?? '',
        type: m.type,
        manufacturer: m.manufacturer,
        model: m.model,
        accuracy_class: m.accuracy_class,
        ct_ratio: m.ct_ratio ?? '',
        vt_ratio: m.vt_ratio ?? '',
        tariff: m.tariff ?? '',
        has_generation: m.has_generation,
        status: m.status,
        installed_at: m.installed_at,
    }),
    toPayload: (m) => ({
        ...m,
        account: m.account || null,
        ct_ratio: m.ct_ratio || null,
        vt_ratio: m.vt_ratio || null,
        tariff: m.tariff || null,
    }),
});

const { model, errors, record } = form;
const { lookups } = useLookups();
const { refresh: refreshNetwork } = useNetwork();
const { chart } = useTheme();
const uid = props.panel.id.replace(/[^\w-]/g, '_');
const firstInput = ref<InstanceType<typeof InputText> | null>(null);

const needsCt = computed(
    () => model.value.type === 'ct' || model.value.type === 'ct_vt',
);
const needsVt = computed(() => model.value.type === 'ct_vt');

const gatewayOptions = computed(() =>
    (lookups.value?.gateways ?? []).map((g) => ({
        value: g.id,
        label: `${g.serial} · ${g.name}`,
        group: g.headend_code,
    })),
);
const statusOptions = computed(() =>
    (lookups.value?.meterStatuses ?? []).map((s) => ({
        value: s,
        label: fmt.titleCase(fmt.status(s)),
    })),
);

const installedAt = computed<Date | null>({
    get: () => parseDate(model.value.installed_at),
    set: (d) => {
        model.value.installed_at = d ? isoDate(d) : null;
    },
});

/* ---- load profile -------------------------------------------------- */

const ranges = [
    { label: '1 day', value: 1 },
    { label: '7 days', value: 7 },
    { label: '30 days', value: 30 },
    { label: '60 days', value: 60 },
];
const rangeDays = ref(7);
const profile = shallowRef<MeterProfile | null>(null);
const profileLoading = ref(false);

async function loadProfile(): Promise<void> {
    if (form.id.value === null) {
        return;
    }

    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (rangeDays.value - 1));
    profileLoading.value = true;

    try {
        profile.value = await api.get<MeterProfile>(
            `/api/meters/${form.id.value}/profile${buildQuery({ from: isoDate(from), to: isoDate(to) })}`,
        );
    } finally {
        profileLoading.value = false;
    }
}

watch(rangeDays, () => void loadProfile());

const phases = computed(
    () => record.value?.phases ?? (model.value.type === 'single_phase' ? 1 : 3),
);
const profileOption = computed(() =>
    profile.value
        ? loadProfileOption(
              profile.value,
              chart.value,
              Boolean(record.value?.has_generation),
          )
        : {},
);
const voltOption = computed(() =>
    profile.value
        ? voltageOption(profile.value, chart.value, phases.value)
        : {},
);
const dailyOption = computed(() =>
    profile.value ? dailyEnergyOption(profile.value, chart.value) : {},
);

/* ---- navigation ---------------------------------------------------- */

function scoped(module: 'readings' | 'events'): void {
    if (!record.value) {
        return;
    }

    openScopedGrid(
        module,
        `meter-${record.value.id}`,
        `${module === 'readings' ? 'Readings' : 'Events'} – ${record.value.serial}`,
        {
            meter_id: {
                filterType: 'number',
                type: 'equals',
                filter: record.value.id,
            },
        },
    );
}

onRecordChange('saved', (e) => {
    if (e.entity === 'meters' && e.id === form.id.value) {
        void refreshNetwork();
        void loadProfile();
    }
});

onMounted(async () => {
    await form.load();
    void loadProfile();

    if (props.params.focus) {
        await nextTick();
        focusInside(firstInput.value);
    }
});
</script>

<template>
    <RecordShell
        :title="
            form.isNew.value ? 'New meter' : `Meter ${record?.serial ?? ''}`
        "
        :subtitle="
            record
                ? `${record.name} · ${record.manufacturer} ${record.model} · ${record.type_label}`
                : 'Assign the meter to a gateway and set its type.'
        "
        icon="pi pi-bolt"
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
                fmt.status(record.status)
            }}</span>
            <span v-if="record?.has_generation" class="erp-tag erp-tag--online"
                >PV</span
            >
        </template>
        <template v-if="record" #actions>
            <Button
                label="Readings"
                icon="pi pi-chart-line"
                size="small"
                severity="secondary"
                text
                @click="scoped('readings')"
            />
            <Button
                label="Events"
                icon="pi pi-bell"
                size="small"
                severity="secondary"
                text
                @click="scoped('events')"
            />
        </template>

        <fieldset v-if="record" class="erp-fieldset erp-fieldset--stack">
            <legend>Load profile</legend>
            <div class="erp-profile-bar">
                <SelectButton
                    v-model="rangeDays"
                    :options="ranges"
                    option-label="label"
                    option-value="value"
                    :allow-empty="false"
                    size="small"
                    aria-label="Profile range"
                />
                <span v-if="profile" class="erp-muted">
                    {{ fmt.date(profile.range.from) }} –
                    {{ fmt.date(profile.range.to) }},
                    {{
                        profile.range.granularity === 'interval'
                            ? 'half-hourly'
                            : profile.range.granularity === 'hour'
                              ? 'hourly averages'
                              : 'daily averages'
                    }}
                </span>
            </div>
            <dl v-if="profile" class="erp-profile-stats">
                <div>
                    <dt>Imported</dt>
                    <dd>{{ fmt.energy(profile.summary.kwhImport) }}</dd>
                </div>
                <div v-if="profile.summary.kwhExport > 0">
                    <dt>Exported</dt>
                    <dd>{{ fmt.energy(profile.summary.kwhExport) }}</dd>
                </div>
                <div>
                    <dt>Max demand</dt>
                    <dd>{{ fmt.power(profile.summary.maxKva, 'kVA') }}</dd>
                    <small v-if="profile.summary.maxKvaAt">{{
                        fmt.dateTime(profile.summary.maxKvaAt)
                    }}</small>
                </div>
                <div>
                    <dt>Load factor</dt>
                    <dd>{{ fmt.pct(profile.summary.loadFactor) }}</dd>
                </div>
                <div>
                    <dt>Avg PF</dt>
                    <dd>{{ fmt.dec3(profile.summary.avgPf) }}</dd>
                </div>
                <div>
                    <dt>Voltage L1</dt>
                    <dd>
                        {{ profile.summary.vMin }}–{{ profile.summary.vMax }} V
                    </dd>
                </div>
                <div>
                    <dt>Complete</dt>
                    <dd
                        :class="{
                            'erp-num-alert': profile.summary.completeness < 99,
                        }"
                    >
                        {{ fmt.pct(profile.summary.completeness) }}
                    </dd>
                    <small
                        >{{ fmt.int(profile.summary.intervals) }} /
                        {{ fmt.int(profile.summary.expected)
                        }}<template v-if="profile.summary.estimated">
                            · {{ profile.summary.estimated }} est.</template
                        ></small
                    >
                </div>
            </dl>
            <div class="erp-profile-charts">
                <div class="erp-profile-chart erp-profile-chart--wide">
                    <h4>
                        Demand (average kW per
                        {{
                            profile?.range.granularity === 'day'
                                ? 'day'
                                : profile?.range.granularity === 'hour'
                                  ? 'hour'
                                  : 'half-hour'
                        }})
                    </h4>
                    <EChart
                        v-if="profile"
                        :option="profileOption"
                        :loading="profileLoading"
                        label="Load profile line chart in kilowatts"
                    />
                </div>
                <div class="erp-profile-chart">
                    <h4>Energy per day</h4>
                    <EChart
                        v-if="profile"
                        :option="dailyOption"
                        :loading="profileLoading"
                        label="Bar chart of energy imported per day"
                    />
                </div>
                <div class="erp-profile-chart">
                    <h4>
                        Voltage{{ phases > 1 ? ' per phase' : ''
                        }}{{ record.vt_ratio ? ' (VT secondary)' : '' }}
                    </h4>
                    <EChart
                        v-if="profile"
                        :option="voltOption"
                        :loading="profileLoading"
                        label="Line chart of voltage per phase"
                    />
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-fieldset">
            <legend>Meter</legend>
            <FormField
                label="Serial"
                :input-id="`${uid}-serial`"
                :error="errors.serial"
                required
                hint="6–20 digits, as printed on the meter."
            >
                <InputText
                    :id="`${uid}-serial`"
                    ref="firstInput"
                    v-model="model.serial"
                    size="small"
                    fluid
                    class="erp-code"
                    inputmode="numeric"
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
                label="Gateway"
                :input-id="`${uid}-gateway`"
                :error="errors.gateway_id"
                required
            >
                <Select
                    v-model="model.gateway_id"
                    :label-id="`${uid}-gateway`"
                    :options="gatewayOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Choose a gateway"
                    filter
                    size="small"
                    fluid
                    :invalid="!!errors.gateway_id"
                >
                    <template #option="{ option }">
                        <span
                            class="erp-code erp-muted"
                            style="width: 3.2rem"
                            >{{ option.group }}</span
                        >
                        {{ option.label }}
                    </template>
                </Select>
            </FormField>
            <FormField
                label="Account"
                :input-id="`${uid}-account`"
                :error="errors.account"
            >
                <InputText
                    :id="`${uid}-account`"
                    v-model="model.account"
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
            <FormField
                label="Installed"
                :input-id="`${uid}-installed`"
                :error="errors.installed_at"
            >
                <DatePicker
                    v-model="installedAt"
                    :input-id="`${uid}-installed`"
                    date-format="yy-mm-dd"
                    show-icon
                    icon-display="input"
                    size="small"
                    fluid
                    :invalid="!!errors.installed_at"
                />
            </FormField>
        </fieldset>

        <fieldset class="erp-fieldset">
            <legend>Hardware and tariff</legend>
            <FormField
                label="Type"
                :input-id="`${uid}-type`"
                :error="errors.type"
                required
            >
                <Select
                    v-model="model.type"
                    :label-id="`${uid}-type`"
                    :options="lookups?.meterTypes ?? []"
                    option-label="label"
                    option-value="value"
                    size="small"
                    fluid
                    :invalid="!!errors.type"
                />
            </FormField>
            <FormField
                label="Manufacturer"
                :input-id="`${uid}-make`"
                :error="errors.manufacturer"
                required
            >
                <Select
                    v-model="model.manufacturer"
                    :label-id="`${uid}-make`"
                    :options="lookups?.manufacturers ?? []"
                    editable
                    size="small"
                    fluid
                    :invalid="!!errors.manufacturer"
                />
            </FormField>
            <FormField
                label="Model"
                :input-id="`${uid}-model`"
                :error="errors.model"
                required
            >
                <InputText
                    :id="`${uid}-model`"
                    v-model="model.model"
                    size="small"
                    fluid
                    :invalid="!!errors.model"
                />
            </FormField>
            <FormField
                label="Accuracy class"
                :input-id="`${uid}-class`"
                :error="errors.accuracy_class"
                required
            >
                <Select
                    v-model="model.accuracy_class"
                    :label-id="`${uid}-class`"
                    :options="['0.2S', '0.5S', '1', '2']"
                    size="small"
                    fluid
                />
            </FormField>
            <FormField
                v-if="needsCt"
                label="CT ratio"
                :input-id="`${uid}-ct`"
                :error="errors.ct_ratio"
                required
                hint="Primary/secondary, e.g. 400/5."
            >
                <InputText
                    :id="`${uid}-ct`"
                    v-model="model.ct_ratio"
                    size="small"
                    fluid
                    :invalid="!!errors.ct_ratio"
                />
            </FormField>
            <FormField
                v-if="needsVt"
                label="VT ratio"
                :input-id="`${uid}-vt`"
                :error="errors.vt_ratio"
                required
                hint="e.g. 11000/110."
            >
                <InputText
                    :id="`${uid}-vt`"
                    v-model="model.vt_ratio"
                    size="small"
                    fluid
                    :invalid="!!errors.vt_ratio"
                />
            </FormField>
            <small v-if="!needsCt && errors.ct_ratio" class="erp-field-error">{{
                errors.ct_ratio
            }}</small>
            <FormField
                label="Tariff"
                :input-id="`${uid}-tariff`"
                :error="errors.tariff"
            >
                <Select
                    v-model="model.tariff"
                    :label-id="`${uid}-tariff`"
                    :options="lookups?.tariffs ?? []"
                    editable
                    show-clear
                    size="small"
                    fluid
                />
            </FormField>
            <FormField
                label="Generation"
                :input-id="`${uid}-pv`"
                hint="Rooftop PV or other generation behind the meter."
            >
                <ToggleSwitch
                    v-model="model.has_generation"
                    :input-id="`${uid}-pv`"
                />
            </FormField>
        </fieldset>

        <template v-if="record" #side>
            <dl class="erp-stats">
                <div>
                    <dt>Load now</dt>
                    <dd>
                        {{
                            record.kw_now !== null
                                ? fmt.power(record.kw_now)
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt>Today</dt>
                    <dd>{{ fmt.energy(record.kwh_today) }}</dd>
                </div>
                <div>
                    <dt>Last 30 days</dt>
                    <dd>{{ fmt.energy(record.kwh_30d) }}</dd>
                </div>
                <div>
                    <dt>Max kVA (30 d)</dt>
                    <dd>{{ fmt.power(record.max_kva_30d, 'kVA') }}</dd>
                </div>
                <div>
                    <dt>Last reading</dt>
                    <dd>{{ fmt.dateTime(record.last_reading_at) }}</dd>
                </div>
            </dl>
            <div class="erp-side-actions">
                <Button
                    :label="`Gateway ${record.gateway_serial}`"
                    icon="pi pi-sitemap"
                    size="small"
                    severity="secondary"
                    outlined
                    fluid
                    @click="
                        openRecord(
                            'gateways',
                            record.gateway_id,
                            `Gateway ${record.gateway_serial}`,
                        )
                    "
                />
                <Button
                    v-if="record.headend_id"
                    :label="`Headend ${record.headend_code}`"
                    icon="pi pi-server"
                    size="small"
                    severity="secondary"
                    outlined
                    fluid
                    @click="
                        openHeadend(
                            record.headend_id,
                            record.headend_code ?? undefined,
                        )
                    "
                />
            </div>
        </template>
    </RecordShell>
</template>
