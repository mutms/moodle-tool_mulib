# muform - server-side rendered forms

`tool_mulib\muform` is a replacement for legacy Moodle forms. A form is a PHP class,
each element is a PHP class with a mustache template and an optional ES module.
The server is the only authority: it parses, validates and renders; the browser
only adds convenience (native validation hints, hiding, disabling, focus handling).

## The four rules

1. **An element value never changes after the element is attached to a form.**
   `parse_value()` runs once inside `$form->add()`. From that moment `get_value()`
   returns the same thing to the definition, to validators, to the handler and to the template.
2. **Hiding and disabling are cosmetic.** Display rules only control what the browser
   shows. Hidden elements are still submitted and still validated. Disabled elements are
   not submitted by the browser, so the server falls back to current data, then default.
   The backend never skips anything because of a display rule.
3. **Extra validation happens after the form is finalised.** Validators run in the
   constructor after `definition()` and the `muform_definition` hook, so they can
   see every element, every value and every display rule.
4. **As little public API as possible.** If something is not public, that is on purpose.
   Do not work around it; ask for the API.

Everything below follows from these rules.

## Lifecycle

`new my_form($targeturl, $currentdata, $extradata)` does all the work:

1. Reads `$_POST` if it carries this form's `__formid` and a valid `__sesskey`; both helper
   fields start with `__` so they can never clash with element names. Without a session key
   the request is treated as never submitted.
2. Calls `definition()`. Each `add()` parses the element value immediately
   (POST, then current data, then element default), so `definition()` may branch
   on `$element->get_value()`.
3. Dispatches the `tool_mulib\hook\muform_definition` hook; plugins may add elements,
   validators, display rules or swap templates.
4. Finalises the form. No element or rule can be added afterwards.
5. Resolves the state from submitted values: any element whose `is_cancelling()` returns true
   and whose value is non-empty wins, then the same for `is_reloading()`, then `is_submitting()`.
   The shipped `cancel`, `reload` and `submit` buttons are just the usual case; a checkbox
   "add more rows" may declare itself reloading so that checking it resubmits without validation.
6. Validates on submit: element parse errors, then `set_required()`, then validators,
   then `validation()`.

Then the handler asks `is_cancelled()`, `is_reloaded()`, `get_data()`
(non-null only when valid) and finally `render($OUTPUT)`.

## Writing a form

```php
namespace local_myplugin\form;

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\section;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;
use tool_mulib\muform\validator\required_if_visible;

final class item_edit extends form {
    #[\Override]
    protected function definition(): void {
        $this->add(new section('general', get_string('general')));
        $this->add(new hidden('id'), 'general');

        $name = (new text('name', get_string('name'), ['maxlength' => 100]))
            ->set_required(true)
            ->add_help_button('name', 'local_myplugin');
        $this->add($name, 'general');

        $this->add(new number('priority', get_string('priority'), ['min' => 0, 'max' => 10]), 'general');
        $this->add(new checkbox('notify', get_string('notify'), get_string('notify_desc')), 'general');

        // Only shown when notify is checked, required only when shown.
        $message = (new textarea('message', get_string('message'), ['rows' => 5]))
            ->set_required_marker(true)
            ->add_validator(new required_if_visible());
        $this->add($message, 'general');
        $this->get_display_manager()->hide_if('message', 'notify', 'notchecked');

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if ($data['name'] === 'admin') {
            $allerrors['name'][] = get_string('reservedname', 'local_myplugin');
        }
    }
}
```

Always add the submit button first: browsers use the first submit button of the form for Enter
key submission; `buttons` warns in developer debugging when a cancel or reload button comes first.
Reload buttons placed before the button row (for example "Delete row" in repeated rows) are fine:
with JavaScript, Enter in a single line input always submits through the submit button. To show the submit button last use the `buttons-reversed`
template variant: `$buttons->set_template('tool_mulib/muform/element/buttons-reversed')`.

Style: build the element into a variable with each chained setter on its own line,
then `add()` it. Chain nothing inside `add()`. Mark every overridden method with the
`#[\Override]` attribute instead of repeating the parent docblock.

## Handling a form

```php
require('../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = \core\context\system::instance();
require_capability('local/myplugin:manage', $context);

$item = $DB->get_record('local_myplugin_item', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url(new \core\url('/local/myplugin/edit.php', ['id' => $item->id]));
$PAGE->set_context($context);
$returnurl = new \core\url('/local/myplugin/index.php');

$form = new item_edit($PAGE->url, $item);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    // $data is stdClass with typed values: int for number, string for text, 1/0 for checkbox.
    $DB->update_record('local_myplugin_item', $data);
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $form->render($OUTPUT);
echo $OUTPUT->footer();
```

Access control belongs to the handler, never to the form: `require_login()` and
`require_capability()` run before the form is constructed, so nothing is parsed for
users who may not edit. The sesskey is checked by the form itself.

There is no "reload" special case: a reloading element just makes `get_data()` return null
and the form re-renders with the submitted values, so `definition()` can add more elements
based on them.

Forms answered with a file (exports) use the `download` button instead of `submit`: it validates
like `submit` but posts the form to a new browser window, also from dialogs, so the form stays open
and usable after the download. The handler sends the file and stops, validation errors that only
the server finds render in that new window.

## Elements

