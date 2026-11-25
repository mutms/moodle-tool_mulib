# Pickers

`tool_mulib\picker` lets users pick values in a panel over the right half of the screen, the page stays
visible on the left. Panels show what a plain autocomplete cannot: columns, filters, paging or a tree.
The panel is a native modal `<dialog>` of its own, so it also works over muform dialogs, wizards and
side panels of reports.

Pickers are independent of forms. muform elements (`picker`, `pickermany`, `userpicker`, `userpickermany`,
`cohortpicker`, `cohortpickermany`, see [muform](muform.md#pickers)) show the selected values as pills and
open the panel; other UIs, for example report filters, open panels with `openPanel()` from
`tool_mulib/picker/panel`.

## Families

Pickers come in families. A family is three things:

- an abstract source base extending `tool_mulib\picker\base`, with one subclass per usage;
- a panel component (React), the default export of the module named by `get_panel()`;
- the family's own endpoints that the panel talks to. Panels differ a lot (a paged search with filters,
  a whole tree loaded at once), so tool_mulib has no generic picker endpoint.

The source works like an autocomplete source. Its constructor takes plain ids and does the access
control, and `get_args()` returns them. `labels()` returns label HTML for values that may be
selected, and `validate()` can refuse a value with a reason - or `validate_value()` one value at a
time, the same as in autocomplete sources. `get_panel_strings()` gives the panel
its strings and must include `title`. One source serves both elements; the panel gets `multiple`
as a prop.

```php
namespace tool_x\picker\category;

abstract class base extends \tool_mulib\picker\base {
    final public function get_panel(): string {
        return 'tool_x/picker/category/panel';
    }

    final public function get_panel_strings(bool $multiple): array {
        return ['title' => get_string($multiple ? 'categories' : 'category'), 'search' => get_string('search')];
    }

    // Shared labels(), the tree for the panel, ...
}

final class program_category extends base {
    public function __construct(private int $programid) {
        require_capability('tool/x:edit', program::get_context($programid));
    }

    public function get_args(): array {
        return [$this->programid];
    }
}

$form->add(new pickermany('categoryids', get_string('categories'), new program_category($programid)));
```

Values are opaque strings of letters, digits and `_-.:@`, the same as autocomplete values, so ids
and tokens such as `category:5` both work. Commas separate values in the browser. A value without a
label is rejected on submit, which is how permissions are enforced. `are_allowed($values)` of a source
tells if all the values may be selected; forms use it in `validation()` to check values again with a source
created from submitted data, for example for a newly selected category.

Family endpoints recreate the source from the class name and arguments sent by the browser. Call
`base::require_browser_session()` first, then `instantiate()` on the family base: it accepts only
instantiable subclasses of that base with scalar arguments, living in the namespace of the family base
below any installed component (`tool_x\picker\category\base` accepts `tool_y\picker\category\abc`).

```php
$source = \tool_x\picker\category\base::instantiate($body['source'], $body['args']);
```

The panel component gets `PickerPanelProps` from `tool_mulib/picker/panel`: `source`
(`class` and `args`), `multiple`, `selected` (values already in the field, never offered again),
`strings`, `onPick(items)` and `onClose()`. `onPick` returns the picked values with their label HTML,
so the element never fetches labels itself. `openPanel()` from the same module opens a panel
outside forms, for example in an editor that adds several items at once.

## User picker

The user picker family offers Moodle users in a panel:
one page of users at a time with full names and the identity fields visible in the source context
(`showuseridentity` and `moodle/site:viewuseridentity`), searching names and those fields. Form elements
`userpicker` and `userpickermany` use it instead of a user autocomplete when users need to see who they pick,
for example when allocating users.

The source says where the users are picked from, the same way as autocomplete sources. It extends
`tool_mulib\picker\user\base`, lives in `<component>\picker\user\` and serves both elements.
Deleted, unconfirmed and guest users are never offered and tenant rules apply, the same as in the user
autocomplete. Values are user ids.

```php
namespace tool_muprog\picker\user;

final class source_manual_allocate_users extends \tool_mulib\picker\user\base {
    private context $context;

    public function __construct(private readonly int $programid) {
        $this->context = program::get_context($programid);
        require_capability('tool/muprog:allocate', $this->context);
    }

    public function get_args(): array {
        return [$this->programid];
    }

    protected function get_context(): context {
        return $this->context;
    }

    protected function get_candidates_sql(): ?sql {
        return new sql("NOT EXISTS (SELECT 'x' FROM {tool_muprog_allocation} pa
                                     WHERE pa.userid = u.id AND pa.programid = :aprogid)", ['aprogid' => $this->programid]);
    }
}

$form->add(new userpickermany('users', get_string('users'), new source_manual_allocate_users($programid)));
```

`get_candidates_sql()` limits who may be picked (all users by default), `get_selected_sql()` decides
which stored values stay valid (the candidates by default), and `allow_suspended_users()` refuses
suspended users when it returns false. `get_current_userids()` lists users of the edited item that
stay valid as the current value whatever the candidates, selected and tenant rules say, for example
the current supervisor of an edited relation; the search does not offer them. `site_users` offers all users of a context to users with
`moodle/user:viewalldetails`. The search endpoint is `tool_mulib\route\api\picker\user`.
In Behat, table values are user ids, full names or identity field values such as emails; the filters are
removed before a user is looked up, so suspended users are found too.

Filters are pills below the search field that look and work like the report filter bar: a pill opens
the editor of its value, the cross removes it, filters without a value are in "Add filter" and
"Reset filters" returns to the default values. Every source that allows suspended users has the
pinned "Active" filter, set to "Yes" when the panel opens, so suspended users are hidden until the
filter is removed ("Active: Any") or changed to "No". The "Cohort" filter in "Add filter" lists members
of one cohort: only cohorts whose members the current user may list are found, see
`cohort_util::get_view_members_sql()`. Sources add their own filters in `get_filters()`:

```php
protected function get_filters(): array {
    $filters = parent::get_filters();
    $filters[] = filter::boolean(
        'completed',
        get_string('completed', 'tool_muprog'),
        new sql("EXISTS (SELECT 'x' FROM {tool_muprog_allocation} ca WHERE ca.userid = u.id AND ca.timecompleted IS NOT NULL)"),
        new sql("NOT EXISTS (SELECT 'x' FROM {tool_muprog_allocation} ca WHERE ca.userid = u.id AND ca.timecompleted IS NOT NULL)"),
    );
    $filters[] = filter::select(
        'auth',
        get_string('authentication'),
        ['manual' => 'Manual accounts', 'oauth2' => 'OAuth 2'],
        fn(string $value): sql => new sql('u.auth = :auth', ['auth' => $value]),
    )->set_pinned();
    return $filters;
}
```

`tool_mulib\picker\filter::boolean()` has Yes and No values, `filter::select()` a list of options
with plain text labels (a search box is added for more than 10); conditions use table alias `u`. The
last argument is the default value, without it the filter starts in "Add filter".
`filter::autocomplete($key, $label, $source, $where)` is for values that cannot be listed: the editor searches
an [autocomplete](muform.md#autocomplete) source through the autocomplete endpoint and only values known to `labels()`
of the source are accepted, so the source is the access control of the filter values - the "Cohort" filter
uses `tool_mulib\muform\autocomplete\member_cohorts`. `set_pinned()` keeps
the pill in the bar when it has no value, as "Authentication: Any" (the second argument replaces "Any").
Keys are unique in the source: lowercase letters, digits and underscores. Filters only narrow down the
list of candidates, `validate()` and `get_selected_sql()` decide what may be stored.

The filters are not specific to users, any picker family can have them:

- the family base lets sources define `tool_mulib\picker\filter` objects with conditions on its own table aliases;
- the family endpoint creates `new filter_set($filters, filter_set::clean_values($body['filters'] ?? null))`,
  adds `get_sqls()` to its query (or reads `get_value($key)`) and returns `export()` in the answer;
- `get_panel_strings()` of the family adds `filter_set::get_panel_strings()`;
- the panel renders `<Filters filters strings onChange onCancel />` from `tool_mulib/picker/filters` and
  sends `filters` as an object of values by keys once the user changed one; until then the endpoint
  applies the default values.

## Cohort picker

The cohort picker family is used by form elements `cohortpicker` and `cohortpickermany`. The panel shows cohorts inside the tree of
course categories they belong to: "System" and each category are headings, the cohorts are listed below
their category. Only branches with cohorts are shown, and a search in names, ID numbers and descriptions
keeps the found cohorts in their place of the tree, so cohorts with the same name can be told apart.
At most 200 cohorts are listed, with more the panel asks to refine the search.

The source extends `tool_mulib\picker\cohort\base`, lives in `<component>\picker\cohort\`
and serves both elements. Values are cohort ids.

The source does the access control of the edited item in its constructor, the base decides which cohorts
are offered - the cohorts the current user may **use**, see `cohort_util::get_use_sql()`:

| Cohort | May be picked |
|---|---|
| standard cohorts and cohorts of other plugins | visible cohorts, or `moodle/cohort:view` in the cohort context |
| advanced cohorts of tool_mucohort (curated, rule-based) | the same, or an access record with "Use cohort" |

With multi-tenancy cohorts of other tenants are not offered. Using a cohort does not include seeing its
members, that is a separate and stricter permission, see `cohort_util::get_view_members_sql()`. A form that
reveals members of the picked cohorts must require it too, the compare tool of tool_mucohort does it with
`get_restriction_sql()` returning `cohort_util::get_view_members_sql('ch', (int)$USER->id)`. Both rules are
described in the README of tool_mucohort.

```php
namespace tool_muprog\picker\cohort;

final class source_cohort_edit_cohortids extends \tool_mulib\picker\cohort\base {
    private context $context;

    public function __construct(private readonly int $programid) {
        $this->context = program::get_context($programid);
        require_capability('tool/muprog:edit', $this->context);
    }

    public function get_args(): array {
        return [$this->programid];
    }

    protected function get_context(): context {
        return $this->context;
    }

    protected function get_current_cohortids(): array {
        global $DB;
        // Cohorts already allocated stay valid whatever changed since.
        $source = $DB->get_record('tool_muprog_source', ['programid' => $this->programid, 'type' => 'cohort']);
        return $source ? $DB->get_fieldset('tool_muprog_src_cohort', 'cohortid', ['sourceid' => $source->id]) : [];
    }
}

$form->add(new cohortpickermany('cohortids', get_string('cohorts', 'cohort'), new source_cohort_edit_cohortids($programid)));
```

`get_context()` is the context of the edited item, tenant rules use it. `get_current_cohortids()` lists cohorts
already used by the edited item, they stay valid as the current value; `get_excluded_cohortids()` lists cohorts
that must never be picked, for example the edited cohort itself; `get_restriction_sql()` adds a condition cohorts
must match to be picked (aliases `ch` for the cohort and `ctx` for its context), current cohorts are not affected.
`context_cohorts` offers all cohorts the user may use for an item in a context. The search endpoint is
`tool_mulib\route\api\picker\cohort`.
In Behat, table values are cohort names, ID numbers or ids.
