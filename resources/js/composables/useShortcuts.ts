import { onMounted, onUnmounted, reactive } from 'vue';

/**
 * Global keyboard shortcuts.
 *
 * Combos are written like `mod+k`, `alt+n`, `mod+shift+l`, `f1` where `mod`
 * is Ctrl on Windows/Linux and ⌘ on macOS. Registrations stack: the most
 * recently mounted handler for a combo wins, so a focused dialog can take
 * over Escape without the shell knowing about it.
 *
 * A handler returns `false` to let the event fall through to the next
 * registration (or the browser).
 */

export type ShortcutHandler = (event: KeyboardEvent) => void | boolean;

export interface ShortcutOptions {
    handler: ShortcutHandler;
    /** Fire even when focus is in an input/textarea (default: true for mod/alt/F-keys). */
    allowInInputs?: boolean;
}

interface Registration extends ShortcutOptions {
    id: number;
    combo: string;
}

const registrations: Registration[] = [];
let nextId = 1;
let installed = false;

export const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform);

/** Shortcuts the user has pressed recently, for the help dialog. */
export const shortcutState = reactive({ lastCombo: '' });

export function normalizeCombo(combo: string): string {
    const parts = combo
        .toLowerCase()
        .split('+')
        .map((p) => p.trim());
    const key = parts.pop() ?? '';
    const mods = ['mod', 'alt', 'shift'].filter((m) => parts.includes(m));

    return [...mods, key].join('+');
}

function comboFromEvent(event: KeyboardEvent): string {
    const mods: string[] = [];

    if (isMac ? event.metaKey : event.ctrlKey) {
        mods.push('mod');
    }

    if (event.altKey) {
        mods.push('alt');
    }

    if (event.shiftKey) {
        mods.push('shift');
    }

    // event.code keeps Alt+N working on layouts where Alt changes event.key.
    let key = event.key.toLowerCase();

    if (event.code.startsWith('Key')) {
        key = event.code.slice(3).toLowerCase();
    } else if (event.code.startsWith('Digit')) {
        key = event.code.slice(5);
    }

    return [...mods, key].join('+');
}

function isEditable(target: EventTarget | null): boolean {
    const el = target as HTMLElement | null;

    if (!el) {
        return false;
    }

    return (
        el.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)
    );
}

function onKeyDown(event: KeyboardEvent): void {
    const combo = comboFromEvent(event);
    const hasModifier =
        combo.includes('mod+') ||
        combo.includes('alt+') ||
        /^f\d+$/.test(combo.split('+').pop() ?? '');

    for (let i = registrations.length - 1; i >= 0; i--) {
        const reg = registrations[i];

        if (reg.combo !== combo) {
            continue;
        }

        const allow = reg.allowInInputs ?? hasModifier;

        if (!allow && isEditable(event.target)) {
            continue;
        }

        if (reg.handler(event) === false) {
            continue;
        }

        event.preventDefault();
        event.stopPropagation();
        shortcutState.lastCombo = combo;

        return;
    }
}

function install(): void {
    if (installed || typeof window === 'undefined') {
        return;
    }

    window.addEventListener('keydown', onKeyDown, true);
    installed = true;
}

export function registerShortcut(
    combo: string,
    options: ShortcutHandler | ShortcutOptions,
): () => void {
    install();

    const opts = typeof options === 'function' ? { handler: options } : options;
    const reg: Registration = {
        ...opts,
        id: nextId++,
        combo: normalizeCombo(combo),
    };
    registrations.push(reg);

    return () => {
        const index = registrations.findIndex((r) => r.id === reg.id);

        if (index >= 0) {
            registrations.splice(index, 1);
        }
    };
}

/** Register shortcuts for the lifetime of the calling component. */
export function useShortcuts(
    map: Record<string, ShortcutHandler | ShortcutOptions>,
): void {
    const disposers: (() => void)[] = [];

    onMounted(() => {
        for (const [combo, options] of Object.entries(map)) {
            disposers.push(registerShortcut(combo, options));
        }
    });

    onUnmounted(() => disposers.splice(0).forEach((d) => d()));
}

/** Human label for a combo, e.g. `mod+shift+k` → `Ctrl+Shift+K`. */
export function comboLabel(combo: string): string {
    return normalizeCombo(combo)
        .split('+')
        .map((part) => {
            switch (part) {
                case 'mod':
                    return isMac ? '⌘' : 'Ctrl';
                case 'alt':
                    return isMac ? '⌥' : 'Alt';
                case 'shift':
                    return 'Shift';
                case 'escape':
                    return 'Esc';
                default:
                    return part.length === 1
                        ? part.toUpperCase()
                        : part.toUpperCase();
            }
        })
        .join('+');
}
