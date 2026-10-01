import type { DockviewApi, IDockviewPanel } from 'dockview-vue';
import { onUnmounted, reactive, shallowRef } from 'vue';
import { entities, modules } from '@/lib/modules';
import type { ModuleId } from '@/lib/modules';
import type { EditableEntity, EntityKey } from '@/types/erp';

/**
 * The desktop's window manager: opens modules, records and headend windows
 * as dockview panels, tracks the active panel, unsaved changes and
 * per-panel actions (so Ctrl+N / Ctrl+S / refresh / export reach whatever
 * has focus).
 *
 * Panel ids:
 *   dashboard | reports | grid:<entity> | grid:<entity>:<scope>
 *   record:<entity>:<id> | record:<entity>:new-<n> | headend:<id>
 */

export interface PanelActions {
    onNew?: () => void;
    onSave?: () => void | Promise<unknown>;
    onRefresh?: () => void;
    onExport?: () => void;
}

export interface GridStats {
    loaded: number;
    total: number;
    selected: number;
}

export interface GridPanelParams {
    module: EntityKey;
    /** AG Grid filter model merged into every request (e.g. readings of one meter). */
    baseFilter?: Record<string, unknown>;
    scopeLabel?: string;
}

export interface RecordPanelParams {
    entity: EditableEntity;
    id: number | null;
    preset?: Record<string, unknown>;
    focus?: boolean;
}

export interface HeadendPanelParams {
    id: number;
}

type ConfirmFn = (options: {
    header: string;
    message: string;
    acceptLabel?: string;
    danger?: boolean;
}) => Promise<boolean>;
type Listener = (payload: { entity: EntityKey; id?: number }) => void;

const dock = shallowRef<DockviewApi | null>(null);

export const workspace = reactive({
    activeId: null as string | null,
    activeTitle: '',
    dirty: {} as Record<string, boolean>,
    gridStats: {} as Record<string, GridStats>,
    panelIds: [] as string[],
    sidebarOpen: true,
    paletteOpen: false,
    shortcutsOpen: false,
    aboutOpen: false,
});

const actions = new Map<string, PanelActions>();
const listeners = new Map<string, Set<Listener>>();
let confirmFn: ConfirmFn = async ({ message }) => window.confirm(message);
let newCounter = 0;
let floatOffset = 0;

function syncPanels(): void {
    const api = dock.value;
    workspace.panelIds = api ? api.panels.map((p) => p.id) : [];
}

export function attachDock(api: DockviewApi): void {
    dock.value = api;

    api.onDidActivePanelChange((event) => {
        workspace.activeId = event.panel?.id ?? null;
        workspace.activeTitle = event.panel?.title ?? '';
    });
    api.onDidAddPanel(() => syncPanels());
    api.onDidRemovePanel((panel) => {
        delete workspace.dirty[panel.id];
        delete workspace.gridStats[panel.id];
        actions.delete(panel.id);
        syncPanels();
    });
    syncPanels();
}

export function setConfirm(fn: ConfirmFn): void {
    confirmFn = fn;
}

export function confirmAction(
    options: Parameters<ConfirmFn>[0],
): Promise<boolean> {
    return confirmFn(options);
}

export function dockApi(): DockviewApi | null {
    return dock.value;
}

function activate(id: string): boolean {
    const panel = dock.value?.getPanel(id);

    if (!panel) {
        return false;
    }

    panel.api.setActive();

    return true;
}

export function gridPanelId(entity: EntityKey, scope?: string): string {
    return scope ? `grid:${entity}:${scope}` : `grid:${entity}`;
}

export function openModule(id: ModuleId): void {
    const api = dock.value;

    if (!api) {
        return;
    }

    const panelId =
        id === 'dashboard' || id === 'reports' ? id : gridPanelId(id);

    if (activate(panelId)) {
        return;
    }

    api.addPanel({
        id: panelId,
        component:
            id === 'dashboard'
                ? 'dashboard'
                : id === 'reports'
                  ? 'reports'
                  : 'grid',
        title: modules[id].title,
        params:
            id === 'dashboard' || id === 'reports'
                ? {}
                : ({ module: id } satisfies GridPanelParams),
    });
}

