<script setup lang="ts">
import Dialog from 'primevue/dialog';
import { computed, nextTick, ref, watch } from 'vue';
import { comboLabel } from '@/composables/useShortcuts';
import { useTheme } from '@/composables/useTheme';
import {
    openHeadend,
    openModule,
    openNew,
    openRecord,
    workspace,
} from '@/composables/useWorkspace';
import { api, buildQuery } from '@/lib/api';
import { entities, modules, searchTypeToEntity } from '@/lib/modules';
import type { ModuleId } from '@/lib/modules';
import type { EditableEntity, SearchResult } from '@/types/erp';

/**
 * Ctrl+K: jump to a module, run a command, or find a headend, gateway or
 * meter by serial, name or account. Arrow keys move, Enter opens, Esc closes.
 */

const emit = defineEmits<{ resetLayout: [] }>();

interface Entry {
    id: string;
    section: 'Modules' | 'Commands' | 'Network';
    icon: string;
    title: string;
    subtitle?: string;
    hint?: string;
    run: () => void;
}

const { toggle: toggleTheme, isDark } = useTheme();
const query = ref('');
const records = ref<SearchResult[]>([]);
const searching = ref(false);
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
let controller: AbortController | null = null;
let timer: ReturnType<typeof setTimeout> | null = null;

const moduleEntries = computed<Entry[]>(() =>
    (Object.keys(modules) as ModuleId[]).map((id) => ({
        id: `module:${id}`,
        section: 'Modules',
        icon: modules[id].icon,
        title: modules[id].title,
        subtitle: modules[id].group,
        run: () => openModule(id),
    })),
);

const commandEntries = computed<Entry[]>(() => [
    ...(['meters', 'gateways', 'headends'] as EditableEntity[]).map((key) => ({
        id: `new:${key}`,
        section: 'Commands' as const,
        icon: 'pi pi-plus',
        title: `New ${entities[key].singular.toLowerCase()}`,
        run: () => openNew(key),
    })),
    {
        id: 'cmd:theme',
        section: 'Commands',
        icon: isDark.value ? 'pi pi-sun' : 'pi pi-moon',
        title: isDark.value ? 'Switch to light theme' : 'Switch to dark theme',
        hint: comboLabel('mod+shift+l'),
        run: toggleTheme,
    },
    {
        id: 'cmd:sidebar',
        section: 'Commands',
        icon: 'pi pi-bars',
        title: workspace.sidebarOpen ? 'Hide navigator' : 'Show navigator',
        hint: comboLabel('mod+b'),
        run: () => (workspace.sidebarOpen = !workspace.sidebarOpen),
    },
    {
        id: 'cmd:reset',
        section: 'Commands',
        icon: 'pi pi-replay',
        title: 'Reset window layout',
        run: () => emit('resetLayout'),
    },
    {
        id: 'cmd:shortcuts',
        section: 'Commands',
        icon: 'pi pi-key',
        title: 'Keyboard shortcuts',
        hint: 'F1',
        run: () => (workspace.shortcutsOpen = true),
    },
]);

function matches(entry: Entry, q: string): boolean {
    const keywords = entry.id.startsWith('module:')
        ? modules[entry.id.slice(7) as ModuleId].keywords
        : '';

    return `${entry.title} ${entry.subtitle ?? ''} ${keywords}`
        .toLowerCase()
        .includes(q);
}

const entries = computed<Entry[]>(() => {
    const q = query.value.trim().toLowerCase();
    const recordEntries: Entry[] = records.value.map((r) => {
        const entity = searchTypeToEntity[r.type];

        return {
            id: `record:${r.type}:${r.id}`,
            section: 'Network',
            icon: entities[entity].icon,
            title: r.title,
            subtitle: r.subtitle,
            hint: entities[entity].singular,
            run: () =>
                r.type === 'headend'
                    ? openHeadend(r.id, r.title)
                    : openRecord(
                          entity,
                          r.id,
                          `${entities[entity].singular} ${r.title}`,
                      ),
        };
    });

    if (!q) {
        return [...moduleEntries.value, ...commandEntries.value];
    }

    return [
        ...recordEntries,
        ...moduleEntries.value.filter((e) => matches(e, q)),
        ...commandEntries.value.filter((e) => matches(e, q)),
    ];
});

