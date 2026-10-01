# Northwind Desk

A demo back-office ("ERP lite") for **Northwind Supply Co.**, a fictional food and household wholesaler. It runs in the browser but behaves like a desktop application in the Ext JS tradition: a menubar, a navigator tree, dockable panels, dense data grids, keyboard shortcuts and a status bar. The shell owns the whole viewport and the page never scrolls.

Built with Laravel 13 (Vue starter kit: Inertia 3, Vue 3, TypeScript), PrimeVue (Aura, styled mode), AG Grid Community, dockview, Apache ECharts and SQLite.

## Setup

Requirements: PHP 8.3+ with `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo` and `intl`; Composer 2; Node 22+.

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
npm install
php artisan migrate --seed
composer run dev
```

Open http://localhost:8000 and sign in as **demo@example.com** / **password**.

Seeding takes a few seconds. It generates 18 months of history: 10 categories, 200 products, 24 suppliers, 500 customers, about 5,000 orders (about 19,500 lines) and about 4,800 invoices. Run `php artisan migrate:fresh --seed` at any time to start over; the data is relative to today's date, so the dashboard always has a current month.

`npm run build` produces a production bundle; with it in place, `php artisan serve` alone is enough.

## A short tour

**The shell.** The menubar has File, View, Data, Window and Help, with shortcut hints next to each item. The navigator tree on the left opens modules. The center is a dockview workspace: drag tabs to split or stack panels, use the button at the right of a tab strip (or right-click a tab) to float or maximize a panel, and close panels from the tab. The status bar shows the signed-in user, the active panel, row counts for the active grid, the last successful sync with the server and a connection indicator (it pings the server every 30 s).

**Layout persistence.** Open panels, splits and floating windows are saved per user (`PUT /api/layouts/desktop`) and restored on the next visit. Grid column order, widths, visibility and sort are saved too (`grid:<module>`), and so is the dashboard widget arrangement and period. Window → Reset layout returns the workspace to its default.

**Dashboard.** It shows four KPI cards: revenue, orders, average order value and open receivables. Each has a sparkline and a change against the previous period of the same length; the change is shown with an arrow and color. The revenue card adds month-to-date against the same days last month, and the orders card adds today against yesterday. The charts are revenue over time (with the previous period dashed for comparison), sales by category (donut), the top 10 products and an orders-by-weekday-and-hour heatmap, plus a recent-orders grid. The period selector updates every widget. Drag a widget by its title bar and resize it from the corner; the arrangement is saved. Aggregates come from `DashboardService` and are cached for 60 seconds.

**Grids** (Orders, Invoices, Products, Suppliers, Customers). Every grid uses AG Grid's infinite row model against a Laravel endpoint, with server-side paging, multi-column sorting and the column filters (text, number and date, including two-condition AND/OR). The toolbar has New, Edit, Duplicate, Delete, Refresh and Export, a column chooser, a filter-row toggle, a status/segment/category picker and a quick-search box. Export downloads every row that matches the current filters as CSV, streamed from the server. Double-click a row to open it in its own tab; right-click a row for Open, Edit, Duplicate, Delete and Export row.

**Detail panels.** Forms are dense, with labels on the left. Laravel Form Request errors appear under the field they belong to, and line-item errors highlight the offending cell. Editing a record puts a dot on its tab and a count in the status bar; closing a panel with unsaved changes asks first. An order has an editable line grid (quantity, price and discount are edited in the cell; products are added by SKU or name search) with live subtotal, tax and total. A customer shows lifetime revenue and open balance and opens that customer's orders or invoices as a scoped grid.

**Reports.** Pick sales by month, sales by category, top customers, product performance, sales by country or invoice aging, then a date range. Each report renders a chart and a sortable table and exports to CSV.

**Keyboard.**

| Keys           | Action                                                               |
| -------------- | -------------------------------------------------------------------- |
| Ctrl+K         | Command palette: modules, commands and record search                 |
| Ctrl+N / Alt+N | New record in the active module                                      |
| Ctrl+S         | Save the active form                                                 |
| Ctrl+W / Alt+W | Close the active panel                                               |
| Alt+1 … Alt+7  | Dashboard, Orders, Invoices, Products, Suppliers, Customers, Reports |
| Alt+M          | Maximize / restore the active panel                                  |
| Alt+R          | Refresh the active panel                                             |
| Ctrl+B         | Show / hide the navigator                                            |
| Ctrl+Shift+L   | Light / dark theme                                                   |
| F1             | Shortcut list                                                        |

Chrome and Edge don't let a page intercept Ctrl+N and Ctrl+W in a normal tab, so the Alt variants are always available. Installed as an app window, the Ctrl versions work.

**Theme.** The light/dark toggle (menubar, Ctrl+Shift+L or the palette) switches PrimeVue, dockview, AG Grid and the charts together. Grid and dock colors come from the same CSS variables, and chart colors from one validated palette per mode.

## How it fits together

```
app/
  Support/Grid/GridQuery.php       AG Grid sort/filter/window → Eloquent (one class for every grid)
  Support/Grid/GridColumn.php      whitelisted column → SQL expression + filter type
  Support/Grid/CsvExport.php       streamed CSV downloads
  Support/SqlDate.php              date bucketing for SQLite / MySQL / PostgreSQL
  Http/Controllers/Api/            Orders, Customers, Products, Suppliers, Invoices (extend GridController),
                                   Dashboard, Reports, Layout, Lookup, Search
  Http/Requests/                   GridRequest, DateRangeRequest, Save*Request, SaveLayoutRequest
  Http/Resources/                  API Resources for every model
  Services/DashboardService.php    KPIs, trend, category, top products, heatmap (cached 60 s)
  Services/ReportService.php       the six reports
  Actions/SaveOrder.php            order header + line-item sync in one transaction
