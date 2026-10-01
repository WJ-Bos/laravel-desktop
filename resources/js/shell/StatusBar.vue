<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { layoutStatus } from '@/composables/useLayoutPersistence';
import { hasUnsavedChanges, workspace } from '@/composables/useWorkspace';
import { api, connection } from '@/lib/api';
import { fmt } from '@/lib/format';

const page = usePage();
const now = ref(new Date());
let clock: ReturnType<typeof setInterval> | null = null;
let ping: ReturnType<typeof setInterval> | null = null;

async function heartbeat(): Promise<void> {
    try {
        await api.get('/api/ping');
    } catch {
        // connection.online is updated by the api client
    }
}

function onOnline(): void {
    void heartbeat();
}

function onOffline(): void {
    connection.online = false;
}

onMounted(() => {
    clock = setInterval(() => (now.value = new Date()), 15_000);
    ping = setInterval(heartbeat, 30_000);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    void heartbeat();
});

onUnmounted(() => {
    if (clock) {
        clearInterval(clock);
    }

    if (ping) {
        clearInterval(ping);
    }

    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});

const gridStats = computed(() =>
    workspace.activeId ? workspace.gridStats[workspace.activeId] : undefined,
);

const syncLabel = computed(() => {
    const last = connection.lastSync;

    if (!last) {
        return 'Not synced yet';
    }

    const seconds = Math.round((now.value.getTime() - last.getTime()) / 1000);

    return seconds < 60
        ? `Synced ${fmt.time(last)}`
        : `Synced ${fmt.time(last)} (${Math.round(seconds / 60)} min ago)`;
});

const connectionState = computed(() => {
    if (!connection.online) {
        return {
            label: 'Offline',
            cls: 'is-offline',
            title: connection.lastError ?? 'The server is not reachable.',
        };
    }

    if (connection.pending > 0) {
        return {
            label: 'Syncing',
            cls: 'is-busy',
            title: `${connection.pending} request(s) in flight`,
        };
    }

    return {
        label: 'Connected',
        cls: 'is-online',
        title: 'Connected to the server',
    };
});

const unsaved = computed(() => Object.keys(workspace.dirty).length);
</script>

<template>
    <footer class="erp-statusbar" role="contentinfo">
        <span
            class="erp-status-cell"
            :title="page.props.auth.user?.email as string"
        >
            <i class="pi pi-user" aria-hidden="true" />
            {{ page.props.auth.user?.name }}
        </span>
        <span class="erp-status-cell erp-status-active">
            <i class="pi pi-window-maximize" aria-hidden="true" />
            {{ workspace.activeTitle || 'No active panel' }}
        </span>
        <span v-if="gridStats" class="erp-status-cell" aria-live="polite">
            <i class="pi pi-list" aria-hidden="true" />
            {{ fmt.int(Math.min(gridStats.loaded, gridStats.total)) }} /
            {{ fmt.int(gridStats.total) }} records
            <template v-if="gridStats.selected"
                >· {{ gridStats.selected }} selected</template
            >
        </span>
        <span
            v-if="unsaved && hasUnsavedChanges()"
            class="erp-status-cell erp-status-warn"
        >
            <i class="pi pi-pencil" aria-hidden="true" /> {{ unsaved }} unsaved
        </span>
        <span class="erp-status-fill" />
        <span v-if="layoutStatus.saving" class="erp-status-cell erp-muted"
            >Saving layout…</span
        >
        <span
            v-else-if="layoutStatus.error"
            class="erp-status-cell erp-status-warn"
            :title="layoutStatus.error"
            >Layout not saved</span
        >
        <span class="erp-status-cell">
            <i class="pi pi-clock" aria-hidden="true" /> {{ syncLabel }}
        </span>
        <span
            class="erp-status-cell erp-conn"
            :class="connectionState.cls"
            :title="connectionState.title"
            role="status"
        >
            <span class="erp-conn-dot" aria-hidden="true" />
            {{ connectionState.label }}
        </span>
    </footer>
</template>
