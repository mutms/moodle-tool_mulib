# tool_mulib - MuTMS shared library

Shared utility plugin for the MuTMS plugin suite. It has no end-user features of its own, it provides
infrastructure the other MuTMS plugins depend on. The largest part is **muform**, the forms library
that replaces moodleform in all MuTMS plugins.

## Forms: muform

Full reference: `docs/muform.md`. Read it before writing any form. MuTMS plugins never use moodleform.

- Form: `tool_mulib\muform\form` subclass, `definition()` adds elements, optional `validation()`.
  Constructed as `new my_form($targeturl, $currentdata, $extradata)`; the form posts to `$targeturl`.
- Handler scripts use `tool_mulib\muform\handler`, the same URL serves the full page and the dialog:

```php
$PAGE->set_url($currenturl);           // must be this script with its real parameters
$PAGE->set_title($title);
$PAGE->set_heading($title);             // also the dialog title

$handler = handler::from_request();
$form = new my_form($currenturl, $current, $extra);
if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}
if ($data = $form->get_data()) {
    // Save.
    $handler->submitted($returnurl);
}
$handler->render($form);
```

  Classic scripts stop in each call; router controllers pass `($request, $response)` and return the
  answers. `$handler->is_dialog()` is only for multi-step flows that render the next step in the dialog.
- Dialog triggers: `tool_mulib\output\muform\dialog\{button,link,icon}`, `create_report_action()` for
  report builder, `dropdown::add_dialog()` for header menus, `set_submitted_action()` with
  `tool_mulib\muform\handler\dialog::ACTION_RELOAD|ACTION_REDIRECT|ACTION_NOTHING`. Default size is `lg`.
- Autocomplete: one source class serves both `autocomplete` and `autocompletemany`; sources live in
  `<component>\muform\autocomplete\*` and extend `tool_mulib\muform\autocomplete\base`. The constructor takes scalar
  ids and does the access control, `get_args()` returns them, `search($query, $maxitems, $exclude)`, `labels()` or
  `label_value()` and `validate()` or `validate_value()` decide what may be selected. Reuse the traits in
  `classes/muform/util/autocomplete/`: `user_trait`, `course_trait`, `category_context_trait`, and
  `local\search_util` for search SQL and user labels. Tags use a `<component>\muform\tagarea\*` class the same way.
- Pickers (`picker`, `pickermany`): selected values are pills, a button opens a family panel (React) over the
  right half of the screen. A family is an abstract `tool_mulib\muform\picker\base` subclass, a panel module
  and the family's own endpoints (`base::require_browser_session()`, `<family base>::instantiate()`); values
  are opaque strings like autocomplete values; `are_allowed($values)` of a source re-checks values in form
  `validation()`. See "Picker" in `docs/muform.md`.
- User pickers: `userpicker`/`userpickermany` elements with a source in `<component>\muform\userpicker\*` extending
  `tool_mulib\muform\userpicker\base` (`get_context()`, optional `get_candidates_sql()`/`get_selected_sql()` on alias `u`,
  `allow_suspended_users()`); a panel with identity columns (`showuseridentity`) and paging, tenant rules apply.
  Filter pills above the list: pinned "Active: Yes" hides suspended users by default, "Cohort" filters by membership
  of cohorts whose members the user may list; sources add `picker\filter::boolean()`, `filter::select()` and
  `filter::autocomplete()` (values searched in an autocomplete source) with optional default values and `set_pinned()` in `get_filters()`;
  other picker families reuse them with `picker\filter_set` and the `tool_mulib/muform/pickerfilters` component.