database/seeders/DemoDataSeeder.php  seasonal, weekday and hourly order patterns
resources/js/
  pages/Desktop.vue                the shell (the only Inertia page after sign-in)
  shell/                           menubar, navigator, status bar, command palette, dock tab, help
  modules/                         GridPanel, RecordPanel, forms/, dashboard/, reports/
  composables/                     useGrid, useShortcuts, useLayoutPersistence, useTheme,
                                   useWorkspace (panels, dirty state, actions), useRecordForm, useLookups
  lib/                             API client, AG Grid theme and column helpers, PrimeVue preset, formatters
routes/desktop.php                 the desktop page and the JSON API
```

The API lives under `/api/*` in the `web` middleware group, so it uses the same session cookie and CSRF protection as the Inertia app. Only columns registered on a `GridQuery` can be sorted or filtered. A grid endpoint takes:

```
GET /api/orders?start=0&end=100
               &sort=[{"colId":"total","sort":"desc"}]
               &filter={"customer_name":{"filterType":"text","type":"contains","filter":"deli"},
                        "status":{"filterType":"set","values":["pending","processing"]}}
               &q=SO-1042
→ { "data": [...], "meta": { "total": 812, "start": 0, "end": 100, "lastRow": 812 } }
```

## Tests and checks

```bash
php artisan test          # Pest: grids (paging, sorting, every filter type, export), CRUD and validation,
                          # dashboard aggregates and caching, layout persistence, reports
composer test             # Pint + PHPStan (level 7) + the test suite
npm run types:check       # vue-tsc
npm run check             # formatter + type-aware lint
npm run build
```

## Notes

- **PrimeVue is pinned to 4.5 (MIT).** PrimeVue 5 moved to the PrimeUI License, which requires a license key; without one, every page shows an "Invalid PrimeUI License" banner. If you have a key, upgrade `primevue` and `@primeuix/themes` and pass `license` to `app.use(PrimeVue, …)` in `resources/js/app.ts`.
- AG Grid is the Community edition only: the infinite row model, built-in column filters and CSV export. The column chooser and the status picker are this app's own UI, standing in for Enterprise features.
- The demo data is generated with a fixed seed, so every install has the same customers and products.
