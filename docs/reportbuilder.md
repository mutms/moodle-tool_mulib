# Report builder in MuTMS plugins

How MuTMS plugins use and tweak Moodle report builder system reports: page layout, filter bar, pager,
row actions and downloads. Everything is in `tool_mulib` and works on top of the standard report builder
storage and `core_table/dynamic`, so the core filters form, downloads and core Behat steps keep working.

## Report pages

```php
echo $OUTPUT->render(new report_filter_bar($report, pinned: [...]));
echo $report->output();
echo $OUTPUT->render(new report_pager($report));
```

The filter bar must be rendered before the report and the pager after it. The parts are independent,
a page may leave either out.

## Reports

- Row actions that open a muform dialog: `tool_mulib\output\muform\dialog\link` with `create_report_action()`,
  passed to `add_action()`; the report builder fills the row placeholders into its URL.
- `local\reportbuilder_util::is_downloading()` - true in `/reportbuilder/download.php`. Do not add actions when
  downloading (see the method for why), column callbacks return plain text instead of HTML.
- `local\reportbuilder_util::format_badge($text, $class)` - badge pill in the page, plain text in downloads.
- `reportbuilder\local\filters\boolean_yes` - yes/no filter that filters only when set to "Yes", for options
  that restrict a report when switched on, such as "Exclude sub-categories". "No" of the core `boolean_select`
  selects the opposite records (sub-categories only), here it does nothing. Add
  `->set_limited_operators(boolean_yes::OPERATORS)` to hide "No" in the core form; the filter bar offers "Yes" only.
- `local\sql::rename_params()` - converts a `sql` fragment for report builder APIs that accept only their
  own parameter names (`database::generate_param_name()`), for example base conditions.

## Filter bar

- Text, select, boolean and date filters are edited in the bar, other types (course role, number, ...) only in
  the core form, which moves into the bar as "Advanced filtering".
- Filter names, select option labels and "any" texts are exported with `clean_string()` and decoded to plain
  text in the browser, so `format_string()` results are fine and tags are shown as text.
- New filter types: a branch in the PHP constructor plus `isActive()`, `describe()`, `buildValues()` and the
  editor in `js/esm/src/reportbuilder/filterbar.ts`.
- Core `set_filters` replaces all stored values, so every save sends all filters.

## Pinned filters

- `pinned:` keys are filter identifiers in display order, values are the "any" texts, null is the generic "Any".

- The "any" text is per filter because many languages need it to agree with the filter name in grammatical
  gender and number (Czech "všechny", "všechna", "všichni"); null means the generic `filterbar_any`.
- Filters missing in the report are ignored, so callers may pin filters that only some users have.
- A pinned filter of a type the bar cannot edit opens the core form ("Advanced filtering").

Which filters to pin:

1. The name of the main entity first, always: program name on programs, user full name on any list of people
   (program users, cohort members), also in small reports.
2. Then the scope if the report has one: "Exclude sub-categories", tenant, category.
3. Then one to three status or type filters that are used daily and usually have a column in the table:
   status, type, draft, archived. Archived is always last.
4. At most four or five pinned filters, the bar must fit one line next to "Advanced filtering".
5. Never dates, other parts of a pinned name (first name, last name, ID number), custom profile fields or
   filters the bar cannot edit.
6. The same filter has the same position in all reports of all plugins.

Every report page gets the pager, also reports without filters and short lists.

## Default filter values

```php
new report_filter_bar($report, pinned: [...], defaults: [
    'program:archived' => ['operator' => boolean_select::NOT_CHECKED],
    'session:timestart' => ['operator' => date::DATE_CURRENT, 'unit' => date::DATE_UNIT_YEAR],
]);
```

- Values are the stored report builder filter values without the filter identifier prefix (`operator`, `value`,
  `unit`, `from`, `to`). Relative date operators such as "current year" need no yearly maintenance.
