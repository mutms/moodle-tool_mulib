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
| `datetime`         | `(name, label, attributes, displayformat = null)`                            | `int` timestamp or `null`                                  | `null`           |
| `duration`         | `(name, label, units = ['d', 'h', 'i'])`                                     | `int` seconds                                              | `0`              |
| `dateinterval`     | `(name, label, units = ['y', 'm', 'w', 'd'])`                                | ISO 8601 duration `string` or `null`                       | `null`           |
| `secret`           | `(name, label, attributes, allowclear = false)`                              | `null` keep, `''` cleared, `string` new                    | see below        |
| `sharedkey`        | `(name, label, attributes, allowclear = false)`                              | `null` keep, `''` cleared, `string` new                    | see below        |
| `filemanager`      | `(name, label, maxfiles = null, acceptedtypes = null, allowsubdirs = false)` | `int` draft item id                                        | no files         |
| `editor`           | `(name, label, maxfiles = 0, allowsubdirs = false, attributes)`              | `string` text, plus `<name>format` and `<name>draftitemid` | blank text       |
| `autocomplete`     | `(name, label, source, attributes)`                                          | `string` value or `null`                                   | `null`           |
| `autocompletemany` | `(name, label, source, attributes)`                                          | `string[]` values                                          | `[]`             |
| `tags`             | `(name, label, tagarea, attributes)`                                         | `string[]` tag names                                       | `[]`             |
| `checkbox`         | `(name, label, text, attributes)`                                            | `1` or `0`                                                 | `0`              |
| `radios`           | `(name, label, options, inline)`                                             | option key or `null`                                       | `null`           |
| `checkboxes`       | `(name, label, options, inline)`                                             | `string[]` of option keys                                  | `[]`             |
| `select`           | `(name, label, options, attributes)`                                         | option key or `null`                                       | `null`           |
| `multiselect`      | `(name, label, options, attributes)`                                         | `string[]` of option keys                                  | `[]`             |
| `hidden`           | `(name, type = null)`                                                        | `string`, `int` for `param::INT`, or `null`                | `null`           |
| `info`             | `(name, label, default = null, format = info::STRING)`                       | not returned                                               |                  |
| `inforawhtml`      | `(name, label, html)`                                                        | not returned                                               |                  |
| `section`          | `(name, label)`                                                              | not returned                                               |                  |
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
timestamp. Without JavaScript the server parses the text itself: a timestamp, the display format, or
anything PHP understands, like `tomorrow 10:00`. The display format is the `muform_datetimeformat`
lang string, `Y-m-d H:i` by default.

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
everything. Its constructor takes plain ids and checks access. `search()` finds options,
`label()` or `labels()` return labels for stored values, and `validate()` can refuse a value with a
reason. `get_args()` returns the constructor arguments, so the shared endpoint in tool_mulib can
create the same source again. Plugins never add their own endpoints.

```php
namespace tool_muprog\muform\autocompletemany;

final class program_allocation_userids extends \tool_mulib\muform\autocompletemany\base {
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
```

A value without a label is rejected on submit, which is how permissions are enforced. When more than
`get_maxitems()` options match, `search()` returns `null` and the user is asked to type more. The
traits in `classes/muform/util/autocomplete/` cover users, cohorts and category contexts, and
`tool_mulib\local\search_util` has the search SQL.

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
option and returns `null`. For short lists like roles, `checkboxes` is nicer than `multiselect`.

### Attributes and setters

Attributes are the usual HTML attributes of each element, like `maxlength`, `min`, `rows` or
`placeholder`. Unknown ones are ignored with a debugging message. Text-like inputs also accept
`width` with `small`, `medium` or `full`.

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

`section` and `buttons` hold other elements, added with `$this->add($element, 'parentname')`. They
return no data. Their templates get the children in order as `elements` and by name as
`elements_byname`, so a custom template can place each child where it wants. It must render every
child though, otherwise that child is not submitted.

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
`2026-09-26 10:00` for dates, seconds for durations, ISO strings for intervals, `[clear]` to clear a
secret. Checkboxes are checked by anything except empty or `0`. Files are uploaded with
`I upload "lib/tests/fixtures/empty.txt" file to "attachments" muform filemanager`.

`I type "Faculty" into the "tenantid" muform search field` types into an autocomplete without picking
anything, and `the open muform list should be fully visible` checks the result list is not cut off.

Every element type has a Behat helper in `tests/classes/muform/element/<type>.php`, a fixture page in
`tests/behat/fixtures/` and its own feature file. When you write a new element, copy the `text`
fixture and feature, they show the usual set of scenarios. Fixture pages must check
`BEHAT_SITE_RUNNING` right after `config.php`.

## JavaScript

Every element has an ES module in `js/esm/src/muform/element/<type>.ts`. The form loads one module
per element on the page. Build with `npx grunt esm` from the Moodle root and commit the build.

A module exports a class based on `Element`, or on `NativeElement` for normal inputs. The form code
never touches the markup inside an element; it reads `getValue()`, updates `state` and calls
`syncUI()`, and the element decides how to show it. A React widget simply gets the state as props.
Elements report changes with a `muform:change` event and can call `form.submit()`, `form.reload()`
or `form.cancel()`.

The browser validates on submit and when the user leaves a changed field, but the server always has
the final word. A form is only submitted once, the buttons are disabled until a new form arrives.
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

Use a wizard when creating something takes several steps. The state is stored in
`tool_mulib_muform_wizard`, one row per running wizard, and the forms know nothing about it.

The row id travels in the URL, but the row can only be loaded by the same user in the same login
session and by the same wizard, because it is protected by a hash of all of these. Include the page
parameters in the wizard name, like `'auth_musaml_user_upload:' . $idp->id`, and the data cannot be
replayed against another record either. Old rows are deleted after a day.

The stored data is the only truth. On every request the script checks it stage by stage with
`wizard::resolve_stages()`, and the first stage that is not valid yet is the current one. Users may
go back to any finished stage, but never skip ahead. Each stage is its own form class and gets the
stored data as current data, so uploaded files survive moving between stages.

```php
$name = 'auth_musaml_user_upload:' . $idp->id;
$wizard = wizard::load($name, optional_param(wizard::PARAM, 0, PARAM_INT));
if (!$wizard) {
    $wizard = wizard::start($name);
    redirect($wizard->get_url($pageurl));
}
$data = $wizard->get_data();
$stages = wizard::resolve_stages(['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'],
    fn(string $stage) => mapping_import::is_stage_valid($stage, $data), optional_param(wizard::STAGE_PARAM, null, PARAM_ALPHA));
$stage = wizard::current_stage($stages);
$form = new user_upload_source($wizard->get_url($pageurl, $stage), $data, ['idp' => $idp]);
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

Wizards are full pages, so they use `redirect()` and `$OUTPUT` directly. The Back button is a
`reload` element, not `cancel`, because dialogs close on cancel. Cancel and the final submit delete
the row. `render()` shows the steps on the left and the form on the right.

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
