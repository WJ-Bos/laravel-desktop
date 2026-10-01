<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { DockviewVue } from 'dockview-vue';
import type {
    ContextMenuItem,
    DockviewApi,
    DockviewReadyEvent,
    GetTabContextMenuItemsParams,
    SerializedDockview,
} from 'dockview-vue';
import 'dockview-vue/dist/styles/dockview.css';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { onMounted, onUnmounted } from 'vue';
import '../../css/desktop.css';
import {
    seedLayouts,
    useLayoutPersistence,
} from '@/composables/useLayoutPersistence';
import { useShortcuts } from '@/composables/useShortcuts';
import { useTheme } from '@/composables/useTheme';
import {
    attachDock,
    closeActive,
    closeAll,
    hasUnsavedChanges,
    openModule,
    requestClose,
    setConfirm,
    toggleMaximizeActive,
    triggerNew,
    triggerRefresh,
    triggerSave,
    workspace,
} from '@/composables/useWorkspace';
import { dockComponent } from '@/lib/dom';
import type { ModuleId } from '@/lib/modules';
import DashboardPanel from '@/modules/dashboard/DashboardPanel.vue';
import GridPanel from '@/modules/GridPanel.vue';
import HeadendPanel from '@/modules/HeadendPanel.vue';
import RecordPanel from '@/modules/RecordPanel.vue';
import ReportsPanel from '@/modules/reports/ReportsPanel.vue';
import AppMenubar from '@/shell/AppMenubar.vue';
import CommandPalette from '@/shell/CommandPalette.vue';
import DockTab from '@/shell/DockTab.vue';
import GroupActions from '@/shell/GroupActions.vue';
import HelpDialogs from '@/shell/HelpDialogs.vue';
import NavTree from '@/shell/NavTree.vue';
import StatusBar from '@/shell/StatusBar.vue';
import Watermark from '@/shell/Watermark.vue';

const props = defineProps<{ layouts: Record<string, unknown> }>();

// Saved layouts arrive with the page, so every panel can restore synchronously.
seedLayouts(props.layouts);

const { dockviewTheme, toggle: toggleTheme } = useTheme();
const confirm = useConfirm();
const toast = useToast();
const layout = useLayoutPersistence<SerializedDockview>('desktop:mol4', 1000);

const panelComponents = {
    dashboard: dockComponent(DashboardPanel),
    grid: dockComponent(GridPanel),
    headend: dockComponent(HeadendPanel),
    record: dockComponent(RecordPanel),
    reports: dockComponent(ReportsPanel),
};
const tabComponent = dockComponent(DockTab);
const groupActionsComponent = dockComponent(GroupActions);
const watermarkComponent = dockComponent(Watermark);

let dock: DockviewApi | null = null;

setConfirm(
    ({ header, message, acceptLabel, danger }) =>
        new Promise<boolean>((resolve) => {
            confirm.require({
                header,
                message,
                icon: danger
                    ? 'pi pi-exclamation-triangle'
                    : 'pi pi-question-circle',
                rejectProps: {
                    label: 'Cancel',
                    severity: 'secondary',
                    outlined: true,
                    size: 'small',
                },
                acceptProps: {
                    label: acceptLabel ?? 'OK',
                    severity: danger ? 'danger' : 'primary',
                    size: 'small',
                },
                accept: () => resolve(true),
                reject: () => resolve(false),
                onHide: () => resolve(false),
            });
        }),
);

function buildDefaultLayout(api: DockviewApi): void {
    api.addPanel({
        id: 'dashboard',
        component: 'dashboard',
        title: 'Dashboard',
    });
    api.addPanel({
        id: 'grid:meters',
        component: 'grid',
        title: 'Meters',
        params: { module: 'meters' },
        position: { referencePanel: 'dashboard', direction: 'within' },
        inactive: true,
    });
    api.getPanel('dashboard')?.api.setActive();
}

