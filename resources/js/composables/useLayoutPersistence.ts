import { onUnmounted, reactive } from 'vue';
import { api } from '@/lib/api';

/**
 * Per-user UI state stored server-side under a key:
 *   "desktop"      dockview workspace (panels, splits, floating groups)
 *   "dashboard"    widget positions/sizes
 *   "grid:<name>"  AG Grid column state (order, width, visibility, sort)
 *
 * The desktop page hands over every saved layout with its first response,
 * so restoring needs no extra request. Saves are debounced.
 */

const initialLayouts: Record<string, unknown> = {};

export const layoutStatus = reactive({
    saving: false,
    lastSaved: null as Date | null,
    error: null as string | null,
});

export function seedLayouts(
    layouts: Record<string, unknown> | null | undefined,
): void {
    Object.assign(initialLayouts, layouts ?? {});
}

export function useLayoutPersistence<T>(key: string, debounceMs = 800) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let pending: T | null = null;
    let suspended = false;

    function initial(): T | null {
        return (initialLayouts[key] as T | undefined) ?? null;
    }

    async function write(data: T): Promise<void> {
        layoutStatus.saving = true;

        try {
            await api.put(`/api/layouts/${key}`, { data });
            initialLayouts[key] = data;
            layoutStatus.lastSaved = new Date();
            layoutStatus.error = null;
        } catch (error) {
            layoutStatus.error = (error as Error).message;
        } finally {
            layoutStatus.saving = false;
        }
    }

    function save(data: T): void {
        if (suspended) {
            return;
        }

        pending = data;

        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            timer = null;

            if (pending !== null) {
                void write(pending);
                pending = null;
            }
        }, debounceMs);
    }

    async function flush(): Promise<void> {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        if (pending !== null) {
            const data = pending;
            pending = null;
            await write(data);
        }
    }

    function cancel(): void {
        if (timer) {
            clearTimeout(timer);
        }

        timer = null;
        pending = null;
    }

    /** Delete the saved layout; changes made while `suspend` runs aren't saved. */
    async function reset(): Promise<void> {
        cancel();
        delete initialLayouts[key];
        await api.delete(`/api/layouts/${key}`);
        layoutStatus.lastSaved = new Date();
    }

    async function suspend(fn: () => void | Promise<void>): Promise<void> {
        suspended = true;

        try {
            await fn();
        } finally {
            suspended = false;
        }
    }

    onUnmounted(() => {
        void flush();
    });

    return { initial, save, flush, cancel, reset, suspend };
}