/** A grid locked to a subset, e.g. "Readings – 2241100137". */
export function openScopedGrid(
    entity: EntityKey,
    scope: string,
    title: string,
    baseFilter: Record<string, unknown>,
): void {
    const api = dock.value;
    const panelId = gridPanelId(entity, scope);

    if (!api || activate(panelId)) {
        return;
    }

    api.addPanel({
        id: panelId,
        component: 'grid',
        title,
        params: {
            module: entity,
            baseFilter,
            scopeLabel: title,
        } satisfies GridPanelParams,
    });
}

/**
 * A headend opens in its own floating window listing its gateways and
 * their meters. Several can be open side by side.
 */
export function openHeadend(id: number, title?: string): void {
    const api = dock.value;
    const panelId = `headend:${id}`;

    if (!api || activate(panelId)) {
        return;
    }

    const host = (api as unknown as { element?: HTMLElement }).element;
    const width = Math.min(980, (host?.clientWidth ?? 1200) - 80);
    const height = Math.min(620, (host?.clientHeight ?? 800) - 60);
    floatOffset = (floatOffset + 1) % 6;

    api.addPanel({
        id: panelId,
        component: 'headend',
        title: title ?? `Headend #${id}`,
        params: { id } satisfies HeadendPanelParams,
        floating: {
            width,
            height,
            x: 40 + floatOffset * 28,
            y: 24 + floatOffset * 24,
        },
    });
}

export function openRecord(
    entity: EntityKey,
    id: number,
    title?: string,
    options: { focus?: boolean } = {},
): void {
    const api = dock.value;

    if (!api) {
        return;
    }

    if (entity === 'headends' && !options.focus) {
        openHeadend(id, title);

        return;
    }

    // Readings and events aren't edited on their own; open the meter they belong to.
    if (entity === 'readings' || entity === 'events') {
        return;
    }

    const panelId = `record:${entity}:${id}`;

    if (activate(panelId)) {
        return;
    }

    api.addPanel({
        id: panelId,
        component: 'record',
        title: title ?? `${entities[entity].singular} #${id}`,
        params: {
            entity,
            id,
            focus: options.focus,
        } satisfies RecordPanelParams,
    });
}

export function openNew(
    entity: EditableEntity,
    preset?: Record<string, unknown>,
): void {
    const api = dock.value;

    if (!api) {
        return;
    }

    newCounter++;

    api.addPanel({
        id: `record:${entity}:new-${Date.now().toString(36)}${newCounter}`,
        component: 'record',
        title: `New ${entities[entity].singular.toLowerCase()}`,
        params: {
            entity,
            id: null,
            preset,
            focus: true,
        } satisfies RecordPanelParams,
    });
}

export function setDirty(panelId: string, dirty: boolean): void {
    if (dirty) {
        workspace.dirty[panelId] = true;
    } else {
        delete workspace.dirty[panelId];
    }
}

export function hasUnsavedChanges(): boolean {
    return Object.keys(workspace.dirty).length > 0;
}

export async function requestClose(panelId: string): Promise<boolean> {
    const panel = dock.value?.getPanel(panelId);

    if (!panel) {
        return false;
    }

    if (workspace.dirty[panelId]) {
        const ok = await confirmFn({
            header: 'Discard changes?',
            message: `“${panel.title}” has unsaved changes. Close it and discard them?`,
            acceptLabel: 'Discard and close',
            danger: true,
        });

        if (!ok) {
            panel.api.setActive();

            return false;
        }
    }

    delete workspace.dirty[panelId];
    panel.api.close();

    return true;
}

export function closeActive(): void {
    const id = dock.value?.activePanel?.id;

    if (id) {
        void requestClose(id);
    }
}

