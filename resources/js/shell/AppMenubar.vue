<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Menubar from 'primevue/menubar';
import type { MenuItem } from 'primevue/menuitem';
import { computed } from 'vue';
import { useNetwork } from '@/composables/useLookups';
import { comboLabel } from '@/composables/useShortcuts';
import { useTheme } from '@/composables/useTheme';
import {
    activateById,
    closeActive,
    closeAll,
    dockActive,
    dockApi,
    floatActive,
    openHeadend,
    openModule,
    openNew,
    toggleMaximizeActive,
    triggerExport,
    triggerNew,
    triggerRefresh,
    triggerSave,
    workspace,
} from '@/composables/useWorkspace';
import { modules } from '@/lib/modules';
import type { ModuleId } from '@/lib/modules';

const emit = defineEmits<{ resetLayout: [] }>();

const { isDark, toggle } = useTheme();
const { network } = useNetwork();
const page = usePage();
const userName = computed(() => page.props.auth.user?.name ?? '');

type ErpMenuItem = MenuItem & { shortcut?: string };

const moduleItem = (id: ModuleId, shortcut?: string): ErpMenuItem => ({
    label: modules[id].title,
    icon: modules[id].icon,
    shortcut,
    command: () => openModule(id),
});

function panelLabel(id: string): string {
    return dockApi()?.getPanel(id)?.title ?? id;
}

const items = computed<ErpMenuItem[]>(() => [
    {
        label: 'File',
        items: [
            {
                label: 'New record',
                icon: 'pi pi-plus',
                shortcut: 'mod+n',
                command: () => triggerNew(),
            },
            {
                label: 'New…',
                icon: 'pi pi-file-plus',
                items: [
                    {
                        label: 'Meter',
                        icon: modules.meters.icon,
                        command: () => openNew('meters'),
                    },
                    {
                        label: 'Gateway',
                        icon: modules.gateways.icon,
                        command: () => openNew('gateways'),
                    },
                    {
                        label: 'Headend',
                        icon: modules.headends.icon,
                        command: () => openNew('headends'),
                    },
                ],
            },
            {
                label: 'Save',
                icon: 'pi pi-save',
                shortcut: 'mod+s',
                command: () => triggerSave(),
            },
            { separator: true },
            {
                label: 'Export current view',
                icon: 'pi pi-download',
                command: () => triggerExport(),
            },
            { separator: true },
            {
                label: 'Profile settings',
                icon: 'pi pi-cog',
                command: () => router.visit('/settings/profile'),
            },
            {
                label: 'Sign out',
                icon: 'pi pi-sign-out',
                command: () => router.post('/logout'),
            },
        ],
    },
    {
        label: 'View',
        items: [
            {
                label: 'Find…',
                icon: 'pi pi-search',
                shortcut: 'mod+k',
                command: () => (workspace.paletteOpen = true),
            },
            {
                label: workspace.sidebarOpen
                    ? 'Hide navigator'
                    : 'Show navigator',
                icon: 'pi pi-bars',
                shortcut: 'mod+b',
                command: () => (workspace.sidebarOpen = !workspace.sidebarOpen),
            },
            {
                label: isDark.value ? 'Light theme' : 'Dark theme',
                icon: isDark.value ? 'pi pi-sun' : 'pi pi-moon',
                shortcut: 'mod+shift+l',
                command: toggle,
            },
            { separator: true },
            {
                label: 'Refresh',
                icon: 'pi pi-refresh',
                shortcut: 'alt+r',
                command: () => triggerRefresh(),
            },
        ],
    },
    {
        label: 'Data',
        items: [
            moduleItem('dashboard', 'alt+1'),
            { separator: true },
            moduleItem('meters', 'alt+2'),
            moduleItem('readings', 'alt+3'),
            moduleItem('events', 'alt+4'),
            { separator: true },
            moduleItem('gateways', 'alt+5'),
            moduleItem('headends', 'alt+6'),
            {
                label: 'Open headend',
                icon: 'pi pi-window-maximize',
                items: (network.value ?? []).map((h) => ({
                    label: `${h.code} ${h.name}`,
                    icon:
                        h.status === 'online'
                            ? 'pi pi-server'
                            : 'pi pi-exclamation-triangle',
                    command: () => openHeadend(h.id, `${h.code} ${h.name}`),
                })),
            },
            { separator: true },
            moduleItem('reports', 'alt+7'),
        ],
    },
    {
        label: 'Window',
        items: [
            {
                label: 'Close panel',
                icon: 'pi pi-times',
                shortcut: 'mod+w',
                command: closeActive,
                disabled: !workspace.activeId,
            },
            {
                label: 'Close all panels',
                icon: 'pi pi-times-circle',
                command: () => closeAll(),
                disabled: workspace.panelIds.length === 0,
            },
            { separator: true },
            {
                label: 'Float panel',
                icon: 'pi pi-external-link',
                command: floatActive,
                disabled: !workspace.activeId,
            },
            {
                label: 'Dock panel',
                icon: 'pi pi-sign-in',
                command: dockActive,
                disabled: !workspace.activeId,
            },
            {
                label: 'Maximize / restore',
                icon: 'pi pi-window-maximize',
                shortcut: 'alt+m',
                command: toggleMaximizeActive,
                disabled: !workspace.activeId,
            },
            { separator: true },
            {
                label: 'Reset layout',
                icon: 'pi pi-replay',
                command: () => emit('resetLayout'),
            },
            ...(workspace.panelIds.length
                ? [
                      { separator: true },
                      ...workspace.panelIds.map((id) => ({
                          label:
                              id === workspace.activeId
                                  ? `${panelLabel(id)}  ✓`
                                  : panelLabel(id),
                          icon: 'pi pi-window-maximize',
                          command: () => activateById(id),
                      })),
                  ]
                : []),
        ],
    },
    {
        label: 'Help',
        items: [
            {
                label: 'Keyboard shortcuts',
                icon: 'pi pi-key',
                shortcut: 'f1',
                command: () => (workspace.shortcutsOpen = true),
            },
            {
                label: 'About MOL4',
                icon: 'pi pi-info-circle',
                command: () => (workspace.aboutOpen = true),
            },
        ],
    },
]);
</script>