- Cohort pickers: `cohortpicker`/`cohortpickermany` elements with a source in `<component>\muform\cohortpicker\*` extending
  `tool_mulib\muform\cohortpicker\base` (`get_context()`, optional `get_current_cohortids()`, `get_excluded_cohortids()`,
  `get_restriction_sql()`); the panel shows cohorts in the tree of course categories, a search keeps found cohorts
  in their branch. The base offers cohorts the user may use (`cohort_util::get_use_sql()`), tenant rules apply; forms
  that reveal members of picked cohorts add `cohort_util::get_view_members_sql()` in `get_restriction_sql()`.
  Never select cohorts with autocompletes, the only exception is the "Cohort" filter of user pickers.
- Multi-page forms: a `tool_mulib\muform\wizard_page` subclass `<component>\local\wizard\<name>\wizard` with one
  form class per stage in the same namespace and a thin entry script calling `execute()`; full page only (plain
  links, never dialogs). Stage forms use the `wizardcontinue` and `wizardback` buttons, wizard state never holds
  values that were not validated, valid data never skips a stage. Custom fields: the `customfields` element.
- Sessions and events with start and end: one `datetimerange` element (text `2026-11-06 23:30 – 01:30 +1`,
  value `['start' => int, 'end' => int]`, at most 24 hours, `set_default_duration()`), not two `datetime` elements.
- Behat: `I set the following muform fields [in the "dialog[open]" "css_element"]:` and
  `the following muform fields [...] match:`, first column is the element name or its exact label.

### Pitfalls

Each of these cost a debugging round when the plugins were migrated from moodleform:

- `$PAGE->url` must be the handler script itself with the parameters it reads, because muform posts to
  it. Legacy forms posted to `qualified_me()`, so wrong `set_url()` calls were harmless before. Check every
  converted page. With `admin_externalpage_setup()` pass the real URL as `$actualurl`.
- Hidden `id` elements are not needed: take ids from the page parameters in the handler
  (`$data->id = $record->id;`), never from submitted data.
- Option labels (`select`, `radios`, `checkboxes`) must be strings: convert `lang_string` with
  `array_map('strval', ...)`.
- Legacy APIs that call `$handler->instance_form_save($data)` for custom fields: pass them the data
  without `customfield_*` keys, then call `$form->get_element('customfields')->save($id)`.
- `filemanager` values are draft item ids, APIs that save drafts keep working; `editor` values already
  carry `@@PLUGINFILE@@` links, set the file area item id and call `save_area()` after inserts.
- `sharedkey` and `secret` return `null` for "unchanged": keep the stored value in the handler.
- Repeated rows: rebuild them in `definition()` from `$this->get_post_data()` plus `reload` buttons
  (see `tool_musudo\local\form\privileges_trait`), muform has no repeat element.
- Behat: dialogs are `dialog[open]`, use muform field steps (a guard fails core field steps on muform
  pages), datetime values are typed text such as `2025-11-05 09:00`, use element names when labels repeat.

## Other shared APIs

- `local\sql` - immutable SQL fragment with parameters. Write the whole query with `/* name */`
  placeholders, fill them with `replace_comment()` (throws when the placeholder is missing), use
  `wrap('AND ', '')` for optional parts (empty stays empty) and `sql::join()`. `?` and `:named`
  parameters may be mixed, collisions are renamed. `"mdl_table"` works as `{table}`. `sql::for_db(pgsql: ..., mariadb: ...[, db: $extdb])`
  picks the variant for `$DB` or the given database, MariaDB/MySQL get the mariadb variant, all other databases
  the pgsql one; plugins never mention "mysql".
- `local\context_map` - fast capability lookups on system, tenant, category and course levels without
  walking the context tree: `get_contexts_by_capability_query()` (subquery of context ids, usually
  `wrap("JOIN (", ")capctx ON capctx.id = x.contextid")`) and `get_contexts_by_capability_join()`
  (`['join' => sql, 'where' => sql]`). Cache tables are rebuilt by `context_map_builder` (cron, events).
  Activity and block contexts are never added to the map: speed is the whole point of it, and activities
  hold most role overrides and prohibits.
