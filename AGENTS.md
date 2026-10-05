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
- Autocomplete: sources in `<component>\muform\autocomplete\*` and `<component>\muform\autocompletemany\*`
  extend `tool_mulib\muform\autocomplete(many)\base`. The constructor takes scalar ids and does the access
  control, `get_args()` returns them, `search()`, `label()`/`labels()` and `validate()` decide what may be
  selected. Reuse the traits in `classes/muform/util/autocomplete/`: `user_trait`, `cohort_trait`,
  `category_context_trait`, and `local\search_util` for search SQL and user labels. Tags use a `<component>\muform\tagarea\*` class the same way.
- Multi-page forms: `tool_mulib\muform\wizard`; custom fields: the `customfields` element.
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
  parameters may be mixed, collisions are renamed. `"mdl_table"` works as `{table}`.
- `local\context_map` - fast capability lookups on system, tenant, category and course levels without
  walking the context tree: `get_contexts_by_capability_query()` (subquery of context ids, usually
  `wrap("JOIN (", ")capctx ON capctx.id = x.contextid")`) and `get_contexts_by_capability_join()`
  (`['join' => sql, 'where' => sql]`). Cache tables are rebuilt by `context_map_builder` (cron, events).
- `local\mudb::upsert_record()` - concurrent-safe insert or update for tables with one unique index.
- `local\mulib::is_<plugin>_available()` / `is_<plugin>_active()` - optional sibling plugins: muprog,
  mucertify, mutrain, murelatio, muhome, mutenancy, mucatalog.
- `local\search_util` - search SQL for autocomplete sources (`get_search_query()`, cohort and user
  search), `is_cohort_visible()`, `get_tenant_related_users_where()`, `format_user_label()`.
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

### Entity tabs (secondary navigation)

Multi-page entity views repurpose Moodle secondary navigation as tabs: a class extending
`core\navigation\views\secondary` in `classes/navigation/views/` adds one node per tab in `initialise()`
(key = 5th argument of `add()`), and a static `setup_page($url, $record, $activetab)` on the entity class
calls `admin_externalpage_setup()`, `$PAGE->set_secondarynav()`, `$PAGE->set_secondary_active_tab()`
and `initialise()`. See the program and certification pages in tool_muprog and tool_mucertify.

## Database tables

`tool_mulib_notification`, `tool_mulib_notification_user`, `tool_mulib_extdb_server`,
`tool_mulib_extdb_query`, `tool_mulib_context_parent`, `tool_mulib_context_map` (caches),
`tool_mulib_muform_wizard` (wizard state, rows older than a day are deleted).

Capability: `tool/mulib:useextdb`.

## Development

- In an mpd development VM (`/opt/mpd` exists) read `/opt/mpd/docs/moodle-agents.md` first: tools for
  install, upgrade, backups, PHPUnit, Behat and code checks, and what `mpd reset` destroys.
- Namespaces: `tool_mulib\muform\` forms, `tool_mulib\local\` internal APIs, `tool_mulib\output\`
  renderables, `tool_mulib\route\` REST routes (muform endpoints are browser only).
- JS lives in `js/esm/src` (TypeScript), build from the Moodle root:
  `npx grunt esm`, check with `npx eslint public/admin/tool/mulib/js/esm`,
  `npx jest public/admin/tool/mulib/js/esm/tests`, `npx stylelint public/admin/tool/mulib/styles.css`
  (no `!important`).
- PHP: `vendor/bin/phpunit --testsuite tool_mulib_testsuite`, `mpci phpcs --max-warnings 0 <plugin dir>`.
- Behat: one run at a time (single test site); `php admin/cli/purge_caches.php` after adding classes,
  `behat-init` after adding steps; fail dumps in the behat dataroot `behat_faildump`; summaries say
  "1 scenario" in singular.
- Never change license headers or `@copyright` tags of existing files. New files get
  `@copyright 2026 Petr Skoda`. Petr bumps versions and commits.
