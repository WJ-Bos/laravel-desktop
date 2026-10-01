<script setup lang="ts">
import type {
    DockviewApi,
    DockviewGroupPanelApi,
    IDockviewPanel,
} from 'dockview-vue';
import { computed, onUnmounted, ref } from 'vue';

/**
 * Buttons on the right of each tab strip: float / dock and maximize.
 */
const props = defineProps<{
    params: {
        api: DockviewGroupPanelApi;
        containerApi: DockviewApi;
        activePanel: IDockviewPanel | undefined;
        isGroupActive: boolean;
    };
}>();

const group = props.params.api;
const location = ref(group.location.type);
const maximized = ref(
    group.location.type === 'grid' ? group.isMaximized() : false,
);

const disposables = [
    group.onDidLocationChange((event) => {
        location.value = event.location.type;
    }),
    props.params.containerApi.onDidMaximizedGroupChange(() => {
        maximized.value = group.location.type === 'grid' && group.isMaximized();
    }),
];
onUnmounted(() => disposables.forEach((d) => d.dispose()));

const floating = computed(() => location.value === 'floating');

function toggleFloat(): void {
    const api = props.params.containerApi;
    const panel = props.params.activePanel;

    if (!panel) {
        return;
    }

    if (floating.value) {
        const target = api.groups.find((g) => g.api.location.type === 'grid');

        if (target) {
            panel.api.moveTo({ group: target, position: 'center' });
        } else {
            panel.api.moveTo({ position: 'right' });
        }
    } else {
        api.addFloatingGroup(panel, { width: 720, height: 460, x: 60, y: 40 });
    }
}

function toggleMaximize(): void {
    if (group.isMaximized()) {
        group.exitMaximized();
    } else {
        group.maximize();
    }
}
</script>

<template>
    <div class="erp-group-actions">
        <button
            type="button"
            class="erp-icon-btn"
            :title="floating ? 'Dock back into the layout' : 'Float this panel'"
            :aria-label="floating ? 'Dock panel' : 'Float panel'"
            @click="toggleFloat"
        >
            <i
                :class="floating ? 'pi pi-sign-in' : 'pi pi-external-link'"
                aria-hidden="true"
            />
        </button>
        <button
            v-if="!floating"
            type="button"
            class="erp-icon-btn"
            :title="maximized ? 'Restore' : 'Maximize'"
            :aria-label="maximized ? 'Restore panel size' : 'Maximize panel'"
            @click="toggleMaximize"
        >
            <i
                :class="
                    maximized
                        ? 'pi pi-window-minimize'
                        : 'pi pi-window-maximize'
                "
                aria-hidden="true"
            />
        </button>
    </div>
</template>
