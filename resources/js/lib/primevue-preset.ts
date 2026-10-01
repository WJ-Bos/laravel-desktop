import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

/**
 * Aura, tuned for a dense desktop back-office: a petrol-teal accent that
 * stays clear of the chart series colors, slate surfaces, tight radii and
 * compact form fields. Dark mode follows the `.dark` class on <html>.
 */
export const ErpPreset = definePreset(Aura, {
    primitive: {
        borderRadius: {
            none: '0',
            xs: '2px',
            sm: '2px',
            md: '3px',
            lg: '4px',
            xl: '6px',
        },
    },
    semantic: {
        primary: {
            50: '#effafb',
            100: '#d6f0f3',
            200: '#b0e1e7',
            300: '#7ccbd5',
            400: '#41abbb',
            500: '#23909f',
            600: '#1a7684',
            700: '#175f6b',
            800: '#174e58',
            900: '#16424b',
            950: '#0a2a31',
        },
        focusRing: {
            width: '2px',
            style: 'solid',
            color: '{primary.color}',
            offset: '1px',
        },
        formField: {
            paddingX: '0.5rem',
            paddingY: '0.3125rem',
            sm: {
                fontSize: '0.875rem',
                paddingX: '0.4rem',
                paddingY: '0.2rem',
            },
            borderRadius: '{border.radius.md}',
        },
        list: {
            padding: '0.25rem 0.25rem',
            gap: '1px',
            option: {
                padding: '0.3rem 0.5rem',
                borderRadius: '{border.radius.sm}',
            },
            optionGroup: { padding: '0.3rem 0.5rem' },
        },
        navigation: {
            list: { padding: '0.25rem 0.25rem', gap: '1px' },
            item: {
                padding: '0.3rem 0.6rem',
                borderRadius: '{border.radius.sm}',
                gap: '0.5rem',
            },
            submenuLabel: { padding: '0.3rem 0.6rem' },
        },
        overlay: {
            popover: { borderRadius: '{border.radius.md}', padding: '0.5rem' },
            modal: { borderRadius: '{border.radius.lg}', padding: '1rem' },
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{primary.600}',
                    contrastColor: '#ffffff',
                    hoverColor: '{primary.700}',
                    activeColor: '{primary.800}',
                },
                surface: {
                    0: '#ffffff',
                    50: '#f6f8fa',
                    100: '#eef1f4',
                    200: '#dfe4e9',
                    300: '#cbd3db',
                    400: '#97a3af',
                    500: '#6b7885',
                    600: '#4f5d6b',
                    700: '#3a4652',
                    800: '#26303a',
                    900: '#1d2733',
                    950: '#111820',
                },
                highlight: {
                    background: '{primary.50}',
                    focusBackground: '{primary.100}',
                    color: '{primary.800}',
                    focusColor: '{primary.900}',
                },
            },
            dark: {
                primary: {
                    color: '{primary.400}',
                    contrastColor: '{surface.950}',
                    hoverColor: '{primary.300}',
                    activeColor: '{primary.200}',
                },
                surface: {
                    0: '#ffffff',
                    50: '#e4e9ee',
                    100: '#c9d1d9',
                    200: '#a9b4bf',
                    300: '#8a96a2',
                    400: '#6b7782',
                    500: '#535e69',
                    600: '#3d4751',
                    700: '#2e3741',
                    800: '#20272f',
                    900: '#1a2027',
                    950: '#12171c',
                },
                highlight: {
                    background:
                        'color-mix(in srgb, {primary.400}, transparent 84%)',
                    focusBackground:
                        'color-mix(in srgb, {primary.400}, transparent 76%)',
                    color: 'rgba(255,255,255,.92)',
                    focusColor: 'rgba(255,255,255,.96)',
                },
            },
        },
    },
});
