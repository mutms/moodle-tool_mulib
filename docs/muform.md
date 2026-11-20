# muform - server-side rendered forms

`tool_mulib\muform` replaces legacy Moodle forms. A form is a PHP class, and each element is a PHP
class with a mustache template and an optional ES module. The server parses, validates and renders
everything. The browser only makes it nicer to use.

## The four rules

1. **An element value never changes after the element is added to the form.** `parse_value()` runs
   once inside `$form->add()`, and from then on `get_value()` returns the same value to the
   definition, validators, the handler script and the template.
2. **Hiding and disabling only change what the browser shows.** Hidden elements are still submitted
   and validated. Browsers do not submit disabled elements, so the server uses current data or the
   default instead. The server never skips anything because of a display rule.
3. **Extra validation happens after the form is complete.** Validators run after `definition()` and
   the `muform_definition` hook, so they can see every element, value and display rule.
4. **As little public API as possible.** If something is not public, that is on purpose. Do not work
   around it, ask for the API.

## How a form works

The constructor `new my_form($targeturl, $currentdata, $extradata)` does all the work. It reads
`$_POST` only when the post contains this form's `__formid` and a valid `__sesskey`, otherwise the
form is simply not submitted. Then it calls `definition()`. Every `add()` parses the element value
straight away (from POST, then current data, then the default), so the definition can already look
at `$element->get_value()` and add more elements based on it.

After the definition, the `tool_mulib\hook\muform_definition` hook lets other plugins add elements,
validators and display rules, or change templates. Then the form is complete and nothing more can be
added.

The form then works out what happened. A non-empty value of a cancelling element wins, then a
reloading one, then a submitting one. Usually these are the `cancel`, `reload` and `submit` buttons,
but a checkbox such as "Add more rows" can reload the form too. A submitted form is validated in this
order: element parse errors, required values, validators and finally `validation()`.

The handler script then asks `is_cancelled()`, `is_reloaded()` and `get_data()`, which returns data
only when the form is valid.

## Writing a form

```php
namespace local_myplugin\form;

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\number;
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

        $name = new text('name', get_string('name'), ['maxlength' => 100]);
        $name->set_required(true);
        $name->add_help_button('name', 'local_myplugin');
        $this->add($name, 'general');

        $this->add(new number('priority', get_string('priority'), ['min' => 0, 'max' => 10]), 'general');
        $this->add(new checkbox('notify', get_string('notify'), get_string('notify_desc')), 'general');

        // Only shown when notify is checked, required only when shown.
        $message = new textarea('message', get_string('message'), ['rows' => 5]);
        $message->set_required_marker(true);
        $message->add_validator(new required_if_visible());
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

Create each element in a variable, call its setters one per line, then add it. Mark overridden
methods with `#[\Override]` instead of copying the parent docblock.

Add the submit button first, because browsers use the first submit button when Enter is pressed.
`buttons` warns in debugging mode when a cancel or reload button comes first. Reload buttons before
the button row, like "Delete row", are fine: with JavaScript, Enter in a text input always uses the
submit button. If you want the submit button shown last, use the `buttons-reversed` template.

## Handling a form

```php
use tool_mulib\muform\handler;

require('../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = \core\context\system::instance();
require_capability('local/myplugin:manage', $context);

$item = $DB->get_record('local_myplugin_item', ['id' => $id], '*', MUST_EXIST);

$currenturl = new \core\url('/local/myplugin/edit.php', ['id' => $item->id]);
$returnurl = new \core\url('/local/myplugin/index.php');

$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('item_edit', 'local_myplugin');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();
$form = new item_edit($currenturl, $item);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}
if ($data = $form->get_data()) {
    // $data is stdClass with typed values: int for number, string for text, 1/0 for checkbox.
    $data->id = $item->id;
    $DB->update_record('local_myplugin_item', $data);
    $handler->submitted($returnurl);
}
$handler->render($form);
```

