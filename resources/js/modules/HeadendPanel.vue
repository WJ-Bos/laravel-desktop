<script setup lang="ts">
import type { DockviewApi, DockviewPanelApi } from 'dockview-vue';
import Button from 'primevue/button';
import ProgressBar from 'primevue/progressbar';
import { computed, onMounted, onUnmounted, ref, shallowRef } from 'vue';
import {
    onRecordChange,
    openNew,
    openRecord,
    openScopedGrid,
    usePanelActions,
} from '@/composables/useWorkspace';
import type { HeadendPanelParams } from '@/composables/useWorkspace';
import { api } from '@/lib/api';
import { fmt } from '@/lib/format';
import { meterTypeIcons } from '@/lib/modules';
import type { Gateway, Headend, Meter } from '@/types/erp';

/**
 * A headend's own window (opened floating): the headend's details, then
 * every gateway it polls with the meters behind each one and their live
 * figures. Click a meter or gateway to open it.
 */
const props = defineProps<{
    params: {
        params: HeadendPanelParams;
        api: DockviewPanelApi;
        containerApi: DockviewApi;
    };
}>();

const headendId = props.params.params.id;
const headend = shallowRef<Headend | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const collapsed = ref<Record<number, boolean>>({});

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await api.get<{ data: Headend }>(
            `/api/headends/${headendId}`,
        );
        headend.value = data;
        props.params.api.setTitle(`${data.code} ${data.name}`);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

const meters = computed<Meter[]>(() =>
    (headend.value?.gateways ?? []).flatMap((g) => g.meters ?? []),
);
const kwNow = computed(() =>
    meters.value.reduce((sum, m) => sum + (m.kw_now ?? 0), 0),
);
const kwhToday = computed(() =>
    meters.value.reduce((sum, m) => sum + (m.kwh_today ?? 0), 0),
);

function isStale(value: string | null): boolean {
    return !value || Date.now() - new Date(value).getTime() > 2 * 3600_000;
}

function gatewayKw(gateway: Gateway): number {
    return (gateway.meters ?? []).reduce((sum, m) => sum + (m.kw_now ?? 0), 0);
}

function openMeter(meter: Meter): void {
    openRecord('meters', meter.id, `Meter ${meter.serial}`);
}

function openGateway(gateway: Gateway): void {
    openRecord('gateways', gateway.id, `Gateway ${gateway.serial}`);
}

function scoped(module: 'readings' | 'events' | 'meters'): void {
    if (!headend.value) {
        return;
    }

    const label = { readings: 'Readings', events: 'Events', meters: 'Meters' }[
        module
    ];
    openScopedGrid(
        module,
        `headend-${headendId}`,
        `${label} – ${headend.value.code}`,
        {
            headend_id: {
                filterType: 'number',
                type: 'equals',
                filter: headendId,
            },
        },
    );
}

function toggle(gatewayId: number): void {
    collapsed.value = {
        ...collapsed.value,
        [gatewayId]: !collapsed.value[gatewayId],
    };
}

usePanelActions(props.params.api.id, { onRefresh: () => void load() });
onRecordChange('saved', (e) => {
    if (['headends', 'gateways', 'meters'].includes(e.entity)) {
        void load();
    }
});