- `local\profilefield_cache` - cached custom profile field values (`tool_mulib_profilefield`: `charvalue`
  for text and menu, trimmed and cut to 255 chars, `intvalue` for checkbox and datetime, defaults included,
  visibility ignored). Use only fields that are ready (`is_ready()`), never read `user_info_data` for
  filtering. Persistent usage must be recorded with `set_references(component, area, itemid, fieldids)`,
  an empty list clears the item; referenced and identity fields cannot be removed from cache. Types come
  from handlers in `local\profilefield\*` registered via `hook\profilefield_handlers`. Values are kept
  current by observers and `task\profilefield_cron`; only profile field changes in admin UI queue adhoc
  population (keeps UI fast), install, upgrade and CLI populate inline. Admin UI is settings page
  `tool_mulib_profilefields` (Users > Accounts) with table setting `local\setting\profilefields`.
  Generators do not trigger profile field events, Behat uses `all custom profile fields are cached`.
- `local\mudb::upsert_record()` - concurrent-safe insert or update for tables with one unique index.
- `local\mulib::is_<plugin>_available()` / `is_<plugin>_active()` - optional sibling plugins: muprog,
  mucertify, mutrain, murelatio, muhome, mutenancy, mucatalog.
- `local\cohort_util` - cohort links and names and the two cohort permissions of MuTMS plugins:
  `get_use_sql()` / `can_use()` (may select the cohort in forms: visible, `moodle/cohort:view` or "Use cohort" access
  record of advanced cohorts) and `get_view_members_sql()` / `can_view_members()` (may list members:
  `moodle/cohort:assign` or `tool/mucohort:viewmembers` for standard cohorts, `tool/mucohort:viewmembers` or "View members"
  access record for advanced cohorts). Using does not include viewing of members. The rules are the same as in
  tool_mucohort cohort types and are described in its README; web services must use `can_use()` like the forms.
- `local\search_util` - search SQL for autocomplete sources (`get_search_query()`, cohort and user
  search), `get_tenant_related_users_where()`, `format_user_label()`.
- `local\user_util` - account status rules (`is_suspendable()`, `is_unsuspendable()`) and `suspend()` / `unsuspend()`
  through core APIs; no capability checks, callers check the capability that applies (site actions
  `moodle/user:update` in user context, tenant actions their own).
- `local\customfield_util::change_instances_context()` - move custom field data when an item moves.
- `local\vendor_loader::register($vendordir)` - Composer libraries in plugin `vendor/` directories;
  never include a plugin's `vendor/autoload.php`.
- `local\notification\*` - configurable notifications: per-plugin `manager` subclass, one
  `notificationtype` subclass per event, `util` for CRUD and placeholders.
- `local\extdb\*` - external database servers and queries, query types come from the
  `hook\extdb_query_classes` hook.
- `local\date_util`, `local\role_util`, `local\json_schema`, `local\plugindocs`.
- `output\header_actions` + `output\dropdown` - page header buttons and a menu of secondary actions:

```php
$actions = new \tool_mulib\output\header_actions(get_string('actions'));
$button = new \tool_mulib\output\muform\dialog\button($createurl, get_string('create', 'myplugin'), true);
$actions->add_button($button);
$link = new \tool_mulib\output\muform\dialog\link($reseturl, get_string('reset', 'myplugin'));
$link->set_form_size('sm');
$actions->get_dropdown()->add_dialog($link);
$actions->get_dropdown()->add_item(get_string('settings'), $settingsurl, new \core\output\pix_icon('i/settings', ''));
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));   // before $OUTPUT->header()
}
```

- `output\entity_details`, `output\url_clipboard` - detail lists and copy-to-clipboard URLs.
- `output\expandable_text` - long descriptions collapsed to one line (native `details`): `new expandable_text($fullhtml, $short)`,
  `$fullhtml` is always cleaned with `clean_text()`, html of templates needs `->allow_unsafe_rawhtml()`;
  `$short` is plain text always cut to one line by the browser; NULL or blank collapses full texts
  with tags or 100 and more characters, using their beginning as the short text; shorter plain text, also as one
  editor paragraph, is printed whole. `expandable_text::is_empty($html)` detects editor content without text and media
  (empty paragraphs, `&nbsp;`) - use it when storing descriptions as NULL.
