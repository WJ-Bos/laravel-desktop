import {
    AllCommunityModule,
    ModuleRegistry,
    themeQuartz,
} from 'ag-grid-community';
import type { ColDef, ValueFormatterParams } from 'ag-grid-community';
import { fmt } from '@/lib/format';

ModuleRegistry.registerModules([AllCommunityModule]);

/**
 * Quartz, compacted for desktop density. Every color points at an --erp-*
 * CSS variable, so the grid flips with the `.dark` class along with
 * PrimeVue and dockview — no grid remount needed on theme change.
 */
export const gridTheme = themeQuartz.withParams({
    fontFamily: 'inherit',
    fontSize: 12,
    headerFontSize: 12,
    headerFontWeight: 600,
    spacing: 4,
    rowHeight: 26,
    headerHeight: 28,
    iconSize: 12,
    cellHorizontalPadding: 8,
    wrapperBorder: false,
    wrapperBorderRadius: 0,
    borderRadius: 2,
    browserColorScheme: 'inherit',
    accentColor: 'var(--erp-accent)',
    backgroundColor: 'var(--erp-panel)',
    foregroundColor: 'var(--erp-text)',
    textColor: 'var(--erp-text)',
    borderColor: 'var(--erp-border)',
    headerBackgroundColor: 'var(--erp-panel-2)',
    headerTextColor: 'var(--erp-text-2)',
    oddRowBackgroundColor: 'var(--erp-row-alt)',
    rowHoverColor: 'var(--erp-row-hover)',
    selectedRowBackgroundColor: 'var(--erp-selection)',
    chromeBackgroundColor: 'var(--erp-panel-2)',
    menuBackgroundColor: 'var(--erp-panel)',
    inputBorder: { color: 'var(--erp-border-strong)' },
    columnBorder: { color: 'var(--erp-grid-line)' },
    headerColumnBorder: { color: 'var(--erp-border)' },
    rowBorder: { color: 'var(--erp-grid-line)' },
});

export const money = (p: ValueFormatterParams) =>
    typeof p.value === 'number' ? fmt.money(p.value) : '';

export const integer = (p: ValueFormatterParams) =>
    typeof p.value === 'number' ? fmt.int(p.value) : '';

export const date = (p: ValueFormatterParams) =>
    fmt.date(p.value as string | null);

export const dateTime = (p: ValueFormatterParams) =>
    fmt.dateTime(p.value as string | null);

/** `type: 'rightAligned'` only sets this class when cellClass is unset, so keep it explicitly. */
export const RIGHT = ['erp-num', 'ag-right-aligned-cell'];

const filterParams = {
    buttons: ['reset', 'apply'],
    closeOnApply: true,
    maxNumConditions: 2,
};

export const col = {
    decimal: (
        field: string,
        headerName: string,
        digits = 1,
        extra: ColDef = {},
    ): ColDef => ({
        field,
        headerName,
        type: 'rightAligned',
        filter: 'agNumberColumnFilter',
        filterParams,
        valueFormatter: (p: ValueFormatterParams) =>
            typeof p.value === 'number'
                ? p.value.toLocaleString('en-US', {
                      minimumFractionDigits: digits,
                      maximumFractionDigits: digits,
                  })
                : '',
        cellClass: RIGHT,
        width: 105,
        ...extra,
    }),
    text: (field: string, headerName: string, extra: ColDef = {}): ColDef => ({
        field,
        headerName,
        filter: 'agTextColumnFilter',
        filterParams,
        ...extra,
    }),
    number: (
        field: string,
        headerName: string,
        extra: ColDef = {},
    ): ColDef => ({
        field,
        headerName,
        type: 'rightAligned',
        filter: 'agNumberColumnFilter',
        filterParams,
        valueFormatter: integer,
        cellClass: RIGHT,
        width: 100,
        ...extra,
    }),
    money: (field: string, headerName: string, extra: ColDef = {}): ColDef => ({
        field,
        headerName,
        type: 'rightAligned',
        filter: 'agNumberColumnFilter',
        filterParams,
        valueFormatter: money,
        cellClass: RIGHT,
        width: 120,
        ...extra,
    }),
    date: (field: string, headerName: string, extra: ColDef = {}): ColDef => ({
        field,
        headerName,
        filter: 'agDateColumnFilter',
        filterParams: { ...filterParams, inRangeInclusive: true },
        valueFormatter: date,
        cellClass: 'erp-num',
        width: 110,
        ...extra,
    }),
    status: (
        field: string,
        headerName: string,
        extra: ColDef = {},
    ): ColDef => ({
        field,
        headerName,
        filter: false,
        width: 110,
        cellRenderer: (p: { value: string | null }) =>
            p.value
                ? `<span class="erp-tag erp-tag--${p.value}">${p.value.replace(/_/g, ' ')}</span>`
                : '',
        ...extra,
    }),
    bool: (field: string, headerName: string, extra: ColDef = {}): ColDef => ({
        field,
        headerName,
        filter: false,
        width: 80,
        cellClass: 'erp-center',
        cellRenderer: (p: { value: boolean | null }) =>
            p.value === null || p.value === undefined
                ? ''
                : p.value
                  ? '<i class="pi pi-check erp-bool-yes" aria-label="Yes"></i>'
                  : '<i class="pi pi-minus erp-bool-no" aria-label="No"></i>',
        ...extra,
    }),
};