| Element            | Constructor                                | Value in `get_data()`     | Empty means      |
|--------------------|--------------------------------------------|---------------------------|------------------|
| `text`             | `(name, label, attributes)`                | `string`, cleaned         | blank after trim |
| `textarea`         | `(name, label, attributes)`                | `string`, cleaned, `\n` line ends | blank after trim |
| `number`           | `(name, label, attributes)`                | `int`, `float` or `null`  | `null`           |
| `datetime`         | `(name, label, attributes, displayformat = null)` | `int` timestamp or `null` | `null`     |
| `duration`         | `(name, label, units = ['d', 'h', 'i'])`   | `int` seconds             | `0`              |
| `dateinterval`     | `(name, label, units = ['y', 'm', 'w', 'd'])` | ISO 8601 duration `string` or `null` | `null` |
| `secret`           | `(name, label, attributes, allowclear = false)` | `null` keep, `''` cleared, `string` new | see below |
| `sharedkey`        | `(name, label, attributes, allowclear = false)` | `null` keep, `''` cleared, `string` new | see below |
| `filemanager`      | `(name, label, maxfiles = null, acceptedtypes = null, allowsubdirs = false)` | `int` draft item id | no files |
| `editor`           | `(name, label, maxfiles = 0, allowsubdirs = false, attributes)` | `string` text, plus `<name>format` and `<name>draftitemid` | blank text |
| `autocomplete`     | `(name, label, source, attributes)`        | `string` value or `null`  | `null`           |
| `autocompletemany` | `(name, label, source, attributes)`        | `string[]` values         | `[]`             |
| `tags`             | `(name, label, tagarea, attributes)`       | `string[]` tag names      | `[]`             |
| `checkbox`         | `(name, label, text, attributes)`          | `1` or `0`                | `0`              |
| `radios`           | `(name, label, options, inline)`           | option key or `null`      | `null`           |
| `checkboxes`       | `(name, label, options, inline)`           | `string[]` of option keys | `[]`             |
| `select`           | `(name, label, options, attributes)`       | option key or `null`      | `null`           |
| `multiselect`      | `(name, label, options, attributes)`       | `string[]` of option keys | `[]`             |
| `hidden`           | `(name, type = null)`                      | `string`, `int` for `param::INT`, or `null` | `null` |
| `info`             | `(name, label, default = null, format = info::STRING)` | not returned  |                  |
| `inforawhtml`      | `(name, label, html)`                      | not returned              |                  |
| `section`          | `(name, label)`                            | not returned              |                  |
| `customfields`     | `(name, handler, instanceid)`              | not returned, see [Custom fields](#custom-fields) | |
| `buttons`          | `(name)`                                   | not returned              |                  |
| `submit`, `cancel` | `(name = 'submit'/'cancel', label = null)` | not returned              |                  |
| `reload`           | `(name, label)`                            | not returned              |                  |
| `download`         | `(name = 'download', label = null)`        | not returned              |                  |

`text` and `textarea` clean values with `core\param::TEXT`: all tags are stripped except the
multilang `<span lang="xx" class="multilang">` and `<lang>` tags, which is what names and titles
stored in the database expect. Use the `type` attribute `rawtext` for JSON, code or other content
that must be kept exactly as submitted; it renders as a plain text input. Other `text` types
(`email`, `url`, `tel`, `search`) are cleaned and then validated.

`info` displays text from current data (the `name` key) or its default. How it is shown is one of
its own constants, not a Moodle `FORMAT_*` value: `info::STRING` (default) uses `format_string()`, for
names, titles and other multilang values; `info::PLAIN` escapes with `s()` and keeps line breaks, for
idnumbers, URLs and codes;
`info::HTML` and `info::MARKDOWN` use `format_text()` with that format; `info::TEXTFORMAT` uses
`format_text()` with the format stored in current data (`nameformat`), `FORMAT_HTML` when there is
none. This is the only place muform formats anything, because displayed data comes in many
formats. `inforawhtml` displays trusted HTML given in the definition, never data; with an empty
label it spans the whole row.

`number` renders a text input with `inputmode="numeric"` (integers) or `"decimal"`, never
`type="number"`: spinner arrows and mouse wheel scrolling would change values unnoticed. The
`decimals` attribute is the number of decimal places: 0 (default) returns `int`, more returns
`float` with at most that many places; there is no other step, `min` and `max` must fit the
decimal places (coding exception otherwise). Decimal values accept a comma as the decimal separator
and ignore trailing zeros, exponents and thousands separators are invalid; invalid text is shown
again with the error. The format is checked by the input `pattern`, the range by the element
JavaScript and everything again on the server. Anything else (steps like 0.5, units, ranges of
text) belongs to a `text` element with a validator. `duration` and `dateinterval` unit inputs are
numeric text inputs too.

`datetime` is the only date element, because Moodle stores timestamps: a text input in the
user's timezone with a calendar and time picker as JavaScript enhancement, a checkbox for
"enabled" does not exist, empty means `null` (legacy `0` in current data is `null` too).
Timestamps are primary: with JavaScript the module unhooks the text input from the form and
submits a hidden input with the timestamp instead, typed text is normalised by the server
through the `POST /api/rest/v2/tool_mulib/muform/datetime` endpoint (stateless, no login).
Without JavaScript the text is parsed on the server the same way: integers are timestamps,
then the display format is tried, then anything PHP understands (`2026-09-26 19:28`,
`26 September 2026 19:28`, `tomorrow 10:00`). Unparseable text is an error and is shown
again so the user can fix it. The display format is the `muform_datetimeformat` lang string,
a PHP `date()` format, `Y-m-d H:i` by default (seconds are kept in the timestamp but not
shown; use `Y-m-d H:i:s` to show them); sites wanting another order or 12 hour
clock change the lang string (it must round-trip through `DateTime::createFromFormat()`),
or pass a format to the constructor. The localised timezone name is printed under the input.
All parsing and formatting lives in `tool_mulib\muform\util\calendar`, shared with `duration`.

`duration` is a number of seconds, never `null`: one number input per unit posted as
`name[w]`, `name[d]`, `name[h]`, `name[i]` or `name[s]`, the server adds them up, so any
combination works (90 minutes is fine) and the value comes back normalised (1 hour
30 minutes). The `units` list says which inputs are always shown, days, hours and minutes
by default (`['w', 'd', 'h', 'i', 's']` in that order, any subset). A smaller unit is added
automatically when the current data or the default needs it, so nothing is rounded or lost;
users just cannot enter units that are neither configured nor already present. Empty inputs
mean `0`; required means greater than zero. There are no limits, add a validator. Long periods
in months or years belong to `dateinterval`.

`dateinterval` is for calendar periods added to dates with PHP `DateInterval`, where seconds
do not work: the value is an ISO 8601 duration such as `P1Y6M` or `P1W2DT36H`, or `null`.
One number input per unit posted as `name[y]`, `name[m]`, `name[w]`, `name[d]`, `name[h]`,
`name[i]` or `name[s]`; nothing is normalised, 14 months stay `P14M`. The `units` list says
which inputs are always shown, years, months, weeks and days by default (any subset of
`['y', 'm', 'w', 'd', 'h', 'i', 's']` in that order); any other unit with a non-zero part in
the current data or the default is added automatically, so nothing is lost. The value is
canonical: zero parts are omitted and an all zero interval is `null`, current data is validated
with a regex (designators in the fixed order `Y M W D T H M S`, the subset `DateInterval`
accepts) and canonicalised (`P0Y2M` becomes `P2M`). Interval parsing and building lives in
`tool_mulib\muform\util\calendar`.

`secret` and `sharedkey` replace `passwordunmask`. They are not passwords: `secret` is a
credential for a machine (database, LDAP bind, API key) and its current value never reaches
the browser; `sharedkey` is a key handed to humans (enrolment key, guest access) and "Show"
reveals the current value, which is rendered in a data attribute because whoever may edit the
key may see it. Both are masked `<input type="text">` elements (CSS `-webkit-text-security`),
never `type="password"`, so browsers do not autofill them or offer to save them. Browsers also
classify fields by name and label, so call elements `dbsecret`, `apikey`, `enrolkey`, never
`password`. The two classes are deliberately separate implementations.

Current data is a tri-state: `true` means a value exists without telling the form what it is
(pass `!empty($record->dbsecret)` for secrets), a non-empty string means it exists and, for
`sharedkey`, is the value shown; anything else means not set. The input is always empty with
a placeholder of dots when a value exists. The returned value is `null` (keep the current
value), `''` (cleared, only when `allowclear` renders the checkbox) or the new string as typed,
without cleaning. `set_required(true)` is satisfied by an existing value. Complexity rules are
ordinary validators:

```php
$dbsecret = (new secret('dbsecret', 'Database secret', [], true))
    ->add_validator(function (secret $element, array &$allerrors): void {
        $value = $element->get_value();
        if (is_string($value) && $value !== '' && !check_password_policy($value, $errmsg)) {
            $allerrors['dbsecret'][] = $errmsg;
        }
    });
$this->add($dbsecret);
```

Handlers store only when a new value arrived: `if ($data->dbsecret !== null) { ... }`. A value
typed in the submitted request is rendered again after a reload or validation error (for example
a "Check connection" button), the current value never is; the clear tick survives too.

`filemanager` renders core's file manager widget (uploads, repositories, folders) over a
draft area; the value is the draft item id. Where the files live permanently is a
`tool_mulib\muform\util\file_area` (context, component, area, item id), given either in
current data under the element name or via `set_file_area()` before `add()`; files are then
copied into a fresh draft. Current data may also be an existing draft item id. Context and item
id may be unknown for new records: create the area with `null`s, insert the record, then
`set_itemid()` and `save_area()`, or `export_to_file_area()` with any area:

```php
$this->add(new filemanager('attachments', 'Attachments', 5, ['.pdf', 'image']));
// handler, current data: ['attachments' => new file_area($context, 'tool_x', 'attachments', $record->id)]
$record->id = $DB->insert_record('tool_x_thing', $record);
$element = $form->get_element('attachments');
$element->get_file_area()->set_itemid($record->id);
$element->save_area();
```

`get_files()` returns the acceptable draft files (`stored_file[]`, wrong types and disallowed
subfolders left out), independent of the submission state, useful for imports and wizard
stages that read a file and never save an area. Validation enforces accepted types, `maxfiles` and subfolders as
form errors instead of dropping files silently on save; `set_required(true)` means at least
one file. A stale draft (cleaned up after 4 days) never overwrites an existing area, the save is
skipped with a debugging message. Guests cannot use the element. The widget works in dialogs.

`editor` uses the site's preferred editor for the text format: TinyMCE for HTML, the plain
textarea editor with a format selector for other formats. The value is the text; the format
and the draft item id come back next to it as `<name>format` and `<name>draftitemid`
through `get_additional_data()`, an element hook whose keys must not clash with any
element name. Current data keys are `<name>`, `<name>format` (default: the preferred
editor's format) and optionally `<name>filearea` (a `file_area`, or use `set_file_area()`).

HTML and Moodle-auto text is cleaned with `clean_text()` on load and on submit; plain text
is escaped at display time and Markdown cannot be cleaned, so both are left alone. There is
no trusttext: a form that must accept raw HTML calls `allow_unsafe_rawhtml()` on the element
and takes responsibility for who may submit it; such editors show a warning line underneath. Files are optional (`maxfiles` 0 means none);
with files the editor uploads into a draft area, dropped and pasted images included; the value
already carries `@@PLUGINFILE@@` links, so the handler stores it and calls `save_area()` for
the files:

```php
$this->add(new editor('description', 'Description', -1));
// handler, current data: ['description' => $record->description,
//   'descriptionformat' => $record->descriptionformat,
//   'descriptionfilearea' => new file_area($context, 'tool_x', 'description', $record->id)]
$record->description = $data->description;
$record->descriptionformat = $data->descriptionformat;
$DB->update_record('tool_x_thing', $record);
$form->get_element('description')->save_area();
```

Without files `save_area()` and `export_to_file_area()` do nothing, so handlers may always
call them; for new records set the area's item id after the insert, as with `filemanager`.

`autocomplete` and `autocompletemany` are two separate families (one value, list of values),
each with a `final` element, a source base class, one fixed REST endpoint in tool_mulib and
its own React island. The element gets a source object that owns the whole picker: the
constructor takes scalar arguments and does the access control, `search()` finds options,
`label()`/`labels()` resolve stored values (unknown values are rejected on submit, that is the
permission check), `validate()` refuses values with a reason shown per option, and
`get_args()` returns the constructor arguments so the endpoint can reinstantiate the same
source; plugins never add endpoints. Labels are HTML already passed through `clean_text()`.
Values are non-empty strings of letters, digits and `_-.:@` (no comma), so a list travels
as a comma separated string. Pickers are not available to guests.

```php
namespace tool_muprog\muform\autocompletemany;

final class program_allocation_userids extends \tool_mulib\muform\autocompletemany\base {
    use \tool_mulib\muform\util\autocomplete\user_trait;

    public function __construct(private int $programid) {
        $this->context = program::get_context($programid); // 0 means a new program, parent context
        require_capability('tool/muprog:allocate', $this->context);
    }
    public function get_args(): array { return [$this->programid]; }
    public function search(string $query, int $maxitems, array $exclude): ?array {
        return $this->search_users($this->context, $query, $maxitems, $exclude, $this->get_where());
    }
    public function labels(array $values): array { return $this->user_labels($this->context, $values, $this->get_where()); }
    public function validate(array $values): array { return $this->validate_users($values); }
}
// form definition: $this->add(new autocompletemany('userids', 'Users', new program_allocation_userids($program->id)));
```

Sources may depend on earlier elements (`new course_users((int)$course->get_value())` right
after adding the course element; changing the dependency needs a form reload) and the same
source serves create and edit forms through an id that is `0` for new records. Helper traits
for entities live in `classes/muform/util/autocomplete/`: `user_trait` (users with identity fields
and tenant rules), `cohort_trait` (visible cohorts, cohorts of other tenants refused, cohorts
already used by the edited item always accepted) and `category_context_trait` (the system context
or categories where the user has a capability, the current context stays selectable);
`site_user`/`site_users` are the shipped sources for anyone who may view all user details. The endpoints answer the legacy
`{list, overflow, maxitems}` structure; the search endpoints are browser only, requests without
an active browser session (OAuth2 tokens, API keys) are refused; a source returns `null` from `search()` when more than
`get_maxitems()` match and the picker asks to keep typing.

`tags` edits the core tags of one item. The tag area object plays the role of an autocomplete
source: a subclass of `tool_mulib\muform\tagarea\base` in `<component>\muform\tagarea` whose constructor
takes scalar arguments only and checks capabilities, because the `tool_mulib` tags endpoint
instantiates it again from `get_args()` for every suggestion request. It names the core area
(`get_component()`, `get_itemtype()`), the instance context and the item id, `null` for new items;
the context never changes while the form is used, moving an item elsewhere is a separate action.
Current tags are loaded from the area unless current data has the element key, and
`$form->get_element('tags')->save($itemid)` stores them after the item is saved. Names are trimmed and
spaces collapsed, case insensitive duplicates merged; names core would change further (`<`, `>`,
backticks, control characters, over 255 characters) or containing commas are invalid, never silently
cleaned. The area's standard tags mode is enforced on the server: standard tags are suggested unless
hidden, and with standard tags only, unknown names are invalid. With tagging disabled the element is
hidden, its value is empty and `save()` does nothing. `tool_mulib\muform\tagarea\course` is the shipped
area for course tags.

```php
namespace mod_mubook\muform\tagarea;

final class chapter extends \tool_mulib\muform\tagarea\base {
    public function __construct(private readonly int $cmid, private readonly ?int $chapterid) {
        require_capability('mod/mubook:edit', \core\context\module::instance($cmid));
    }
    // get_args(): [$this->cmid, $this->chapterid], get_component(): 'mod_mubook', ...
}
```

`hidden` without a type is frozen: the submitted value is ignored and current data or an explicit default is used,
which is what ids need. Pass a `core\param` type to accept the submitted value; it is cleaned
with that type and any change caused by cleaning is an error; with `param::INT` the value is
returned as `int`. Errors of hidden elements are
rendered as an alert in place of the input. Put initial values for new records into current
data; `set_default()` exists for the rare cases where current data is not the right place.

`radios`, `checkboxes`, `select` and `multiselect` accept either an array of labels indexed
by keys or a `tool_mulib\muform\util\options` instance when groups are needed. An option
with an empty key in `select` acts as the "Choose..." placeholder and is returned as `null`:

```php
$roleoptions = (new options(['none' => 'No role']))
    ->add_optgroup('Teachers', ['editingteacher' => 'Editing teacher', 'teacher' => 'Non-editing teacher'])
    ->add_optgroup('Others', ['student' => 'Student']);
$this->add(new radios('role', 'Role', $roleoptions));
```

Groups are presentation only: keys are unique across the whole element, values and display
rules never see groups. `checkboxes` and `multiselect` accept current data as an array or a
comma separated string and always return an array in option order. Use it instead of
a multiselect for short lists such as roles. Elements never change their value type based on
attributes: single value and multiple value widgets are always separate classes.

Attributes are element specific HTML attributes (`maxlength`, `min`, `decimals`, `rows`,
`placeholder`, `pattern`, ...). Unknown attributes trigger a debugging message and are ignored.
Text-like inputs (`text`, `number`, `datetime`, `secret`, `sharedkey`, `autocomplete`) take a
`width` attribute with `auto` (theme default, about 20 characters), `small`, `medium` or `full`;
it is rendered as a `muform-width-*` class, never as a character count, so widths stay
consistent and shrink on small screens. `text` with type `url` is full width by default,
`autocomplete` defaults to `medium`, `autocompletemany` is always full width.

Common fluent setters on every element:

- `set_required(true)`: native `required`, server check, required marker.
- `set_required_marker(true)`: marker and `aria-required` only, no validation.
  Pair it with a validator.
- `set_required_hint()`, `set_invalid_hint()`: custom error texts.
- `set_frozen(true)`: submitted value is ignored, current data or default is used,
  rendered as static text. This is the only way to make a value unchangeable.
- `add_help_button()`, `set_template()`: cosmetic, allowed at any time before render.
- `add_validator()`: validator instance or `function (element $element, array &$allerrors): void`.

Value related setters (`set_required()`, `set_frozen()`, `set_attribute()`) throw once the
element is attached. Cosmetic setters do not.

## Required values

- `set_required(true)` for "always required".
- `set_required_marker(true)` plus `required_if_visible` for "required when shown by display rules".
- `set_required_marker(true)` plus your own validator for anything else. Use
  `$element->has_required_value()` inside it; every element defines what "empty" means.

Never use the `required` HTML attribute on a field that may be hidden; the browser would
block submission on an invisible field.

## Display rules

```php
$dm = $this->get_display_manager();
$dm->hide_if('message', 'notify', 'notchecked');
$dm->disable_if('priority', 'level', 'in', ['low', 'none']);
```

Operators: `eq`, `neq`, `in`, `notin`, `checked`, `notchecked`, `empty`, `notempty`.
Rules depend on element values only, never on another element's hidden or disabled
state, so evaluation is a single deterministic pass. Rules on a section or button row
cascade to all children. The same rules are evaluated in the browser by the display
manager ES module.

Validators may ask `$element->get_form()->get_display_manager()->is_hidden('name')`.
That is still only a statement about what the browser would show for these values.

To make a value unchangeable use `set_frozen()`, not `disable_if()`.

## Validators

```php
final class unique_name extends \tool_mulib\muform\validator {
    #[\Override]
    public function validate(element $element, array &$allerrors): void {
        global $DB;
        if ($DB->record_exists('local_myplugin_item', ['name' => $element->get_value()])) {
            $allerrors[$element->get_name()][] = get_string('duplicatename', 'local_myplugin');
        }
    }
}
```

Validators run only on submission, after all elements are attached. They receive the
element and the whole error array, so cross-element errors are fine. Errors keyed by an
unknown element name are rendered at the top of the form and trigger a debugging message.

## Templates and escaping

Everything passed to a template is escaped with `clean_string()` on the PHP side and
printed with `{{ }}`. Only context keys ending in `html` (`html`, `helpbuttonhtml`)
are printed with `{{{ }}}`. Never call `format_string()` in element code.

Element templates extend `tool_mulib/muform/element/wrapper` and provide the
`{{$control}}` block. `$form->render($OUTPUT, 'compact')` renders with template variants: for
the form and every element the template `<name>-compact` is used when it exists (in the plugin or
as a theme override) and the standard template otherwise, so a variant only has to provide the
templates it changes, typically `wrapper-compact`. Form or element templates can be replaced with `set_template()`,
also from the hook, which is the supported way for local plugins to customise a form.

## Layout elements

`section` and `buttons` return no data and only arrange their children, which are added with
`$this->add($element, 'parentname')`; other elements refuse children. Appearance is a template
matter: `buttons-reversed.mustache` extends `buttons.mustache` and overrides its `rowclass`
block, and any element can be pointed at such a variant with `set_template()`, or all of them
at once with the render variant. Template variants use a `-` suffix after the base name.
A new layout element extends `element`, overrides `accepts_children()` to return true,
`returns_data()` to return false, `parse_value()` to keep the value null, adds whatever context
the template needs in `get_template_data()`, and gives the template
`{{#elements}}{{{html}}}{{/elements}}`. A container may also retarget its children's templates
by calling `set_template()` on them before `parent::get_template_data()`, for example a toolbar
rendering icon-only buttons; that changes markup only, never behaviour. The browser side is
`export default class extends Element {}`.

Container and form templates get their children twice: `elements` in order, and `elements_byname` indexed by
element name, so a custom layout places each child where it belongs:
`{{#elements_byname.content}}{{{html}}}{{/elements_byname.content}}`. A custom template must render every child,
a child left out is not posted. For a fully custom page part, such as an editor with panes and
previews, write a plugin layout element (`<component>\muform\element\<type>`) with its own template
and ES module; the module finds its children inside its own wrapper by
`[data-muform-name="..."]`, never through html ids, which carry the form suffix.

All shipped elements are `final`: their value type, template, Behat helper and ES module form one
contract, so a different behaviour is always a new element type rather than a subclass, and a
different look is a template or a variant.

## Writing a new element

1. Extend `tool_mulib\muform\element` in the `<component>\muform\element` namespace of your
   plugin; the class name is the element type. The template, the ES module
   (`@moodle/lms/<component>/muform/element/<type>`) and the Behat helper are all derived
   from component and type, so keep the names aligned; call `set_template()` only to reuse
   another template. Set `$this->label` in the constructor and declare `ALLOWED_ATTRIBUTES`
   with `core\param` types.
2. Override `parse_value()`: call `parent::parse_value()`, then sanitise `$this->value`
   into its final type and push messages into `$this->errors`. Do not check required here.
3. Override `has_required_value()` if "empty" is not `null` or `''`.
4. Override `get_template_data()` to add element specific context; keep the
   `attr_x` / `has_attr_x` convention for optional HTML attributes.
5. Override `is_cancelling()`, `is_reloading()` or `is_submitting()` if a non-empty value
   of the element should cancel, reload or submit the form.
6. Add a mustache template with the standard docblock and example context.
7. Add a PHPUnit test under `tests/phpunit/muform/element/`.

## Hook

`tool_mulib\hook\muform_definition` is dispatched after `definition()`, before
finalisation. Allowed: `add()` elements, `add_validator()`, display rules,
`set_template()`, hints, markers, help buttons. Not possible: changing values,
defaults, attributes, frozen or required state of existing elements.

## Behat

Four steps in `behat_tool_mulib`, tables only:

```gherkin
When I set the following muform fields:
  | fullname | Jane Doe         |
  | roles    | manager, teacher |
  | country  | Germany          |
And I set the following muform fields in the "dialog[open]" "css_element":
  | name | x |
Then the following muform fields match:
  | fullname | Jane Doe         |
  | roles    | teacher, manager |
And the following muform fields in the "dialog[open]" "css_element" match:
  | name | x |
```

- First column: exact element name; if no element has that name, the exact label text of the
  element is used. Exactly one element must match.
- Second column: for option elements exact option keys, comma separated where multiple values
  are possible (`\,` for a literal comma); an exact option label is accepted only when no option
  has that key. Textarea: a literal `\n` in a cell is a line break, in expected values too.
  Checkbox: anything except empty or `0` means checked. Hidden elements can be
  checked but not set. Datetime: a timestamp (`##tomorrow noon##` works), empty, or any text the
  server can parse; expected values are compared as timestamps. Duration: seconds, empty
  means zero, the seconds must fit the rendered units; the unit inputs can also be set one by one
  through their names
  (`I set the field with xpath "//input[@name='timelimit[h]']" to "2"`). Dateinterval: an ISO 8601
  duration string or empty, compared in canonical form, every non-zero part must have a rendered
  input, unit inputs are named `name[y]` and so on. Secret and sharedkey: text is
  typed as the new value, empty keeps the current value, `[clear]` ticks the clear checkbox;
  an untouched sharedkey matches its current value. Filemanager: the value is the comma
  separated list of file names in the draft area and can only be checked; uploads use
  `I upload "lib/tests/fixtures/empty.txt" file to "attachments" muform filemanager` from
  `behat_tool_mulib_files` (needs the `@_file_upload` tag and JavaScript). Editor: the text,
  set and read through core's editor field (TinyMCE or textarea), compared with whitespace
  collapsed. Autocomplete: the value (comma separated values for many) or the start of the visible
  label; with JavaScript each value is typed and the result whose value or label matches is
  picked, a search with a single result is picked too, an empty cell clears.

Two more steps test the result lists of autocomplete, autocompletemany and tags with JavaScript:
`I type "Faculty" into the "tenantid" muform search field` types a search without picking a
result (a chosen single value is clicked first, the way users start a new search), and
`the open muform list should be fully visible` checks that the open list is inside the window and
not clipped or covered, dialogs included. Options are then picked with a plain click, for example
`I click on "//li[@data-muform-autocomplete-option][normalize-space(.)='Faculty 45']" "xpath_element"`;
result lists scroll on their own and never grow past the window.
- Each element type has a Behat helper in `tests/classes/muform/element/<type>.php`, class
  `<component>\tests\muform\element\<type>` extending `tool_mulib\tests\muform\element\base`,
  found automatically from the `data-muform-component` and `data-muform-element` attributes.
  A custom element in another plugin ships its helper in that plugin's
  `tests/classes/muform/element/`. Helpers get the Behat context, so
  JS driven widgets can use `execute_script()` and `wait_for_pending_js()` there.
- Every element has its own fixture page `tests/behat/fixtures/muform_element_<type>.php` and
  feature `tests/behat/muform_element_<type>.feature`; copy `muform_element_text.php` and its
  feature when testing a custom element. Pages check `BEHAT_SITE_RUNNING` right after
  `config.php`, before any include, and call `require_admin()`; the shared helpers in
  `muform_fixture_lib.php` read the `required`, `frozen`, `prefill` and `default` flags, set up the
  page and print `#muform_state` plus one `#submitted_<name>` item per data key (arrays joined
  with commas, `null` printed, line breaks as `\n`). Every fixture form has `hide` and `lock`
  checkboxes wired to display rules of the tested element. The scenarios follow one pattern:
  new form and default, current data submitted unchanged, typed values, required, invalid
  values, custom `validation()`, frozen, reload and cancel, then with JavaScript hidden,
  locked and client side validation.

## JavaScript

Every element type ships an ES module at `js/esm/src/muform/element/<type>.ts`, served as
`@moodle/lms/<component>/muform/element/<type>`; the form template loads the orchestrator
`@moodle/lms/tool_mulib/muform/form`, which imports one module per element wrapper found in the
form. Build with `npx grunt esm` from the Moodle root (Node 22) and commit `js/esm/build/`.

The contract is a default-exported class:

- `Element` (`@moodle/lms/tool_mulib/muform/element`) is the pure contract: `name`, `wrapper`,
  `state` (`hidden`, `disabled`, `errors`, `touched`), `getValue()`, `validate()`, `syncUI()`,
  `focus()`, `destroy()`. It assumes nothing about the markup inside the wrapper and its
  `syncUI()` writes only the wrapper attributes.
- `NativeElement` (`@moodle/lms/tool_mulib/muform/native`) adds native control handling for the
  standard wrapper: values derived from inputs, selects and textareas, native constraint
  validation using the hints rendered by the wrapper, disabling of controls and error display
  with `is-invalid` and `aria-invalid`. All tool_mulib inputs are `export default class extends NativeElement {}`.

Rules of the browser side:

- The orchestrator and the display manager never touch the DOM inside a wrapper. They read
  `getValue()`, write `state` and call `syncUI()`; the element projects its state however it
  likes, a React island simply passes the state as props.
- Display rules are evaluated exactly like on the server (`js/esm/src/muform/display.ts`):
  values only, single pass, OR per target, cascade into sections and button rows.
- Client validation runs on submit and when leaving a touched element; hidden and disabled
  elements are skipped; the server remains the authority.
- Elements report changes with a bubbling `muform:change` event on their wrapper and use the
  `form` API given to the constructor for actions: `form.submit()`, `form.reload()`,
  `form.cancel()`. A custom "reloading" element calls `form.reload()` when it decides to.
- Buttons are native submits; the orchestrator reads `data-muform-role` from the submitter
  to skip validation for reload and cancel.
- Double submission is refused: once a submission left the browser every later submit event
  is cancelled and all submit buttons are disabled, the page or dialog answer renders a
  fresh form. Leaving an element does not validate it when the focus moves to a form button,
  otherwise the appearing error message would move the button away from the click.

A custom element with its own UI extends `Element`, renders inside the minimal
`tool_mulib/muform/element/outer` wrapper template and overrides `getValue()`, `validate()`,
`syncUI()` and `focus()` as needed. Jest tests live in `js/esm/tests/` and run with
`npx jest public/admin/tool/mulib/js/esm/tests`.

## Dialogs

A muform can be opened in a native `<dialog>` from any page. The form does not know about it;
the same handler URL serves the full page and the dialog (the dialog JS sends the
`X-Muform-Dialog: 1` header). `tool_mulib\muform\handler::from_request()` returns the dialog
handler (`handler\dialog`) for dialog requests and the page handler (`handler\page`) otherwise,
both answer the same three calls:

```php
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();
$form = new item_edit($PAGE->url, $item);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}
if ($data = $form->get_data()) {
    // Save data.
    $handler->submitted($returnurl);
}
$handler->render($form);
```

In classic scripts every answer is sent at once and the script stops: the page handler redirects
or prints header, content and footer, the dialog handler sends JSON. Router controllers pass the
request and response, `handler::from_request($request, $response)`, and return the answers
instead; `classes/route/controller/muform_fixture.php` is a complete example (available only in
Behat and PHPUnit, exercised by `tests/behat/muform_dialog.feature`).

`render()` takes the form, or a callable receiving the page renderer and returning html for
anything else, and the dialog title, the page heading by default. The dialog handler calls
`$OUTPUT->header()` for theme initialisation and passes the real renderer to the callable (do not
capture `$OUTPUT` before that, it is still the bootstrap renderer), collects JavaScript
requirements issued while rendering, buffers stray output and answers with JSON
`{status: 'render', title, html, javascript}`; `submitted()` answers
`{status: 'submitted', redirecturl, data}` (`data` goes to page JavaScript, see below),
`cancelled()` answers `{status: 'cancelled'}`. A flow that continues differently in a dialog
asks `$handler->is_dialog()`, for example a dialog renders the second step at once while the
page redirects to it.

Triggers are renderables in `tool_mulib\output\muform\dialog`: `button`, `link` and `icon`, with `set_form_size('sm'|'lg'|'xl')`, `set_submitted_action(handler\dialog::ACTION_*)`,
`set_modal_title()`, `set_icon()`, `add_class()`; `link` also offers `create_report_action()`
for report builder and `dropdown::add_dialog()` puts it into header action menus. Links keep a real
`href`, so without JavaScript they open the full page. After a successful submission the action
decides: `ACTION_RELOAD` reloads the page, `ACTION_REDIRECT` goes to the handler's redirect URL,
`ACTION_NOTHING` closes the dialog and dispatches `muform:dialog-submitted` on the trigger with
the handler's `data` in `event.detail.data`. Cancel buttons close the dialog client side.

In Behat scope the muform steps to the dialog: `in the "dialog[open]" "css_element"`.

## Wizards

Multi-page forms keep their state in `tool_mulib_muform_wizard`, one row per running wizard:
`userid`, `sessionhash`, `jsondata`, `timecreated`. The handler uses `tool_mulib\muform\wizard`;
forms and elements know nothing about wizards. The row id travels in the page URL as the `wizard`
parameter and the row is bound to the wizard name, its owner and the login session: the stored hash is
`sha1(name / userid / sesskey / timecreated)`, neither the name nor the sesskey is stored. Nobody else
can load the row, a re-login orphans it and the data of one wizard can never be loaded by another one.
Put the page parameters the data belongs to into the name, such as `'auth_musaml_user_upload:' . $idp->id`,
then a row cannot be replayed against a different record either; that matters because handlers tend to
cross-check stored data with page parameters. Rows older than a day are deleted whenever a wizard
starts; there is no cron.

The stored JSON is the only truth. The handler validates it stage by stage on every request with
`wizard::resolve_stages()`: the first invalid stage is the current one (the last stage when everything is
valid), a `stage` page parameter may move to any valid stage or to that first invalid one, never past it.
That alone gives back and forward navigation on a fully filled wizard; nothing about the stage is trusted
from the request. Every stage is its own form class (the `__formid` is the class name), it gets the stored
data as current data, so a stored `filemanager` draft itemid is reused and uploaded files survive stage
changes without any export. After an accepted stage the handler stores the validated result and redirects
to the URL without a stage, so the next stage is resolved from data.

```php
$name = 'auth_musaml_user_upload:' . $idp->id;
$wizard = wizard::load($name, optional_param(wizard::PARAM, 0, PARAM_INT));
if (!$wizard) {                                   // new, expired, re-login, another wizard or somebody else's row
    $wizard = wizard::start($name);
    redirect($wizard->get_url($pageurl));
}
$data = $wizard->get_data();
$stages = wizard::resolve_stages(['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'],
    fn(string $stage) => mapping_import::is_stage_valid($stage, $data), optional_param(wizard::STAGE_PARAM, null, PARAM_ALPHA));
$stage = wizard::current_stage($stages);
$form = new user_upload_source($wizard->get_url($pageurl, $stage), $data, ['idp' => $idp]);   // one class per stage
if ($form->is_cancelled()) {
    $wizard->delete();
    redirect($returnurl);
}
if ($form->is_reloaded() && $form->get_element('back')->get_value()) {
    redirect($wizard->get_url($pageurl, 'source'));
}
if ($submitted = $form->get_data()) {
    $wizard->set_data(mapping_import::save_source($data, $submitted, $form));
    redirect($wizard->get_url($pageurl));
}
echo $OUTPUT->header();
echo $wizard->render($OUTPUT, $stages, $pageurl, $form);
```

Rules: Back is a `reload` element (usually named `back`) that the handler turns into a redirect to the
previous stage, never a `cancel` (dialogs close on cancel in the browser); Cancel and the final submit
delete the row; `set_data()` stores only data the handler has validated, and the handler validates the
stored data again on every request instead of trusting it. `render()` renders the stage form inside
the wizard layout (`templates/muform/wizard.mustache`): a numbered step list on the left, the form in
a card on the right; reachable stages are links, the current one carries `aria-current="step"`, later
ones are muted. The layout works with the `standard` page layout for small forms and with `report` when
the stages need the full width, the handler decides. Stage labels passed as array values are cleaned with `clean_string()`.
Editor drafts are not carried across stages yet, the `editor` element ignores a `<name>draftitemid` in
current data. Fixture page and feature: `tests/behat/fixtures/muform_wizard.php` and
`tests/behat/muform_wizard.feature`.

## Custom fields

`customfields` edits the core custom fields of one instance without the legacy
`instance_form_*()` handler API. It is a layout element: when added it creates a `section` per
category (`<name>_category_<categoryid>`) with one element per field named `customfield_<shortname>`,
so the values are part of `get_data()` like any other element. It loads the stored values from
`customfield_data` itself, new instances (`instanceid` null) get the configured defaults, and
`save()` writes all fields straight into `customfield_data` after the instance was saved:

```php
$this->add(new customfields('customfields', program_handler::create(), $program?->id));

if ($data = $form->get_data()) {
    $id = program::create($data)->id;              // or update
    $form->get_element('customfields')->save($id);
}
```

Only field types with a mapper in `classes/muform/customfield/` are supported, other types are
skipped. Values are stored in the same columns as the core field types, so display, export,
report builder, backup and `handler::delete_instance()` keep working with core code.

| Type       | Element    | Stored in                        | Notes                                              |
|------------|------------|----------------------------------|----------------------------------------------------|
| `text`     | `text`     | `charvalue`                      | `maxlength` enforced; password flag, size and link ignored |
| `textarea` | `editor`   | `value`, `valueformat`           | files in `customfield_textarea/value/<dataid>`; `valuetrust` is 0 because the editor cleans text; default value files are not copied |
| `checkbox` | `checkbox` | `intvalue`                       | `checkbydefault` is the default                    |
| `select`   | `select`   | `intvalue` option index          | empty option means 0                               |
| `date`     | `datetime` | `intvalue` timestamp, 0 empty    | date only fields show `Y-m-d` and store midnight in the user timezone; `mindate`, `maxdate` |
| `number`   | `number`   | `decvalue`                       | min, max and decimal places; fields with automatic value providers are skipped |
| `mutrain`  | `number`   | `decvalue`                       | two decimals, 0 means no credits                   |

Rules: shared custom field categories (`core_customfield/shared`) are never shown; fields the
handler's `can_edit()` refuses are skipped and never saved; required, unique values and the type
rules are validated by the child elements; rows are always written, also for empty values, so an
unticked checkbox does not fall back to `checkbydefault`. Category names, field names and select
options are formatted with `format_string()` and descriptions with `format_text()` here, because
they are configuration data the caller never sees. The field label in Behat tables is ambiguous
when a description repeats it, use the `customfield_<shortname>` names. Fixture page and feature:
`tests/behat/fixtures/muform_customfields.php` and `tests/behat/muform_customfields.feature`.