export async function closeAll(exceptId?: string): Promise<void> {
    const api = dock.value;

    if (!api) {
        return;
    }

    const targets = api.panels.filter((p) => p.id !== exceptId);
    const dirty = targets.filter((p) => workspace.dirty[p.id]);

    if (dirty.length > 0) {
        const ok = await confirmFn({
            header: 'Discard changes?',
            message: `${dirty.length} open ${dirty.length === 1 ? 'panel has' : 'panels have'} unsaved changes. Close everything and discard them?`,
            acceptLabel: 'Discard and close',
            danger: true,
        });

        if (!ok) {
            return;
        }
    }

    targets.forEach((p) => {
        delete workspace.dirty[p.id];
        p.api.close();
    });
}

export function activePanel(): IDockviewPanel | undefined {
    return dock.value?.activePanel;
}

export function floatActive(): void {
    const api = dock.value;
    const panel = api?.activePanel;

    if (!api || !panel || panel.api.location.type === 'floating') {
        return;
    }

    api.addFloatingGroup(panel, { width: 760, height: 500, x: 80, y: 60 });
}

export function dockActive(): void {
    const api = dock.value;
    const panel = api?.activePanel;

    if (!api || !panel || panel.api.location.type === 'grid') {
        return;
    }

    const target = api.groups.find((g) => g.api.location.type === 'grid');

    if (target) {
        panel.api.moveTo({ group: target, position: 'center' });
    } else {
        panel.api.moveTo({ position: 'right' });
    }
}

export function toggleMaximizeActive(): void {
    const panel = dock.value?.activePanel;

    if (!panel || panel.api.location.type !== 'grid') {
        return;
    }

    if (panel.api.isMaximized()) {
        panel.api.exitMaximized();
    } else {
        panel.api.maximize();
    }
}

export function activateById(id: string): void {
    activate(id);
}

/* ---- per-panel actions --------------------------------------------- */

export function usePanelActions(
    panelId: string,
    panelActions: PanelActions,
): void {
    actions.set(panelId, panelActions);
    onUnmounted(() => {
        if (actions.get(panelId) === panelActions) {
            actions.delete(panelId);
        }
    });
}

function activeEditableEntity(): EditableEntity | null {
    const id = workspace.activeId ?? '';
    const match = id.match(/^(?:grid|record):(\w+)/);

    if (
        match &&
        match[1] in entities &&
        entities[match[1] as EntityKey].editable
    ) {
        return match[1] as EditableEntity;
    }

    return null;
}

/** Ctrl+N: the active module decides; record panels create a sibling record. */
export function triggerNew(): boolean {
    const id = workspace.activeId;
    const handler = id ? actions.get(id)?.onNew : undefined;

    if (handler) {
        handler();

        return true;
    }

    const entity = activeEditableEntity();

    if (entity) {
        openNew(entity);

        return true;
    }

    return false;
}

export function triggerSave(): boolean {
    const id = workspace.activeId;
    const handler = id ? actions.get(id)?.onSave : undefined;

    if (!handler) {
        return false;
    }

    void handler();

    return true;
}

export function triggerRefresh(): boolean {
    const id = workspace.activeId;
    const handler = id ? actions.get(id)?.onRefresh : undefined;
    handler?.();

    return Boolean(handler);
}

export function triggerExport(): boolean {
    const id = workspace.activeId;
    const handler = id ? actions.get(id)?.onExport : undefined;
    handler?.();

    return Boolean(handler);
}

/* ---- change notifications between panels -------------------------- */

export function onRecordChange(
    event: 'saved' | 'deleted',
    listener: Listener,
): void {
    const set = listeners.get(event) ?? new Set<Listener>();
    set.add(listener);
    listeners.set(event, set);
    onUnmounted(() => set.delete(listener));
}

export function emitRecordChange(
    event: 'saved' | 'deleted',
    payload: { entity: EntityKey; id?: number },
): void {
    listeners.get(event)?.forEach((listener) => listener(payload));
}
