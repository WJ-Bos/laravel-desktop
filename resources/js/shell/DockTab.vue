<script setup lang="ts">
import type { DockviewApi, DockviewPanelApi } from 'dockview-vue';
import { computed, onUnmounted, ref } from 'vue';
import { requestClose, workspace } from '@/composables/useWorkspace';
import { entities, modules } from '@/lib/modules';
import type { ModuleId } from '@/lib/modules';
import type { EntityKey } from '@/types/erp';

/**
 * Tab renderer: module icon, title, an unsaved-changes dot, and a close
 * button that goes through the workspace so dirty forms ask first.
 */
const props = defineProps<{
    params: {
        params: Record<string, unknown>;
        api: DockviewPanelApi;
        containerApi: DockviewApi;
    };
}>();

const panelApi = props.params.api;
const title = ref(panelApi.title ?? '');
const subscription = panelApi.onDidTitleChange((event) => {
    title.value = event.title;
});
onUnmounted(() => subscription.dispose());

const dirty = computed(() => Boolean(workspace.dirty[panelApi.id]));

const icon = computed(() => {
    const id = panelApi.id;

    if (id === 'dashboard' || id === 'reports') {
        return modules[id as ModuleId].icon;
    }

    if (id.startsWith('headend:')) {
        return modules.headends.icon;
    }

    const entity = id.split(':')[1] as EntityKey | undefined;

    return entity && entity in entities
        ? entities[entity].icon
        : 'pi pi-window-maximize';
});

function close(event: MouseEvent): void {
    event.preventDefault();
    event.stopPropagation();
    void requestClose(panelApi.id);
}

function onAuxClick(event: MouseEvent): void {
    // Middle-click closes, like a browser tab.
    if (event.button === 1) {
        close(event);
    }
}
</script>

<template>
    <div
        class="erp-tab"
        :class="{ 'erp-tab--dirty': dirty }"
        :title="dirty ? `${title} (unsaved changes)` : title"
        @auxclick="onAuxClick"
    >
        <i :class="icon" class="erp-tab-icon" aria-hidden="true" />
        <span class="erp-tab-title">{{ title }}</span>
        <span v-if="dirty" class="erp-tab-dirty" aria-label="Unsaved changes" />
        <button
            type="button"
            class="erp-tab-close"
            :aria-label="`Close ${title}`"
            @pointerdown.stop
            @mousedown.stop
            @click="close"
        >
            <i class="pi pi-times" aria-hidden="true" />
        </button>
    </div>
</template>
