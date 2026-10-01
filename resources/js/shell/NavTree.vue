<script setup lang="ts">
import Tree from 'primevue/tree';
import type { TreeNode } from 'primevue/treenode';
import { computed, ref } from 'vue';
import { useNetwork } from '@/composables/useLookups';
import {
    openHeadend,
    openModule,
    openRecord,
    workspace,
} from '@/composables/useWorkspace';
import { meterTypeIcons, modules } from '@/lib/modules';
import type { ModuleId } from '@/lib/modules';

/**
 * Navigator: the modules plus the full network hierarchy
 * (headend → gateway → meter). One click opens anything: a headend in its
 * own floating window, a gateway or meter in a tab. The filter box
 * narrows the tree by serial or name.
 */

type NodeData =
    | { kind: 'module'; module: ModuleId }
    | { kind: 'headend'; id: number; title: string }
    | { kind: 'gateway'; id: number; title: string }
    | { kind: 'meter'; id: number; title: string };

const { network, refresh } = useNetwork();
const expandedKeys = ref<Record<string, boolean>>({
    network: true,
    data: true,
    lists: true,
});

const moduleNode = (id: ModuleId): TreeNode => ({
    key: id,
    label: modules[id].title,
    icon: modules[id].icon,
    data: { kind: 'module', module: id } satisfies NodeData,
});

const nodes = computed<TreeNode[]>(() => [
    moduleNode('dashboard'),
    {
        key: 'network',
        label: 'Network',
        icon: 'pi pi-share-alt',
        selectable: false,
        children: (network.value ?? []).map((h) => ({
            key: `he-${h.id}`,
            label: `${h.code} ${h.name}`,
            icon: 'pi pi-server',
            data: {
                kind: 'headend',
                id: h.id,
                title: `${h.code} ${h.name}`,
            } satisfies NodeData,
            status: h.status,
            count: h.gateways.reduce((n, g) => n + g.meters.length, 0),
            children: h.gateways.map((g) => ({
                key: `gw-${g.id}`,
                label: `${g.serial} ${g.name}`,
                icon: 'pi pi-sitemap',
                data: {
                    kind: 'gateway',
                    id: g.id,
                    title: `Gateway ${g.serial}`,
                } satisfies NodeData,
                status: g.status,
                count: g.meters.length,
                children: g.meters.map((m) => ({
                    key: `m-${m.id}`,
                    label: `${m.serial} ${m.name}`,
                    icon: meterTypeIcons[m.type] ?? 'pi pi-bolt',
                    data: {
                        kind: 'meter',
                        id: m.id,
                        title: `Meter ${m.serial}`,
                    } satisfies NodeData,
                    status: m.status,
                })),
            })),
        })),
    },
    {
        key: 'data',
        label: 'Data',
        icon: 'pi pi-database',
        selectable: false,
        children: [moduleNode('readings'), moduleNode('events')],
    },
    {
        key: 'lists',
        label: 'Lists',
        icon: 'pi pi-list',
        selectable: false,
        children: [
            moduleNode('headends'),
            moduleNode('gateways'),
            moduleNode('meters'),
        ],
    },
    moduleNode('reports'),
]);

/** Highlight whatever the active panel shows. */
const selectionKeys = computed<Record<string, boolean>>(() => {
    const id = workspace.activeId ?? '';
    let key = id;

    if (id.startsWith('headend:')) {
        key = `he-${id.slice(8)}`;
    } else if (/^record:gateways:\d+$/.test(id)) {
        key = `gw-${id.split(':')[2]}`;
    } else if (/^record:meters:\d+$/.test(id)) {
        key = `m-${id.split(':')[2]}`;
    } else if (id.startsWith('grid:')) {
        key = id.split(':')[1];
    }

    return key ? { [key]: true } : {};
});

function onSelect(node: TreeNode): void {
    const data = node.data as NodeData | undefined;

    switch (data?.kind) {
        case 'module':
            openModule(data.module);
            break;
        case 'headend':
            openHeadend(data.id, data.title);
            break;
        case 'gateway':
            openRecord('gateways', data.id, data.title);
            break;
        case 'meter':
            openRecord('meters', data.id, data.title);
            break;
    }
}

function toggleFolder(node: TreeNode): void {
    if (!node.data && node.key) {
        expandedKeys.value = {
            ...expandedKeys.value,
            [node.key]: !expandedKeys.value[node.key],
        };
    }
}

function expandAll(): void {
    const keys: Record<string, boolean> = {
        network: true,
        data: true,
        lists: true,
    };
    (network.value ?? []).forEach((h) => {
        keys[`he-${h.id}`] = true;
        h.gateways.forEach((g) => (keys[`gw-${g.id}`] = true));
    });
    expandedKeys.value = keys;
}

function collapseAll(): void {
    expandedKeys.value = { network: true, data: true, lists: true };
}
</script>

<template>
    <nav class="erp-nav" aria-label="Navigator">
        <div class="erp-nav-head">
            <span>Navigator</span>
            <span class="erp-toolbar-fill" />
            <button
                type="button"
                class="erp-icon-btn"
                title="Expand all"
                aria-label="Expand all"
                @click="expandAll"
            >
                <i class="pi pi-angle-double-down" />
            </button>
            <button
                type="button"
                class="erp-icon-btn"
                title="Collapse all"
                aria-label="Collapse all"
                @click="collapseAll"
            >
                <i class="pi pi-angle-double-up" />
            </button>
            <button
                type="button"
                class="erp-icon-btn"
                title="Reload the network"
                aria-label="Reload the network"
                @click="refresh"
            >
                <i class="pi pi-refresh" />
            </button>
        </div>
        <Tree
            v-model:expanded-keys="expandedKeys"
            :selection-keys="selectionKeys"
            :value="nodes"
            selection-mode="single"
            filter
            filter-mode="lenient"
            filter-placeholder="Find headend, gateway or meter"
            class="erp-nav-tree"
            @node-select="onSelect"
            @node-unselect="onSelect"
            @node-click="toggleFolder"
        >
            <template #default="{ node }">
                <span class="erp-nav-label" :title="node.label">{{
                    node.label
                }}</span>
                <span v-if="node.count !== undefined" class="erp-nav-count">{{
                    node.count
                }}</span>
                <span
                    v-if="
                        node.status &&
                        node.status !== 'online' &&
                        node.status !== 'active'
                    "
                    :class="`erp-nav-dot is-${node.status}`"
                    :title="node.status.replace('_', ' ')"
                />
            </template>
        </Tree>
        <div class="erp-nav-foot">
            <span v-if="network"
                >{{ network.length }} headends ·
                {{
                    network.reduce((n, h) => n + h.gateways.length, 0)
                }}
                gateways ·
                {{
                    network.reduce(
                        (n, h) =>
                            n +
                            h.gateways.reduce((m, g) => m + g.meters.length, 0),
                        0,
                    )
                }}
                meters</span
            >
            <span v-else>Loading network…</span>
        </div>
    </nav>
</template>
