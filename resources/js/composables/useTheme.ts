import { themeDark, themeLight } from 'dockview-vue';
import type { DockviewTheme } from 'dockview-vue';
import { computed, readonly, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

/**
 * One source of truth for light/dark across the whole desktop:
 *
 *  - PrimeVue follows the `.dark` class on <html> (darkModeSelector).
 *  - AG Grid's theme params point at the --erp-* CSS variables, which flip
 *    with the same class, so grids repaint without remounting.
 *  - dockview gets its own light/dark theme object (plus CSS overrides).
 *  - ECharts options read `chart` tokens from here and are rebuilt
 *    reactively when the theme changes.
 */

const isDark = ref(
    typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark'),
);

if (typeof document !== 'undefined') {
    new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    }).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

/**
 * Categorical slots validated (CVD / normal-vision / contrast) against this
 * app's panel surfaces in both modes. Assign in order; never cycle.
 */
const SERIES_LIGHT = [
    '#2a78d6',
    '#eb6834',
    '#1baf7a',
    '#eda100',
    '#e87ba4',
    '#008300',
    '#4a3aa7',
];
const SERIES_DARK = [
    '#3987e5',
    '#d95926',
    '#199e70',
    '#c98500',
    '#d55181',
    '#008300',
    '#9085e9',
];

/** Sequential blue ramp (light → dark) for magnitude, e.g. the heatmap. */
const SEQUENTIAL_LIGHT = [
    '#eef4fc',
    '#cde2fb',
    '#9ec5f4',
    '#6da7ec',
    '#3987e5',
    '#256abf',
    '#184f95',
    '#0d366b',
];
const SEQUENTIAL_DARK = [
    '#1f2833',
    '#16365c',
    '#104281',
    '#1c5cab',
    '#2a78d6',
    '#5598e7',
    '#86b6ef',
    '#cde2fb',
];

export interface ChartTokens {
    series: string[];
    sequential: string[];
    other: string;
    comparison: string;
    surface: string;
    text: string;
    textMuted: string;
    grid: string;
    axis: string;
    tooltipBg: string;
    tooltipBorder: string;
    good: string;
    critical: string;
    fontFamily: string;
}

const FONT = "'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif";

const lightTokens: ChartTokens = {
    series: SERIES_LIGHT,
    sequential: SEQUENTIAL_LIGHT,
    other: '#a3adb8',
    comparison: '#97a3af',
    surface: '#ffffff',
    text: '#1d2733',
    textMuted: '#5f6c79',
    grid: '#e8ecf0',
    axis: '#cbd3db',
    tooltipBg: '#ffffff',
    tooltipBorder: '#cbd3db',
    good: '#0a7f0a',
    critical: '#c23434',
    fontFamily: FONT,
};

const darkTokens: ChartTokens = {
    series: SERIES_DARK,
    sequential: SEQUENTIAL_DARK,
    other: '#5d6873',
    comparison: '#6b7782',
    surface: '#1a2027',
    text: '#e4e9ee',
    textMuted: '#a9b4bf',
    grid: '#262e37',
    axis: '#3d4751',
    tooltipBg: '#20272f',
    tooltipBorder: '#3d4751',
    good: '#3fbf3f',
    critical: '#ec6a6a',
    fontFamily: FONT,
};

const dockLight: DockviewTheme = {
    ...themeLight,
    className: `${themeLight.className} erp-dock`,
};
const dockDark: DockviewTheme = {
    ...themeDark,
    className: `${themeDark.className} erp-dock`,
};

export function useTheme() {
    const { updateAppearance } = useAppearance();

    const chart = computed<ChartTokens>(() =>
        isDark.value ? darkTokens : lightTokens,
    );
    const dockviewTheme = computed<DockviewTheme>(() =>
        isDark.value ? dockDark : dockLight,
    );

    function setDark(dark: boolean): void {
        updateAppearance(dark ? 'dark' : 'light');
    }

    function toggle(): void {
        setDark(!isDark.value);
    }

    return {
        isDark: readonly(isDark),
        chart,
        dockviewTheme,
        setDark,
        toggle,
    };
}