<template>
    <header class="erp-menubar">
        <div class="erp-brand" aria-label="MOL4">
            <span class="erp-brand-mark" aria-hidden="true"
                ><i class="pi pi-bolt"
            /></span>
            <span class="erp-brand-name">MOL4</span>
        </div>
        <Menubar :model="items" class="erp-menubar-menu">
            <template #item="{ item, props: itemProps, hasSubmenu, root }">
                <a
                    v-bind="itemProps.action"
                    class="erp-menu-item"
                    :class="{ 'erp-menu-item--root': root }"
                >
                    <i
                        v-if="item.icon && !root"
                        :class="item.icon"
                        class="erp-menu-icon"
                        aria-hidden="true"
                    />
                    <span class="erp-menu-label">{{ item.label }}</span>
                    <kbd
                        v-if="(item as ErpMenuItem).shortcut"
                        class="erp-menu-kbd"
                        >{{ comboLabel((item as ErpMenuItem).shortcut!) }}</kbd
                    >
                    <i
                        v-if="hasSubmenu && !root"
                        class="pi pi-angle-right erp-menu-sub"
                        aria-hidden="true"
                    />
                </a>
            </template>
        </Menubar>
        <div class="erp-menubar-end">
            <button
                type="button"
                class="erp-search-trigger"
                @click="workspace.paletteOpen = true"
            >
                <i class="pi pi-search" aria-hidden="true" />
                <span>Find meter, gateway or headend</span>
                <kbd>{{ comboLabel('mod+k') }}</kbd>
            </button>
            <Button
                v-tooltip.bottom="
                    isDark ? 'Switch to light theme' : 'Switch to dark theme'
                "
                :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
                size="small"
                severity="secondary"
                text
                :aria-label="
                    isDark ? 'Switch to light theme' : 'Switch to dark theme'
                "
                @click="toggle"
            />
            <span
                class="erp-user"
                :title="page.props.auth.user?.email as string"
                ><i class="pi pi-user" aria-hidden="true" />
                {{ userName }}</span
            >
        </div>
    </header>
</template>