let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    void load();
    timer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            void load();
        }
    }, 60_000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>
    <div class="erp-headend">
        <header class="erp-record-head">
            <i class="pi pi-server erp-record-icon" aria-hidden="true" />
            <div class="erp-record-titles">
                <h2 class="erp-record-title">
                    {{
                        headend ? `${headend.code} ${headend.name}` : 'Headend'
                    }}
                </h2>
                <span v-if="headend" class="erp-record-subtitle">
                    {{ headend.site }}, {{ headend.city }} ·
                    {{ headend.protocol }} · {{ headend.ip_address }} · polls
                    every {{ headend.poll_interval_min }} min
                </span>
            </div>
            <span
                v-if="headend"
                :class="`erp-tag erp-tag--${headend.status}`"
                >{{ headend.status }}</span
            >
            <span class="erp-toolbar-fill" />
            <Button
                label="Meters"
                icon="pi pi-bolt"
                size="small"
                severity="secondary"
                text
                @click="scoped('meters')"
            />
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
            <Button
                v-tooltip.bottom="'Edit headend settings'"
                icon="pi pi-pencil"
                size="small"
                severity="secondary"
                text
                aria-label="Edit headend"
                @click="
                    headend &&
                    openRecord(
                        'headends',
                        headend.id,
                        `${headend.code} ${headend.name}`,
                        { focus: true },
                    )
                "
            />
            <Button
                v-tooltip.bottom="'Refresh'"
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                text
                aria-label="Refresh"
                :loading="loading"
                @click="load"
            />
        </header>
        <ProgressBar
            v-if="loading && !headend"
            mode="indeterminate"
            class="erp-record-progress"
        />

        <div v-if="error" class="erp-inline-error" role="alert">
            <i class="pi pi-exclamation-triangle" /> Couldn't load this headend:
            {{ error }}
            <Button label="Retry" size="small" text @click="load" />
        </div>

        <div v-if="headend" class="erp-headend-body">
            <dl class="erp-headend-stats">
                <div>
                    <dt>Gateways online</dt>
                    <dd
                        :class="{
                            'erp-num-alert':
                                (headend.gateways_online ?? 0) <
                                (headend.gateways_count ?? 0),
                        }"
                    >
                        {{ headend.gateways_online }} /
                        {{ headend.gateways_count }}
                    </dd>
                </div>
                <div>
                    <dt>Meters reporting</dt>
                    <dd
                        :class="{
                            'erp-num-alert':
                                (headend.meters_reporting ?? 0) <
                                (headend.meters_count ?? 0),
                        }"
                    >
                        {{ headend.meters_reporting }} /
                        {{ headend.meters_count }}
                    </dd>
                </div>
                <div>
                    <dt>Load now</dt>
                    <dd>{{ fmt.power(kwNow) }}</dd>
                </div>
                <div>
                    <dt>Energy today</dt>
                    <dd>{{ fmt.energy(kwhToday) }}</dd>
                </div>
                <div>
                    <dt>Last poll</dt>
                    <dd>{{ fmt.dateTime(headend.last_poll_at) }}</dd>
                </div>
            </dl>

            <section
                v-for="gateway in headend.gateways"
                :key="gateway.id"
                class="erp-gw"
                :aria-label="`Gateway ${gateway.serial}`"
            >
                <header class="erp-gw-head">
                    <button
                        type="button"
                        class="erp-icon-btn"
                        :aria-expanded="!collapsed[gateway.id]"
                        :aria-label="
                            collapsed[gateway.id]
                                ? 'Expand gateway'
                                : 'Collapse gateway'
                        "
                        @click="toggle(gateway.id)"
                    >
                        <i
                            :class="
                                collapsed[gateway.id]
                                    ? 'pi pi-chevron-right'
                                    : 'pi pi-chevron-down'
                            "
                            aria-hidden="true"
                        />
                    </button>
                    <i class="pi pi-sitemap erp-gw-icon" aria-hidden="true" />
                    <button
                        type="button"
                        class="erp-link-btn erp-gw-serial"
                        @click="openGateway(gateway)"
                    >
                        {{ gateway.serial }}
                    </button>
                    <span class="erp-gw-name">{{ gateway.name }}</span>
                    <span :class="`erp-tag erp-tag--${gateway.status}`">{{
                        gateway.status
                    }}</span>
                    <span class="erp-toolbar-fill" />
                    <span class="erp-gw-meta"
                        >{{ gateway.comms }} · fw {{ gateway.firmware }} ·
                        {{ gateway.signal_dbm }} dBm</span
                    >
                    <span
                        class="erp-gw-meta"
                        :class="{
                            'erp-num-alert': isStale(gateway.last_seen_at),
                        }"
                        >seen {{ fmt.dateTime(gateway.last_seen_at) }}</span
                    >
                    <span class="erp-gw-meta erp-gw-load">{{
                        fmt.power(gatewayKw(gateway))
                    }}</span>
                    <Button
                        v-tooltip.bottom="'Add a meter to this gateway'"
                        icon="pi pi-plus"
                        size="small"
                        severity="secondary"
                        text
                        aria-label="Add meter"
                        @click="openNew('meters', { gateway_id: gateway.id })"
                    />
                </header>
                <table v-if="!collapsed[gateway.id]" class="erp-gw-table">
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
                            v-for="meter in gateway.meters"
                            :key="meter.id"
                            tabindex="0"
                            :title="`Open meter ${meter.serial}`"
                            @click="openMeter(meter)"
                            @keydown.enter="openMeter(meter)"
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
                                <span
                                    :class="`erp-tag erp-tag--${meter.status}`"
                                    >{{ fmt.status(meter.status) }}</span
                                >
                            </td>
                            <td class="num">
                                {{
                                    meter.kw_now !== null
                                        ? fmt.power(meter.kw_now)
                                        : '—'
                                }}
                            </td>
                            <td class="num">
                                {{ fmt.energy(meter.kwh_today) }}
                            </td>
                            <td
                                :class="{
                                    'erp-num-alert': isStale(
                                        meter.last_reading_at,
                                    ),
                                }"
                            >
                                {{ fmt.dateTime(meter.last_reading_at) }}
                            </td>
                        </tr>
                        <tr v-if="!gateway.meters?.length">
                            <td colspan="7" class="erp-muted">
                                No meters on this gateway yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>
