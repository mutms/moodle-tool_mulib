# tool_mulib - MuTMS shared library

Shared utility plugin for the MuTMS plugin suite. Does not provide end-user features on its own - it provides infrastructure that other MuTMS plugins depend on.

## Key shared APIs

### SQL fragment builder (`classes/local/sql.php`)

Immutable SQL fragment with named parameters. Supports `"mdl_tablename"` -> `{tablename}` auto-replacement, comment-based composition (`replace_comment()`), joining fragments (`sql::join()`), and wrapping (`wrap()`). Implements `ArrayAccess` for parameter access. Read-only `$sql` and `$params` properties.

Used extensively in all MuTMS plugins for building complex queries, especially in autocomplete handlers and management classes.

### Context map - fast permission lookups (`classes/local/context_map.php`)

Pre-computed context hierarchy cache for fast capability checks without walking the full Moodle context tree at query time. Ignores activity/block contexts - operates on system, tenant, category, course levels only.

- `get_contexts_by_capability_query()` - subquery returning context IDs where user has a capability
- `get_contexts_by_capability_join()` - SQL join/where fragments for capability filtering

Cache tables `tool_mulib_context_parent` and `tool_mulib_context_map` are rebuilt by `context_map_builder` via daily cron and event observers (user/tenant/category/course create/update/delete).

### Concurrent-safe upsert (`classes/local/mulib.php`)

`mulib::upsert_record()` - insert-or-update for tables with exactly one unique index. Native `ON CONFLICT` / `ON DUPLICATE KEY` for PostgreSQL and MySQL, with fallback for other databases. Handles race conditions between concurrent inserts.

### Plugin availability checks (`classes/local/mulib.php`)

Static methods to check if sibling plugins are installed and active:
- `is_muprog_available()` / `is_muprog_active()` - programs
- `is_mucertify_available()` / `is_mucertify_active()` - certifications
- `is_mutrain_available()` / `is_mutrain_active()` - training credits
- `is_murelatio_available()` / `is_murelatio_active()` - teams/supervisors
- `is_muhome_available()` / `is_muhome_active()` - custom homepages
- `is_mutenancy_available()` / `is_mutenancy_active()` - multi-tenancy
- `is_mucatalog_available()` / `is_mucatalog_active()` - catalogue

Use these to conditionally enable features that depend on optional plugins.

### Mustache-safe string encoding (`classes/local/mulib.php`)

`mulib::clean_string()` - encodes all dangerous characters as numeric HTML entities. Safe for both `{{ }}` and `{{{ }}}` Mustache tags because the result is not modified by `s()`.

### Notification framework (`classes/local/notification/`)

Base classes for configurable email/message notifications across MuTMS plugins:

- `notification\manager` - abstract base for per-plugin notification management (CRUD, import, rendering). Subclass in each plugin and implement `get_all_types()`.
- `notification\notificationtype` - abstract base for individual notification types. Subclass for each event (e.g. allocation, completion, due date). Implement `get_provider()`, `get_default_subject()`, `format_subject()`, etc.
- `notification\util` - shared CRUD operations, placeholder replacement, multilang filtering.

Tables: `tool_mulib_notification` (notification config), `tool_mulib_notification_user` (delivery tracking).

Example: `tool_muprog\local\notification\base` extends `notificationtype`, then concrete types like `allocation`, `completion`, `deallocation` extend that base.

### AJAX modal forms (`classes/local/ajax_form_trait.php`, `classes/local/ajax_form.php`)

Modal dialog system for CRUD operations without custom JavaScript. Forms render inside Bootstrap modals via AJAX, submit via AJAX, and trigger a post-submit action (page reload, redirect, or nothing).

#### Form class

Extend `tool_mulib\local\ajax_form` (which extends `moodleform` + adds `ajax_form_trait`). Override `definition()` to define fields. Alternatively, add `ajax_form_trait` to any existing `moodleform`.

Trait methods:
- `is_ajax_request()` — true when `AJAX_SCRIPT` constant is defined
- `ajax_form_cancelled($returnurl)` — sends JSON cancel response with redirect URL
- `ajax_form_submitted($returnurl)` — sends JSON success response with redirect URL
- `ajax_form_render()` — sends JSON with rendered form HTML + JS bundle

#### Management page pattern

Each modal form needs a PHP page that defines `AJAX_SCRIPT`, creates the form, and handles the three states:

```php
define('AJAX_SCRIPT', true);
require('../../../config.php');
// ... require_login, require_capability, load record ...

$form = new \myplugin\local\form\my_form(null, ['record' => $record]);
$returnurl = new core\url('/myplugin/management/page.php', ['id' => $id]);

if ($form->is_cancelled()) {
    $form->ajax_form_cancelled($returnurl);
}
if ($data = $form->get_data()) {
    // ... process data ...
    $form->ajax_form_submitted($returnurl);
}
$form->ajax_form_render();
```

#### Trigger components (output classes)

Three trigger types, all extending `output\ajax_form\action`:
- `output\ajax_form\button` — renders as a `<button>` that opens modal on click
- `output\ajax_form\link` — renders as a text `<a>` link (also `create_report_action()` for Report Builder)
- `output\ajax_form\icon` — renders as a pix icon link

Constructor: `new button($url, $label)` / `new link($url, $label, $description)` / `new icon($url, $label, $pixicon)`

Configuration methods on all trigger types:
- `set_submitted_action($action)` — what happens after successful form submit:
    - `SUBMITTED_ACTION_RELOAD` — reload current page (most common)
    - `SUBMITTED_ACTION_REDIRECT` — redirect to form's return URL
    - `SUBMITTED_ACTION_NOTHING` — close modal, no navigation
- `set_form_size($size)` — modal width: `'sm'` for confirmations, `'lg'` or `'xl'` for complex forms, default for standard
- `set_modal_title($title)` — custom modal header text
- `set_icon($pixicon)` — optional icon
- `add_class($cssclass)` — e.g. `'text-danger'` for delete icons
- `set_primary($bool)` — primary button styling (button trigger only)

Render triggers via `$OUTPUT->render($trigger)` or `$this->render($trigger)` in renderers.

#### Typical CRUD flow

1. **Render page** with trigger buttons/icons pointing to modal form URLs
2. **User clicks trigger** → JS opens Bootstrap modal, fetches form via AJAX POST
3. **Form renders in modal** → server returns `{status: 'render', html, javascript}`
4. **User submits** → modal serializes form, POSTs to same URL
5. **Server processes** → returns `{status: 'submitted', redirecturl}` (or re-renders if validation fails)
6. **Post-submit action** → modal executes reload/redirect/nothing

No custom AMD modules needed — `amd/src/ajax_form/modal.js` handles everything.

JS module `amd/src/ajax_form/modal.js` handles the client side.

### Tabbed detail pages (secondary navigation pattern)

Moodle secondary navigation can be repurposed as entity-level tabs (e.g. Plan → Details | Structure | Courses). This is a common MuTMS pattern for multi-page entity views.

#### Tab definition class

Create a class extending `core\navigation\views\secondary` in `classes/navigation/views/`:

```php
class plan_secondary extends \core\navigation\views\secondary {
    protected stdClass $record;

    public function __construct(\moodle_page $page, stdClass $record) {
        parent::__construct($page);
        $this->record = $record;
    }

    public function initialise(): void {
        $this->id = 'secondary_navigation';
        $id = $this->record->id;

        $url = new url('/myplugin/management/entity.php', ['id' => $id]);
        $this->add(get_string('tab_details', 'myplugin'), $url,
            \navigation_node::TYPE_SETTING, null, 'entity_details');

        $url = new url('/myplugin/management/entity_items.php', ['id' => $id]);
        $this->add(get_string('tab_items', 'myplugin'), $url,
            \navigation_node::TYPE_SETTING, null, 'entity_items');

        $this->scan_for_active_node($this);
        $this->initialised = true;
    }
}
```

#### Page setup helper

Create a static setup method on the entity class that wires up the page, tabs, and active tab:

```php
public static function setup_page(url $pageurl, stdClass $record, string $activetab): void {
    global $PAGE, $CFG;
    require_once($CFG->libdir . '/adminlib.php');

    admin_externalpage_setup('myplugin_entities', '', ['id' => $record->id],
        $pageurl, ['nosearch' => true]);

    $PAGE->set_pagelayout('report');
    $PAGE->set_title(s($record->name));
    $PAGE->set_heading(s($record->name));

    $secondarynav = new \myplugin\navigation\views\entity_secondary($PAGE, $record);
    $PAGE->set_secondarynav($secondarynav);
    $PAGE->set_secondary_active_tab($activetab);
    $secondarynav->initialise();

    $PAGE->navbar->add(s($record->name));
}
```

Each page calls the helper with its tab key:

```php
MyEntity::setup_page($url, $record, 'entity_items');
```

The tab key (4th param of `$this->add()`) must match the string passed to `set_secondary_active_tab()`.