function onReady(event: DockviewReadyEvent): void {
    dock = event.api;
    attachDock(event.api);

    const saved = layout.initial();
    let restored = false;

    if (saved) {
        try {
            event.api.fromJSON(saved);
            restored = event.api.panels.length > 0;
        } catch (error) {
            console.warn(
                'Saved layout could not be restored; using the default.',
                error,
            );
            event.api.clear();
        }
    }

    if (!restored) {
        buildDefaultLayout(event.api);
    }

    event.api.onDidLayoutChange(() => layout.save(event.api.toJSON()));
}

async function resetLayout(): Promise<void> {
    if (!dock) {
        return;
    }

    if (hasUnsavedChanges()) {
        await closeAll();

        if (hasUnsavedChanges()) {
            return;
        }
    }

    const api = dock;
    await layout.suspend(async () => {
        await layout.reset();
        api.clear();
        buildDefaultLayout(api);
    });
    toast.add({
        severity: 'info',
        summary: 'Layout reset',
        detail: 'The workspace is back to its default arrangement.',
        life: 2500,
    });
}

function tabMenu(params: GetTabContextMenuItemsParams): ContextMenuItem[] {
    return [
        { label: 'Close', action: () => void requestClose(params.panel.id) },
        { label: 'Close others', action: () => void closeAll(params.panel.id) },
        'separator',
        'maximize',
        'float',
    ];
}

const moduleKeys: ModuleId[] = [
    'dashboard',
    'meters',
    'readings',
    'events',
    'gateways',
    'headends',
    'reports',
];

useShortcuts({
    'mod+k': () => {
        workspace.paletteOpen = !workspace.paletteOpen;
    },
    'mod+n': () => triggerNew(),
    'alt+n': () => triggerNew(),
    'mod+s': { handler: () => triggerSave() || undefined, allowInInputs: true },
    'mod+w': () => closeActive(),
    'alt+w': () => closeActive(),
    'mod+b': () => {
        workspace.sidebarOpen = !workspace.sidebarOpen;
    },
    'mod+shift+l': () => toggleTheme(),
    'alt+r': () => triggerRefresh(),
    'alt+m': () => toggleMaximizeActive(),
    f1: () => {
        workspace.shortcutsOpen = true;
    },
    ...Object.fromEntries(
        moduleKeys.map((id, i) => [`alt+${i + 1}`, () => openModule(id)]),
    ),
});

function beforeUnload(event: BeforeUnloadEvent): void {
    void layout.flush();

    if (hasUnsavedChanges()) {
        event.preventDefault();
    }
}

onMounted(() => {
    document.documentElement.classList.add('erp-desktop');
    window.addEventListener('beforeunload', beforeUnload);
});

onUnmounted(() => {
    document.documentElement.classList.remove('erp-desktop');
    window.removeEventListener('beforeunload', beforeUnload);
});
</script>

<template>
    <Head title="Desktop" />
    <div
        class="erp-shell"
        :class="{ 'erp-shell--nav-hidden': !workspace.sidebarOpen }"
    >
        <AppMenubar @reset-layout="resetLayout" />
        <div class="erp-main">
            <aside v-show="workspace.sidebarOpen" class="erp-sidebar">
                <NavTree />
            </aside>
            <main class="erp-dock-host">
                <DockviewVue
                    class="erp-dock-root"
                    :theme="dockviewTheme"
                    :components="panelComponents"
                    :default-tab-component="tabComponent"
                    :right-header-actions-component="groupActionsComponent"
                    :watermark-component="watermarkComponent"
                    :get-tab-context-menu-items="tabMenu"
                    :floating-group-bounds="'boundedWithinViewport'"
                    @ready="onReady"
                />
            </main>
        </div>
        <StatusBar />
    </div>
    <CommandPalette @reset-layout="resetLayout" />
    <HelpDialogs />
    <Toast position="bottom-right" />
    <ConfirmDialog class="erp-dialog" />
</template>