- `output\report_filter_bar` + `output\report_pager` - filter bar with pinned filters above and pager with record
  count and download menu below system reports. Read `docs/reportbuilder.md` before adding a report, changing its
  filters or writing Behat for it.

```php
echo $OUTPUT->render(new report_filter_bar($report, pinned: ['program:fullname' => null, 'program:archived' => null]));
echo $report->output();
echo $OUTPUT->render(new report_pager($report));
```

### Entity tabs (secondary navigation)

Multi-page entity views repurpose Moodle secondary navigation as tabs: a class extending
`core\navigation\views\secondary` in `classes/navigation/views/` adds one node per tab in `initialise()`
(key = 5th argument of `add()`), and a static `setup_page($url, $record, $activetab)` on the entity class
calls `admin_externalpage_setup()`, `$PAGE->set_secondarynav()`, `$PAGE->set_secondary_active_tab()`
and `initialise()`. See the program and certification pages in tool_muprog and tool_mucertify.

## Database tables

`tool_mulib_notification`, `tool_mulib_notification_user`, `tool_mulib_extdb_server`,
`tool_mulib_extdb_query`, `tool_mulib_context_parent`, `tool_mulib_context_map` (caches),
`tool_mulib_muform_wizard` (wizard state, rows older than a day are deleted),
`tool_mulib_profilefield` (cache), `tool_mulib_profilefield_reference`.

Capability: `tool/mulib:useextdb`.

## Development

- In an mpd development VM (`/opt/mpd` exists) read `/opt/mpd/docs/moodle-agents.md` first: tools for
  install, upgrade, backups, PHPUnit, Behat and code checks, and what `mpd reset` destroys.
- Namespaces: `tool_mulib\muform\` forms, `tool_mulib\local\` internal APIs, `tool_mulib\output\`
  renderables, `tool_mulib\route\` REST routes (muform endpoints are browser only).
- JS lives in `js/esm/src` (TypeScript). Setup once in the Moodle root: `nvm install && npm install`.
  Build from the mulib directory (grunt then builds only this plugin): `nvm use && npx grunt esm`.
  From the Moodle root check with `npx eslint public/admin/tool/mulib/js/esm`,
  `npx jest public/admin/tool/mulib/js/esm/tests`, `npx stylelint public/admin/tool/mulib/styles.css`
  (no `!important`).
- PHP: `vendor/bin/phpunit --testsuite tool_mulib_testsuite`, `mpci phpcs --max-warnings 0 <plugin dir>`.
- Language packs of all MuTMS plugins follow `docs/translations.md`. `mulib::TRANSLATIONS` lists the
  languages every plugin must ship, `tests/phpunit/lang_test.php` fails on missing strings. In mpd,
  `mdl-lang-check <plugin dir>` also checks placeholders, HTML tags and order.
- Behat: mpd has four independent sites (`behat`, `behat1` to `behat3`), one run per site, so a large
  run such as `--tags=@MuTMS` can be split by component tags (`--tags="@MuTMS&&@tool_muprog,@tool_musudo"`)
  across sites; failures under parallel load are often flaky, re-run them alone before debugging.
  `php admin/cli/purge_caches.php` after adding classes, `behat-init` after adding steps, `behat-util --enable`
  after adding a feature file (each site has its own `-init` and `-util`); fail dumps in the behat dataroot
  `behat_faildump`; scenario line paths need absolute file paths; summaries say "1 scenario" in singular.
- Never change license headers or `@copyright` tags of existing files. New files get
  `@copyright 2026 Petr Skoda`. Petr bumps versions and commits.