const sections = computed(() => {
    const groups: { name: string; items: { entry: Entry; index: number }[] }[] =
        [];

    entries.value.forEach((entry, index) => {
        let group = groups.find((g) => g.name === entry.section);

        if (!group) {
            group = { name: entry.section, items: [] };
            groups.push(group);
        }

        group.items.push({ entry, index });
    });

    return groups;
});

watch(query, (q) => {
    activeIndex.value = 0;

    if (timer) {
        clearTimeout(timer);
    }

    controller?.abort();

    if (q.trim().length < 2) {
        records.value = [];
        searching.value = false;

        return;
    }

    searching.value = true;
    timer = setTimeout(async () => {
        controller = new AbortController();

        try {
            const { data } = await api.get<{ data: SearchResult[] }>(
                `/api/search${buildQuery({ q: q.trim() })}`,
                { signal: controller.signal },
            );
            records.value = data;
        } catch {
            records.value = [];
        } finally {
            searching.value = false;
        }
    }, 160);
});

watch(
    () => workspace.paletteOpen,
    async (open) => {
        if (open) {
            query.value = '';
            records.value = [];
            activeIndex.value = 0;
            await nextTick();
            input.value?.focus();
        }
    },
);

function run(entry: Entry | undefined): void {
    if (!entry) {
        return;
    }

    workspace.paletteOpen = false;
    entry.run();
}

function onKeydown(event: KeyboardEvent): void {
    const count = entries.value.length;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = count ? (activeIndex.value + 1) % count : 0;
        scrollActive();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = count ? (activeIndex.value - 1 + count) % count : 0;
        scrollActive();
    } else if (event.key === 'Enter') {
        event.preventDefault();
        run(entries.value[activeIndex.value]);
    }
}

function scrollActive(): void {
    void nextTick(() => {
        document
            .querySelector('.erp-palette-item.is-active')
            ?.scrollIntoView({ block: 'nearest' });
    });
}
</script>

<template>
    <Dialog
        v-model:visible="workspace.paletteOpen"
        modal
        :show-header="false"
        dismissable-mask
        position="top"
        class="erp-palette"
        :pt="{ content: { style: 'padding: 0' } }"
    >
        <div class="erp-palette-input">
            <i class="pi pi-search" aria-hidden="true" />
            <input
                ref="input"
                v-model="query"
                type="text"
                placeholder="Meter serial, account, gateway, headend or module…"
                aria-label="Search"
                role="combobox"
                aria-expanded="true"
                aria-controls="erp-palette-list"
                :aria-activedescendant="
                    entries[activeIndex] ? `erp-pal-${activeIndex}` : undefined
                "
                @keydown="onKeydown"
            />
            <i
                v-if="searching"
                class="pi pi-spin pi-spinner erp-muted"
                aria-hidden="true"
            />
            <kbd>Esc</kbd>
        </div>
        <div id="erp-palette-list" class="erp-palette-list" role="listbox">
            <template v-for="section in sections" :key="section.name">
                <div class="erp-palette-section">{{ section.name }}</div>
                <div
                    v-for="{ entry, index } in section.items"
                    :id="`erp-pal-${index}`"
                    :key="entry.id"
                    role="option"
                    :aria-selected="index === activeIndex"
                    class="erp-palette-item"
                    :class="{ 'is-active': index === activeIndex }"
                    @mousemove="activeIndex = index"
                    @click="run(entry)"
                >
                    <i
                        :class="entry.icon"
                        class="erp-palette-icon"
                        aria-hidden="true"
                    />
                    <span class="erp-palette-title">{{ entry.title }}</span>
                    <span v-if="entry.subtitle" class="erp-palette-sub">{{
                        entry.subtitle
                    }}</span>
                    <span v-if="entry.hint" class="erp-palette-hint">{{
                        entry.hint
                    }}</span>
                </div>
            </template>
            <div v-if="!entries.length && !searching" class="erp-palette-empty">
                Nothing matches “{{ query }}”. Try part of a meter serial like
                2241, a gateway like GW-41011 or a city.
            </div>
        </div>
    </Dialog>
</template>