### Form autocomplete base (`classes/external/form_autocomplete/base.php`)

Abstract base for AJAX autocomplete form fields (extends `external_api`). Subclass in each plugin for entity-specific search (users, cohorts, programs, etc.). Override `execute()` for search logic, `validate_value()` for validation. Built-in support for multiple selection, required fields, placeholders.

Concrete implementations in tool_mulib: `user`, `cohort`, `categorycontext`, `extdb_query_contextid`.

### UI output components (`classes/output/`)

- `entity_details` - key-value detail rendering
- `url_clipboard` - copy-to-clipboard URL widget

### Page header actions (`classes/output/header_actions.php`, `classes/output/dropdown.php`)

Combo of prominent buttons + a dropdown menu for secondary actions. Rendered into Boost's page header via `$PAGE->add_header_action()`.

```php
use tool_mulib\output\header_actions;
use tool_mulib\output\ajax_form\button;

$actions = new header_actions(get_string('actions'));

// Primary action — visible button.
$url = new core\url('/myplugin/management/create.php', ['id' => $id]);
$btn = new button($url, get_string('create', 'myplugin'));
$btn->set_submitted_action($btn::SUBMITTED_ACTION_RELOAD);
$btn->set_primary(true);
$actions->add_button($btn);

// Secondary action — tucked in dropdown as a plain link.
$url = new core\url('/admin/settings.php', ['section' => 'myplugin_settings']);
$actions->get_dropdown()->add_item(
    get_string('settings'),
    $url,
    new \core\output\pix_icon('i/settings', '')
);

// Secondary action — dropdown item that opens an AJAX modal form.
$url = new core\url('/myplugin/management/reset.php', ['id' => $id]);
$link = new \tool_mulib\output\ajax_form\link($url, get_string('reset', 'myplugin'), '');
$link->set_submitted_action($link::SUBMITTED_ACTION_RELOAD);
$link->set_form_size('sm');
$actions->get_dropdown()->add_ajax_form($link);

// Render into page header (must be called BEFORE $OUTPUT->header()).
if ($actions->has_items()) {
    $PAGE->add_header_action($OUTPUT->render($actions));
}
```

**`header_actions`** API:
- `add_button($renderable)` — add a prominent button (any renderable or HTML string)
- `get_dropdown()` — returns the `dropdown` instance for adding secondary items
- `has_items()` — true if any buttons or dropdown items exist

**`dropdown`** API:
- `add_item($label, $url, $icon = null, $class = '')` — plain navigation link
- `add_ajax_form($link)` — AJAX modal form trigger (accepts `ajax_form\link` instance, auto-adds `dropdown-item` class)
- `add_divider()` — visual separator between groups of items
- `has_items()` — true if any items exist

### External database integration (`classes/local/extdb/`)

Connect to external databases and run queries:
- `extdb\server` - CRUD for database server connection configs (table `tool_mulib_extdb_server`)
- `extdb\query` - abstract query class, subclass for each query type (table `tool_mulib_extdb_query`)
- `extdb\query_manager` - DI service collecting query types via `hook\extdb_query_classes` hook
- `extdb\pdb` - PDO connection wrapper
- `extdb\rs` - result set wrapper

Used by tool_muprog for external database-driven program allocations.

### Other utilities

- `local\date_util` - `format_event_date()` for date range formatting; `TIMESTAMP_FOREVER` constant
- `local\role_util` - `get_contextlevel_roles_menu()` for role selection dropdowns
- `local\json_schema` - JSON Schema validation via Opis library
- `local\mudb` - alias for `mulib` upsert functionality (dedicated DB operations class)

## Database tables

- `tool_mulib_notification` - notification configuration per component/instance
- `tool_mulib_notification_user` - tracks notification delivery to users
- `tool_mulib_extdb_server` - external database server connections
- `tool_mulib_extdb_query` - external database query definitions
- `tool_mulib_context_parent` - context parent cache (rebuilt by cron)
- `tool_mulib_context_map` - context distance map cache (rebuilt by cron)

## Capabilities

- `tool/mulib:useextdb` - use external database queries (manager-only, category context)

## Coding conventions

- Namespace: `tool_mulib\local\` for internal APIs, `tool_mulib\external\` for web services, `tool_mulib\output\` for renderables
- SQL fragments use `sql` class, never raw string concatenation with parameters
- All notification types extend `tool_mulib\local\notification\notificationtype`
- All autocomplete fields extend `tool_mulib\external\form_autocomplete\base`