- Core system reports have no defaults and their SQL cannot be changed from outside, so defaults are seeded:
  when the user has no stored filters of the report, the bar stores the defaults before the report is
  rendered. After that they are ordinary values for table reloads, downloads and the core form.
- Core keeps one record per user and report. No record means "never filtered" or "Reset all" - both give the
  defaults. A record with all filters empty means the user removed them, so defaults do not come back.
- × sets a filter to "any" also when it has a default; the editor of a filter that differs from its default
  has "Reset to default".
- "Reset filters" in the bar is shown when any filter differs from the initial state, it sets the defaults
  and removes all other filters.
- Core stores filters per report record, which includes the context: a report in two categories has two sets
  of stored values and each is seeded on its own.
- A default is a filter: the pager says "matching filters" and the download is "Download filtered".
- Users who already have stored filters of a report get new defaults only after "Reset all"; changing a
  default in code does not change stored values.
- Pages showing the same report (same report source, context and parameters record) share the stored values,
  they should pass the same defaults.
- Archived items are hidden by default (`archived` = No) in lists that have such a filter.
- Behat: a scenario that needs archived items clicks the pinned `Archived: No` button and removes or changes it.

## Pager

- PHP knows no counts - the report runs its query inside `output()` and does not expose the table - so the
  renderable only outputs a placeholder and JS reads `data-table-total-rows`, `data-table-page-size` and
  `data-table-page-number` of the dynamic table root. Core replaces that root on every reload, the pager looks
  it up again on `core_table/dynamic:tableContentRefreshed`.
- The count is the filtered one, the only count core computes. An unfiltered total ("4 of 6") would need an
  extra count query on every reload; instead the range says "matching filters" when any filter is applied.
  The initial state is `get_applied_filter_count()`, later the filter bar dispatches
  `tool_mulib/reportbuilder:filterschanged` on the report element. Without the filter bar the text is not
  updated when the core filters form changes values without a page reload.
- The count is shown also when everything fits one page, where core prints nothing at all.
- Page size lives in `sessionStorage` (`tool_mulib-pagesize-<reportid>`, shared by reports with different
  parameters), never on the server: a large page size must not become the daily default of anybody. A stored
  size different from the rendered one costs one table reload after page load.
- The core paging bar and the "Show all" link are hidden with the `tool_mulib-report-pager-active` class on
  the report element, nothing is removed from the core markup.
- The download menu ("Download all", "Download filtered" when any filter is applied - core downloads all
  pages, but only the records matching the filters) is built from the hidden core download form of the table:
  its address, hidden parameters and formats. So permissions, enabled data formats and report parameters need
  no code here, and the menu is missing wherever core prints no form. The form is hidden with the
  `tool_mulib-report-pager-download` class.
  `new report_pager($report, download: false)` keeps the core form.
- Theme CSS is cached: after changing `styles.css` purge caches, on Behat sites delete `temp/theme`,
  `localcache/theme` and `behat/themedata` in the Behat dataroot.

## Behat

- Unpinned filters: `I press "+ Add filter"`, then click the filter name and the value in
  `.tool_mulib-report-filter-bar-popover`; `Remove filter: <name>` button.
- Pinned filters are always in the bar and never in "+ Add filter": click `<name>: Any` or `<name>: <value>`.
- Pager: texts in `.tool_mulib-report-pager`, field `Per page` with values such as `10 per page`, buttons
  `Next`, `Previous` and page numbers.
- Downloads: click the `Download all` button (`Download filtered` when filtering), then
  `following "Comma separated values (.csv)" should download between "1" and "9" bytes`.

## Later

- A helper such as `reportbuilder_util::render_report($report, pinned: [...], pager: true)` if the three
  lines get repetitive.
- Filters preset from the page context (a tenant selector that forces "tenant = X"). They would fight with the
  stored values shared by all pages of the report; report parameters with a selector above the report fit better.