The same script serves the full page and the [dialog](#dialogs). `handler::from_request()` gives you
a page handler, which redirects or prints the page, or a dialog handler, which answers with JSON.
Every handler call ends the script.

`$PAGE->url` must point to this script with the parameters it reads, because the form posts back to
it. The page heading is also used as the dialog title. Record ids come from page parameters, never
from submitted data, and access checks happen in the script before the form is created. The form
checks the sesskey itself.

A reload is nothing special: `get_data()` returns null and the form is shown again with the submitted
values.

Forms that answer with a file, such as exports, use the `download` button instead of `submit`. It
validates the same way but posts into a new browser window, so the form stays usable after the
download, also in a dialog.

## Elements

| Element            | Constructor                                                                  | Value in `get_data()`                                      | Empty means      |
|--------------------|------------------------------------------------------------------------------|------------------------------------------------------------|------------------|
| `text`             | `(name, label, attributes)`                                                  | `string`, cleaned                                          | blank after trim |
| `textarea`         | `(name, label, attributes)`                                                  | `string`, cleaned, `\n` line ends                          | blank after trim |
| `number`           | `(name, label, attributes)`                                                  | `int`, `float` or `null`                                   | `null`           |
| `datetime`         | `(name, label, attributes)`                                                  | `int` timestamp or `null`                                  | `null`           |
| `datetimerange`    | `(name, label, attributes)`                                                  | `['start' => int, 'end' => int]` or `null`                 | `null`           |
| `duration`         | `(name, label, units = ['d', 'h', 'i'])`                                     | `int` seconds                                              | `0`              |
| `dateinterval`     | `(name, label, units = ['y', 'm', 'w', 'd'])`                                | ISO 8601 duration `string` or `null`                       | `null`           |
| `secret`           | `(name, label, attributes, allowclear = false)`                              | `null` keep, `''` cleared, `string` new                    | see below        |
| `sharedkey`        | `(name, label, attributes, allowclear = false)`                              | `null` keep, `''` cleared, `string` new                    | see below        |
| `filemanager`      | `(name, label, maxfiles = null, acceptedtypes = null, allowsubdirs = false)` | `int` draft item id                                        | no files         |
| `editor`           | `(name, label, maxfiles = 0, allowsubdirs = false, attributes)`              | `string` text, plus `<name>format` and `<name>draftitemid` | blank text       |
| `autocomplete`     | `(name, label, source, attributes)`                                          | `string` value or `null`                                   | `null`           |
| `autocompletemany` | `(name, label, source, attributes)`                                          | `string[]` values                                          | `[]`             |
| `picker`           | `(name, label, source)`                                                      | `string` value or `null`                                   | `null`           |
| `pickermany`       | `(name, label, source)`                                                      | `string[]` values                                          | `[]`             |
| `userpicker`       | `(name, label, source)`                                                      | user id `string` or `null`                                 | `null`           |
| `userpickermany`   | `(name, label, source)`                                                      | `string[]` user ids                                        | `[]`             |
| `cohortpicker`     | `(name, label, source)`                                                      | cohort id `string` or `null`                               | `null`           |
| `cohortpickermany` | `(name, label, source)`                                                      | `string[]` cohort ids                                      | `[]`             |
| `tags`             | `(name, label, tagarea, attributes)`                                         | `string[]` tag names                                       | `[]`             |
| `checkbox`         | `(name, label, text, attributes)`                                            | `1` or `0`                                                 | `0`              |
| `yesno`            | `(name, label)`                                                              | `1` or `0`                                                 | never empty      |
| `radios`           | `(name, label, options, inline)`                                             | option key or `null`                                       | `null`           |
| `radiocards`       | `(name, label, options, descriptions = [])`                                  | option key or `null`                                       | `null`           |
| `checkboxes`       | `(name, label, options, inline)`                                             | `string[]` of option keys                                  | `[]`             |
| `checkboxcards`    | `(name, label, options, descriptions = [])`                                  | `string[]` of option keys                                  | `[]`             |
| `select`           | `(name, label, options, attributes)`                                         | option key or `null`                                       | `null`           |
| `multiselect`      | `(name, label, options, attributes)`                                         | `string[]` of option keys                                  | `[]`             |
| `hidden`           | `(name, type = null)`                                                        | `string`, `int` for `param::INT`, or `null`                | `null`           |
| `info`             | `(name, label, default = null, format = info::STRING)`                       | not returned                                               |                  |
| `inforawhtml`      | `(name, label, html)`                                                        | not returned                                               |                  |
| `section`          | `(name, label)`                                                              | not returned                                               |                  |
| `collapsible`      | `(name, label, statekey = null)`                                             | not returned                                               |                  |
| `customfields`     | `(name, handler, instanceid)`                                                | not returned, see [Custom fields](#custom-fields)          |                  |
| `buttons`          | `(name)`                                                                     | not returned                                               |                  |
| `submit`, `cancel` | `(name = 'submit'/'cancel', label = null)`                                   | not returned                                               |                  |
| `reload`           | `(name, label)`                                                              | not returned                                               |                  |
| `download`         | `(name = 'download', label = null)`                                          | not returned                                               |                  |

### Text

`text` and `textarea` clean their values with `core\param::TEXT`, which removes all tags except
multilang tags. That is what names and titles need. If the value must stay exactly as typed, for
example JSON or code, use the `rawtext` type. The `email`, `url`, `tel` and `search` types are
cleaned and then validated.

### Info

`info` shows a value from current data, or its default. By default it uses `format_string()`, which
suits names and titles. `info::PLAIN` escapes the text and keeps line breaks, good for idnumbers and
URLs. `info::HTML` and `info::MARKDOWN` use `format_text()` with that format, and `info::TEXTFORMAT`
takes the format from `<name>format` in current data. `inforawhtml` shows trusted HTML written in the
definition, never data; with an empty label it takes the whole row.

### Numbers

`number` is a text input with a numeric keyboard, not `type="number"`, because spinner arrows and the
mouse wheel change values without the user noticing. The `decimals` attribute says how many decimal
places are allowed. With 0, the default, you get an `int`, otherwise a `float`. `min` and `max` must
fit the decimal places. A comma works as decimal separator and trailing zeros are ignored. If you
need steps like 0.5 or units, use a `text` element with a validator.

### Dates and periods

`datetime` is the only date element, because Moodle stores timestamps. It is a text input in the
user's timezone with a date picker on top. There is no "enabled" checkbox, an empty value is simply
`null`. With JavaScript the typed text is sent to a small server endpoint and the form submits the
timestamp. Without JavaScript the server parses the text itself. The display format is always the
`muform_datetimeformat` lang string of the user's language: `Y-m-d H:i` in English, `n/j/Y g:i A`
in US English (`lang/en_us` of tool_mulib overrides only this string) and `j. n. Y H:i` in Czech. Forms cannot change it, so users see and type dates the same way everywhere.

Users may type:

- numeric dates in the day and month order of the display format, with `.`, `/` or `-` and any
  spaces: `4. 11. 2026 14:30` or `4.11.26 14.30` in Czech. ISO dates with the year first, like
  `2026-11-04T14:30`, work in every language, and they are the only numeric dates the English ISO
  format accepts;
- a date without time, which gets the default time of the element. That is `00:00` unless the form sets
  something else, for example `->set_default_time('23:59')` for end dates or `'15:00'` for events that
  usually start in the afternoon. The picker opens empty fields on today at the same time;
- a 2-digit year, which means 20yy, or a day and month with the closing dot, `4. 11.`, which means the
  current year;
- times with `AM`/`PM` in any case, with or without dots and space, `2:30pm` or `2 p.m.`, and 24 hour
  times in every language. Formats with AM/PM also switch the picker hours to `12 AM … 11 PM`;
- a time only, `14:30` or `2 pm`, which means today;
- text matching the display format exactly, if a language uses one with month names.

Anything else is invalid. Nothing is guessed: `4.11` might be a time, `tomorrow` works only in English,
and PHP would read `4/11/2026` as April. In Behat use core timestamps such as `##tomorrow noon##`.

The picker takes the first day of the week from Moodle (`calendar_startwday` and the user preference),
and month and weekday names from the browser in the page language. "Today" is in the user's Moodle
timezone. The timezone line under the input is only shown to users whose timezone differs from the
site timezone.

`datetimerange` is for sessions and events: one text input with start and end, such as
`2026-11-06 11:30 – 12:30`, and the value `['start' => int, 'end' => int]` or `null`. Current data
may be an array or object with `start` and `end` keys. A date and a start time with two end
times is what people expect. It also avoids an end date that is wrong when the session crosses
midnight in the user's timezone.

- The start is anything `datetime` accepts. `set_default_time()` works the same way.
- The separator is an en or em dash, with or without spaces, or a hyphen with spaces around it. Dates
  contain hyphens too, so every possible split is tried and exactly one must give a valid range.
- Ranges are at most 24 hours long, by design. Use two `datetime` elements for anything that may
  last longer. The limit is the same wall clock time on the next day, so a range over a daylight
  saving change may have 23 or 25 hours.
- An end time without a date means the first such time after the start: `23:30 - 01:30` ends
  the next day, `11:30 - 11:30` is a whole day. `01:30 +1` (also `01:30+1`) and a full end date
  such as `2026-11-07 01:30` are accepted too, they must not go past the limit, so `11:30 - 12:30 +1`
  is invalid. Dotted times like `13.30` are fine in the end.
- Text with only a start gets `set_default_duration($seconds)`; without a default
  duration the end is required. `set_max_duration($seconds)` sets a shorter limit and limits the
  durations offered by the picker. Longer values from code, such as current data, a default or
  submitted timestamps, are trimmed to 24 hours with a debugging message, longer default durations too.
- The field always shows the start and the end time, with ` +1` when the end is on the next day in the
  user's timezone. Users in other timezones may see the `+1` or not, the timestamps stay the same. A hint
  under the input shows the duration, and the end date when it is on another day. Across a daylight
  saving change the hint shows the real duration: `01:30 – 03:30` lasts one hour in spring.
- The picker shows the calendar with start time, end time and a duration select. Changing the start
  keeps the duration. Picking an end time sets the first such time after the start.
- For reading, `calendar::format_range_html($range, $tz, $lang)` prints the airline style
  `2026-11-06 23:30 – 01:30<sup>+1</sup>` with the full end date in the title. Frozen elements use it.
  Copied text is `… 01:30+1`, which the element accepts again.

`duration` is a number of seconds and never `null`. It shows one input per unit, days, hours and
minutes by default, and adds them up on the server, so 90 minutes comes back as 1 hour 30 minutes.
If the stored value needs a smaller unit than the ones configured, that input appears too, so
nothing gets rounded. Required means more than zero.

`dateinterval` is for calendar periods that are added to dates with PHP `DateInterval`, where seconds
do not work. The value is an ISO 8601 duration like `P1Y6M`, or `null`. Nothing is converted, 14
months stay `P14M`.

The date and interval code lives in `tool_mulib\muform\util\calendar`.

### Secrets and shared keys

`secret` and `sharedkey` replace `passwordunmask`, and neither is meant for user passwords. A `secret`
is something a machine uses, like a database password or an API key, and its current value never
reaches the browser. A `sharedkey` is given to people, like an enrolment key, and anybody who may
edit it may also see it. Both are masked text inputs, so browsers do not try to save them. For the
same reason never call these elements `password`.

The input is always empty. Current data only tells the form whether a value exists: pass `true` for
a stored secret, or the key itself for a `sharedkey`. The returned value is `null` when nothing
changed, an empty string when the user cleared it (only with `allowclear`), and otherwise the new
value exactly as typed. So the handler script stores it only when it is not `null`:

```php
if ($data->dbsecret !== null) {
    $record->dbsecret = $data->dbsecret;
}
```

### Files

`filemanager` is core's file manager. Its value is the draft item id. Tell it where the files are
stored with a `tool_mulib\muform\util\file_area`, either in current data or with `set_file_area()`,
and after saving the record call `save_area()`. For a new record the item id is not known yet, so
create the area with `null` and set the id after the insert:

```php
$this->add(new filemanager('attachments', 'Attachments', 5, ['.pdf', 'image']));

// In the handler script, current data has
// ['attachments' => new file_area($context, 'tool_x', 'attachments', $record->id)]
$record->id = $DB->insert_record('tool_x_thing', $record);
$element = $form->get_element('attachments');
$element->get_file_area()->set_itemid($record->id);
$element->save_area();
```

Wrong file types or too many files are form errors, files are never dropped silently. `get_files()`
returns the draft files, which is handy for imports that only read a file.

### Editor

`editor` uses the user's preferred editor. Next to the text it returns `<name>format` and
`<name>draftitemid`. HTML is cleaned with `clean_text()` both when loaded and when submitted. There
is no trusttext; a form that really needs raw HTML calls `allow_unsafe_rawhtml()` and shows a
warning. Files work like in `filemanager`: the text already contains `@@PLUGINFILE@@` links, so the
handler script stores the text and calls `save_area()`.

```php
$this->add(new editor('description', 'Description', -1));

// In the handler script
$record->description = $data->description;
$record->descriptionformat = $data->descriptionformat;
$DB->update_record('tool_x_thing', $record);
$form->get_element('description')->save_area();
```

### Autocomplete

`autocomplete` picks one value, `autocompletemany` a list. Both get a source object that decides
everything, and the same source class serves both elements. Sources live in
`<component>\muform\autocomplete\` and extend `tool_mulib\muform\autocomplete\base`. The constructor
takes plain ids and checks access, `get_args()` returns them, so the shared endpoint in tool_mulib can
create the same source again. Plugins never add their own endpoints.

- `search($query, $maxitems, $exclude)` finds options. `$exclude` holds the values already selected
  in an `autocompletemany` element. Excluding them in SQL keeps `$maxitems` accurate, but sources may
  ignore it: the endpoint never offers selected values again.
- Labels of stored values come from `labels(array)`, or from `label_value(string)` called for each
  value by the default `labels()`.
- Values may be refused with a reason by `validate(array)`, or by `validate_value(string)` called for
  each value by the default `validate()`.

Override whichever is simpler: per-value hooks for checks of one record, the array methods when one
query handles all values, as the traits do.

```php
namespace tool_muprog\muform\autocomplete;

final class program_allocation_userids extends \tool_mulib\muform\autocomplete\base {
    use \tool_mulib\muform\util\autocomplete\user_trait;

    public function __construct(private int $programid) {
        $this->context = program::get_context($programid);
        require_capability('tool/muprog:allocate', $this->context);
    }

    public function get_args(): array {
        return [$this->programid];
    }

    public function search(string $query, int $maxitems, array $exclude): ?array {
        return $this->search_users($this->context, $query, $maxitems, $exclude, $this->get_where());
    }

    public function labels(array $values): array {
        return $this->user_labels($this->context, $values, $this->get_where());
    }

    public function validate(array $values): array {
        return $this->validate_users($values);
    }
}

final class user_mapping_userid extends \tool_mulib\muform\autocomplete\base {
    // ...
    protected function validate_value(string $value): ?string {
        return mapping::fetch_by_userid((int)$value) ? get_string('error_usermapped', 'auth_musaml') : null;
    }
}
```

A value without a label is rejected on submit, which is how permissions are enforced. When more than
`get_maxitems()` options match, `search()` returns `null` and the user is asked to type more. The
traits in `classes/muform/util/autocomplete/` cover users, courses and category contexts, and
`tool_mulib\local\search_util` has the search SQL. Cohorts are selected with the [cohort picker](#cohort-picker),
not with autocompletes. `site_users` offers all users of a context to users
with `moodle/user:viewalldetails`.

### Picker

`picker` picks one value, `pickermany` a list. Use them when typing part of a name is not enough to
find the right item: users need columns, filters or a tree to see what they pick. For Moodle users use
[User picker](#user-picker) elements, `picker` and `pickermany` are for picker families of plugins. Selected values
are pills with a remove button. The button after them opens a panel over the right half of the
screen, and the form stays visible on the left. The panel is a native modal `<dialog>` of its own, not
a muform dialog, so it also works over forms in muform dialogs and wizards.

Pickers come in families. A family is three things:

- an abstract source base extending `tool_mulib\muform\picker\base`, with one subclass per usage;
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
namespace tool_x\local\categorypicker;

abstract class base extends \tool_mulib\muform\picker\base {
    final public function get_panel(): string {
        return 'tool_x/categorypicker/panel';
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
below any installed component (`tool_x\muform\somepicker\base` accepts `tool_y\muform\somepicker\abc`).

```php
$source = \tool_x\local\categorypicker\base::instantiate($body['source'], $body['args']);
```

The panel component gets `PickerPanelProps` from `tool_mulib/muform/pickerpanel`: `source`
(`class` and `args`), `multiple`, `selected` (values already in the field, never offered again),
`strings`, `onPick(items)` and `onClose()`. `onPick` returns the picked values with their label HTML,
so the element never fetches labels itself. `openPanel()` from the same module opens a panel
outside forms, for example in an editor that adds several items at once.

### User picker

`userpicker` picks one user, `userpickermany` a list. They are pickers with a panel for Moodle users:
one page of users at a time with full names and the identity fields visible in the source context
(`showuseridentity` and `moodle/site:viewuseridentity`), searching names and those fields. Use them
instead of a user autocomplete when users need to see who they pick, for example when allocating users.

The source says where the users are picked from, the same way as autocomplete sources. It extends
`tool_mulib\muform\userpicker\base`, lives in `<component>\muform\userpicker\` and serves both elements.
Deleted, unconfirmed and guest users are never offered and tenant rules apply, the same as in the user
autocomplete. Values are user ids.

```php
namespace tool_muprog\muform\userpicker;

final class source_manual_allocate_users extends \tool_mulib\muform\userpicker\base {
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
`moodle/user:viewalldetails`. The search endpoint is `tool_mulib\route\api\muform\userpicker`.
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

`tool_mulib\muform\picker\filter::boolean()` has Yes and No values, `filter::select()` a list of options
with plain text labels (a search box is added for more than 10); conditions use table alias `u`. The
last argument is the default value, without it the filter starts in "Add filter".
`filter::autocomplete($key, $label, $source, $where)` is for values that cannot be listed: the editor searches
an [autocomplete](#autocomplete) source through the autocomplete endpoint and only values known to `labels()`
of the source are accepted, so the source is the access control of the filter values - the "Cohort" filter
uses `tool_mulib\muform\autocomplete\member_cohorts`. `set_pinned()` keeps
the pill in the bar when it has no value, as "Authentication: Any" (the second argument replaces "Any").
Keys are unique in the source: lowercase letters, digits and underscores. Filters only narrow down the
list of candidates, `validate()` and `get_selected_sql()` decide what may be stored.

The filters are not specific to users, any picker family can have them:

- the family base lets sources define `tool_mulib\muform\picker\filter` objects with conditions on its own table aliases;
- the family endpoint creates `new filter_set($filters, filter_set::clean_values($body['filters'] ?? null))`,
  adds `get_sqls()` to its query (or reads `get_value($key)`) and returns `export()` in the answer;
- `get_panel_strings()` of the family adds `filter_set::get_panel_strings()`;
- the panel renders `<Filters filters strings onChange onCancel />` from `tool_mulib/muform/pickerfilters` and
  sends `filters` as an object of values by keys once the user changed one; until then the endpoint
  applies the default values.

### Cohort picker

`cohortpicker` picks one cohort, `cohortpickermany` a list. The panel shows cohorts inside the tree of
course categories they belong to: "System" and each category are headings, the cohorts are listed below
their category. Only branches with cohorts are shown, and a search in names, ID numbers and descriptions
keeps the found cohorts in their place of the tree, so cohorts with the same name can be told apart.
At most 200 cohorts are listed, with more the panel asks to refine the search.

The source extends `tool_mulib\muform\cohortpicker\base`, lives in `<component>\muform\cohortpicker\`
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
namespace tool_muprog\muform\cohortpicker;

final class source_cohort_edit_cohortids extends \tool_mulib\muform\cohortpicker\base {
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
`tool_mulib\route\api\muform\cohortpicker`.
In Behat, table values are cohort names, ID numbers or ids.

### Tags

`tags` edits core tags of one item. Instead of a source it gets a tag area class, a subclass of
`tool_mulib\muform\tagarea\base`, which works the same way: the constructor takes plain ids and checks
capabilities, and `get_args()` lets the endpoint recreate it. After saving the item call
`$form->get_element('tags')->save($itemid)`. Tag names that core would change, or that contain
commas, are errors rather than being changed silently. `tool_mulib\muform\tagarea\course` handles
course tags.

### Hidden

You rarely need `hidden`, because ids come from page parameters. Without a type it ignores the
submitted value and uses current data. With a `core\param` type it accepts the submitted value, and
if cleaning would change it, that is an error.

### Options

`radios`, `checkboxes`, `select` and `multiselect` take an array of labels indexed by keys, or a
`tool_mulib\muform\util\options` object when you need groups. Labels must be strings, so convert
`lang_string` values with `array_map('strval', ...)`. An empty key in `select` is the "Choose..."
option and returns `null`. A `select` without it never returns `null`: the first option is used when
no value is given (browsers show it selected anyway) and the required marker is not shown, because
the user cannot leave it empty. A submitted value outside the options is a validation error, such a
value in current data or default triggers developer debugging and the first option is used. A frozen
`select` returns the given value unchanged, even when it is not among the options. For short lists
like roles, `checkboxes` is nicer than `multiselect`.

### Cards

`radiocards` and `checkboxcards` work exactly like `radios` and `checkboxes`, but every option is a
card with its title and an optional plain text description underneath, indexed by the same keys as
the options. Descriptions are always visible and linked to the input with `aria-describedby`, the
whole card is clickable. Use them instead of help buttons when users must understand what the
options mean before choosing, for example cohort types or consequences of a conversion. There is
no inline variant. The look is inspired by Bootstrap 6 field group cards.

Unlike `radios`, `radiocards` is required by default: submitting without a selection is a validation
error, use `set_default()` to preselect an option or `set_required(false)` to allow no selection.
Descriptions are plain text on purpose: the clickable card cannot contain links or other interactive
content, and screen readers read the description as text.

Cards are shown in two columns when the element is at least 48rem wide, such as in full page forms
and `xl` dialogs on large screens, `lg` and smaller dialogs keep one column. The layout follows the
width of the element, not the screen (CSS container query). Use `->set_max_columns(1)` when long
descriptions do not read well side by side.

### Yes or No

`yesno` asks a question with two inline radios, Yes and No, and returns 1 or 0 like `checkbox`. Use it
instead of a Yes/No `select`. A frozen `yesno` shows just "Yes" or "No" as text. In Behat tables it
takes `1`, `yes` or `Yes` and `0`, `no` or `No`, and an empty cell leaves the answer unchanged.

### Attributes and setters

Attributes are the usual HTML attributes of each element, like `maxlength`, `min`, `rows` or
`placeholder`. Unknown ones are ignored with a debugging message. Text-like inputs also accept
`width` with `small`, `medium` or `full`.
Buttons accept `primary`, and `link` with `small` for minor actions inside the form such as adding or
removing a row.

`set_required(true)` makes a value required. `set_required_marker(true)` only shows the marker and
is meant for your own validator, usually `required_if_visible` for fields that appear through display
rules. `set_frozen(true)` shows the current value as text and ignores anything submitted; it is the
only way to make a value unchangeable. `add_validator()`, `add_help_button()` and `set_template()` do
what they say. Setters that affect the value throw an exception once the element was added.

Never use the HTML `required` attribute on a field that can be hidden, the browser would refuse to
submit the form because of a field the user cannot see.

## Display rules

```php
$dm = $this->get_display_manager();
$dm->hide_if('message', 'notify', 'notchecked');
$dm->disable_if('priority', 'level', 'in', ['low', 'none']);
```

The operators are `eq`, `neq`, `in`, `notin`, `checked`, `notchecked`, `empty` and `notempty`. Rules
look at values only, never at whether another element is hidden, so the result is always predictable.
Rules compare the same value as `get_data()` returns: an empty `duration` is `0`, so use
`hide_if('x', 'interval', 'eq', 0)` rather than `empty`.
A rule on a section or button row applies to everything inside it. The browser uses exactly the same
logic. A validator can ask `is_hidden()` on the display manager, but that only says what the browser
would show.

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

Validators run only after submission, when all elements exist. They get the whole error array, so
they may add errors to other elements too. A closure with the same signature works as well.

## Templates

Everything passed to templates is escaped with `clean_string()` and printed with `{{ }}`. Only keys
ending in `html` are printed raw. Element code never calls `format_string()`; the code creating the
form formats labels once, and escaping again does no harm because `clean_string()` does not double
escape.

Element templates extend `tool_mulib/muform/element/wrapper` and fill its `{{$control}}` block. Any
template can be replaced with `set_template()`, also from the hook, and
`$form->render($OUTPUT, 'compact')` switches to `-compact` variants where they exist.

## Layout elements

`section`, `collapsible` and `buttons` hold other elements, added with `$this->add($element, 'parentname')`. They
return no data. Their templates get the children in order as `elements` and by name as
`elements_byname`, so a custom template can place each child where it wants. It must render every
child though, otherwise that child is not submitted.

`collapsible` is a section that the user can collapse, a Bootstrap accordion item made of a native
`details` element with the label in its header and an optional `set_summary()` text next to it. Collapsing is not
hiding: collapsed elements are submitted and validated, display rules are still the only way to hide
something.
- It is always expanded by default. A section the user collapses stays collapsed while the page passes
  the same state key (third constructor argument), kept in the browser session storage. Use a key that
  ends with the work, such as the wizard instance id plus the record, so the state never leaks into the
  next run. Without a key the state is not remembered.
- A section with an error inside is expanded and its header shows an error icon, both when the server
  renders errors and after a failed submission in the browser.
- With two or more collapsible sections the form adds "Expand all" and "Collapse all" above the first one.
- Behat: the label is the text of the `summary`, click it to toggle the section.

Buttons have `set_confirm($question)`: the browser asks before the button submits, typically a reload
button that throws away entered data such as "Remove row". There is no confirmation without JavaScript.

For a really custom part of a page, such as an editor with a preview pane, write your own layout
element in your plugin with its own template and ES module. The module should find its children by
`[data-muform-name="..."]` inside its wrapper, not by id, because ids include a form suffix.

All shipped elements are `final`, because of rule 4. If they could be extended, every protected
method would become an API that can never change. If you need different behaviour, write a new
element; if you need a different look, use a template.

## Writing a new element

Extend `tool_mulib\muform\element` in the `<component>\muform\element` namespace. The class name is
the element type, and the template, ES module and Behat helper are found by that name. Override
`parse_value()` to turn the raw value into its final type and report errors; do not check required
values there. Override `has_required_value()` if empty means something other than `null` or `''`, and
`get_template_data()` for anything the template needs. Add a template with an example context and a
PHPUnit test.

## The hook

`tool_mulib\hook\muform_definition` runs after `definition()`. It may add elements, validators and
display rules, and change templates, hints and help buttons. It cannot change values, defaults,
attributes or the frozen and required state of existing elements.

## Behat

```gherkin
When I set the following muform fields:
  | fullname | Jane Doe         |
  | roles    | manager, teacher |
And I set the following muform fields in the "dialog[open]" "css_element":
  | name | x |
Then the following muform fields match:
  | fullname | Jane Doe         |
  | roles    | teacher, manager |
```

The first column is the element name, or its exact label when no element has that name. The second
column is what a user would enter: option keys (or labels), comma separated for multiple values,
`2026-09-26 10:00` for dates, `2026-11-06 23:30 - 01:30` for date ranges, seconds for durations, ISO strings for intervals, `[clear]` to clear a
secret. Checkboxes are checked by anything except empty or `0`. Files are uploaded with
`I upload "lib/tests/fixtures/empty.txt" file to "attachments" muform filemanager`.

`I type "Faculty" into the "tenantid" muform search field` types into an autocomplete without picking
anything, and `the open muform list should be fully visible` checks the result list is not cut off.

Values of `picker` and `pickermany` depend on the panel family. The element helper removes the
pills, opens the panel and leaves each comma separated item to the family's panel helper:
module `<component>/<path>` is served by class `<component>\tests\muform\picker\<path with _>`
in `<component>/tests/classes/muform/picker/`, extending `tool_mulib\tests\muform\picker\panel_base`
(`is_ready()`, `search()`, `pick()`, `confirm()` and `label_matches()`). Steps working with a panel
opened outside a form use `panel_base::wait_for_panel()`.

Every element type has a Behat helper in `tests/classes/muform/element/<type>.php`, a fixture page in
`tests/behat/fixtures/` and its own feature file. When you write a new element, copy the `text`
fixture and feature, they show the usual set of scenarios. Fixture pages must check
`BEHAT_SITE_RUNNING` right after `config.php`.

## JavaScript

Every element has an ES module in `js/esm/src/muform/element/<type>.ts`. The form loads one module
per element on the page. Build with `nvm use && npx grunt esm` from the plugin directory and commit the build.

A module exports a class based on `Element`, or on `NativeElement` for normal inputs. The form code
never touches the markup inside an element; it reads `getValue()`, updates `state` and calls
`syncUI()`, and the element decides how to show it. A React widget simply gets the state as props.
Elements report changes with a `muform:change` event and can call `form.submit()`, `form.reload()`
or `form.cancel()`.

The browser validates on submit and when the user leaves a field, but the server always has the
final word. Errors must never move what the user is clicking: a field left with a mouse or finger is
validated only after the press ended and the click landed; leaving with the keyboard validates at once. Pressing a button of the form never validates the field
left, the submit does. A form is only submitted once, the buttons are disabled until a new form arrives.

Forms are watched by the core change checker, so leaving a page with changed values asks for
confirmation. `$form->set_dirty(true)` in the handler, or `form.markDirty()` in JavaScript, marks a form
as having unsaved work even without changes, for example data prefilled from an import that is not
saved yet. Submit, reload and cancel never ask.
Jest tests are in `js/esm/tests/`.

## Dialogs

Any page can open a form in a native `<dialog>`, and the form does not know about it. The dialog
sends an `X-Muform-Dialog` header, so `handler::from_request()` returns the dialog handler, which
answers with JSON instead of a page. Router controllers pass the request and response to
`from_request()` and return the answers instead of exiting; `classes/route/controller/muform_fixture.php`
is an example.

`render()` also accepts a callable returning HTML, for pages that are not a single form.
`$handler->is_dialog()` is for flows that continue differently in a dialog, for example showing step
two straight away instead of redirecting.

The trigger is a `button`, `link` or `icon` from `tool_mulib\output\muform\dialog`. You can set the
size and title, and what happens after a successful submit: `ACTION_RELOAD` reloads the page,
`ACTION_REDIRECT` goes to the return URL and `ACTION_NOTHING` just closes the dialog and fires a
`muform:dialog-submitted` event with the handler's data. Links keep their real `href`, so without
JavaScript they open the normal page. `create_report_action()` makes a report builder action and
`dropdown::add_dialog()` adds a link to a header menu.

## Wizards

Multi-step full page forms, open them with plain links, never in dialogs. State is one row in
`tool_mulib_muform_wizard`, bound to the user, login session and wizard name; put the page parameters
into the name. Rows older than a day are deleted.

Stages never change, `start()` and `load()` get the same stage names with labels. Each stage is its
own form class with the stored data as current data. A stage is completed only after
`complete_stage()`, valid data never skips a stage. Completing a stage again resets the later ones.
Stages that do not apply now are passed to `resolve_stages()` as skipped, the stepper shows them
greyed out. Stage names are lowercase letters, `?stage=` requests a completed stage.
`set_data()` stores defaults without completing anything.

Write wizards as a `wizard_page` class (below). The `wizard` class used directly looks like this,
it is what `wizard_page` does for you:

```php
$name = 'auth_musaml_user_upload:' . $idp->id;
$stages = ['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'];
$wizard = wizard::load($name, $stages);
if (!$wizard) {
    $wizard = wizard::start($name, $stages);
    redirect($wizard->get_url($pageurl));
}
$data = $wizard->get_data();
$isvalid = function (string $stage) use ($data): bool {
    return match ($stage) {
        'source' => mapping_import::is_source_valid($data),
        'columns' => mapping_import::is_columns_valid($data),
        'options' => mapping_import::is_options_valid($data),
    };
};
$stage = $wizard->resolve_stages($isvalid);
if ($wizard->is_finished()) {
    // All stages completed, do the job, delete the wizard and redirect.
}
$form = new source($wizard->get_url($pageurl, $stage), $data, ['idp' => $idp]);
if ($form->is_cancelled()) {
    $wizard->delete();
    redirect($returnurl);
}
if ($form->is_reloaded() && $wizard->get_previous_stage() !== null && $form->get_element('back')->get_value()) {
    // Back without changes, wizard_page also handles Back with changes.
    redirect($wizard->get_url($pageurl, $wizard->get_previous_stage()));
}
if ($submitted = $form->get_data()) {
    $wizard->complete_stage($stage, mapping_import::save_source($data, $submitted, $form));
    redirect($wizard->get_url($pageurl));
}
echo $OUTPUT->header();
echo $wizard->render($OUTPUT, $pageurl, $form);
```

### Wizard page class

The script above is the same in every wizard, so write a class extending `wizard_page` instead and
keep the entry script thin: page parameters, `require_login()`, then
`(new \myplugin\local\wizard\user_upload\wizard($id))->execute();`. Keep everything of one wizard
together: `<component>\local\wizard\<name>\wizard` with one form class per stage next to it, named
after the stage (`source`, `columns`, `options`). Code shared by several wizards goes to
`<component>\local\wizard\shared`.

```php
final class wizard extends \tool_mulib\muform\wizard_page {
    public function __construct(private stdClass $idp) {
        require_capability('auth/musaml:managemappings', \core\context\system::instance());
    }
    protected function get_name(): string {
        return 'auth_musaml_user_upload:' . $this->idp->id;
    }
    protected function get_stages(): array {
        return ['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'];
    }
    protected function is_stage_valid(string $stage): bool { ... }      // from $this->state
    protected function create_form(string $stage, url $formurl): form { ... }
    protected function submit_stage(string $stage, stdClass $data, form $form): ?url {
        $this->state = ...;     // store the stage, it is completed after returning null
        return null;
    }
    protected function finish(): ?url { ... }   // all stages completed: do the job, delete, return where to go
    // Also get_page_url(), get_return_url() and setup_page().
}
```

`execute()` does the rest: loads or starts the state, resolves the stage, handles Cancel and Back,
completes the stage and prints the page. Hooks work with these properties:

- `$this->state` - the stored data, loaded before `check_state()`; `submit_stage()` and
  `reload_stage()` change it and the page stores it;
- `$this->wizard` - the `wizard` object, for `set_data()` in `start_state()` and `delete()` when
  finishing;
- `$this->stage` and `$this->formurl` - the current stage and its URL, set before `create_form()`.

`setup_page()` sets `$PAGE` (context, URL, layout, title, heading, navigation). `submit_stage()`
returns null to complete the stage and continue, or a URL to go elsewhere without completing it:
after finishing (delete the wizard first), or `$this->formurl` to show the stage again. Values
computed in `create_form()`, such as problems shown on a summary stage, may be kept in properties
for `submit_stage()` of the same request. Optional hooks:

- `start_state()` stores defaults of a new wizard or completes stages that need no input, and may
  return the stage to open;
- `get_unavailable_reason()` returns why the wizard cannot be used now (for example the program was
  archived); the stored wizard is deleted, the reason is shown as a notification on the return page;
- `check_state()` stops a wizard whose stored state cannot be used any more;
- `get_skipped_stages()` lists stages that do not apply now;
- `finish()` is for wizards finished when all stages are completed; wizards with a last summary
  stage finish in `submit_stage()` of that stage instead, by returning the URL to go to;
- `reload_stage()` handles reload buttons other than Back, such as adding a row, see below;
- `get_locked_stages()` lists stages that cannot be changed once completed, together with all stages
  before them; `create_form()` renders them read-only when `is_stage_locked()`, Continue and Back
  there only move to the next or previous stage and nothing is submitted;
- `keeps_later_stages()` is for wizards editing existing data: all stages are completed from the
  start, submitting one goes to the following stage and the later ones stay completed;
- `get_heading()` is printed above the stepper, when the page heading is not the wizard title;
- `render_page()` prints the page.

### Wizard access control

`wizard_page` does no access control of its own. Every request goes through the same steps, so
follow these rules in every wizard:

1. The entry script reads the page parameters, calls `require_login()` and then
   `(new wizard(...))->execute()`, nothing else.
2. The constructor loads the records named by the parameters with `MUST_EXIST`, finds their
   context and checks the capability there with `require_capability()`, or throws
   `required_capability_exception` for rules that are not a single capability. It runs on every
   request, so a revoked permission stops the wizard at the next step.
3. The stored state is input from earlier requests. Anything chosen in an earlier stage that
   depends on permissions or on the current data, such as an action, a type or a target record,
   is checked again before it is used, in `check_state()`, `is_stage_valid()` or `finish()`.
4. `get_unavailable_reason()` covers changes of the target that make the wizard pointless, such
   as an archived program, it is evaluated on every request too.
5. Rows of uploaded data pass the same checks as the single item pages, the user pickers and the
   web services of the same feature: tenant restrictions, protection of site administrators,
   deleted and suspended users. Keep each rule in one function and call it from all of them, an
   upload must never accept a user that the user picker would not offer.
6. Changes are written only in `submit_stage()` or `finish()`, which run after a muform submission
   with a valid sesskey. Never change data in `create_form()`, `start_state()` or
   `get_unavailable_reason()`, they also run on plain page loads.

Uploads: questions that affect the whole wizard (what to do with the uploaded rows, for example)
are asked before it, usually in a dialog form whose answers become page parameters and part of the
wizard name. The upload or pasted data is the first stage, stored parsed (never the draft item id of
the file), and locked: later stages depend on it, different data means starting again.

### Wizard buttons

Stage forms use `wizardcontinue` (submit named `submit`, "Continue") and `wizardback` (named `back`),
the first stage has no Back:

```php
$this->add(new buttons('buttons'));
$this->add(new wizardcontinue(), 'buttons');
$this->add(new wizardback(), 'buttons');
$this->add(new cancel(), 'buttons');
```

Back must not lose what the user entered, and the wizard never stores data that were not validated.
So when nothing was changed in the browser Back reloads the form and the wizard goes to the
previous stage; when something was changed Back submits the form for validation like Continue, the
stage is stored and completed, then the wizard goes back. Invalid values keep the user on the stage.
The browser picks the way, the server only sees a reload or a submission, so it cannot be made to
store unvalidated data. Without JavaScript Back always reloads.

Use `new wizardback(validate: false)` on a last stage whose submission finishes the wizard, there
Back never stores anything. A form counts as changed also when it was rendered from submitted data,
for example after a failed validation, or after `$form->set_changed(true)`.

`reload_stage()` of `wizard_page` only changes `$this->state`, for example adds a row with the typed
values. The page stores the changed state and marks the stage as changed (`wizard::mark_changed()`),
so Back validates it and leaving the stage asks for confirmation; the mark is forgotten when the
stage is completed.

Cancel and the final submit delete the row. The stepper is
horizontal for up to 6 steps when there is room, and a sticky column left of the form in wide pages.
With completed stages the form is marked dirty, so leaving the page asks for confirmation; stepper
links do not ask unless the current stage was changed.

## Custom fields

`customfields` edits the core custom fields of one instance. It adds a section per category and an
element per field named `customfield_<shortname>`, so the values arrive in `get_data()` like any
other. It loads stored values by itself, and `save()` writes them after the instance was saved:

```php
$this->add(new customfields('customfields', program_handler::create(), $program?->id));

if ($data = $form->get_data()) {
    $id = program::create($data)->id;
    $form->get_element('customfields')->save($id);
}
```

Values are stored exactly where core stores them, so display, reports, backup and deletion keep
working with core code. Text, textarea, checkbox, select, date, number and MuTMS credit fields are
supported; other types are left out. Shared categories are never shown, and fields the user may not
edit are skipped. In Behat use the `customfield_<shortname>` names, because labels can repeat.
