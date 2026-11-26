# Dates and the date panel

Dates are typed as text in the user's format and timezone, and the server parses them. The browser
never interprets dates itself. A calendar button next to the text opens the date panel over the right half
of the screen, the same `openPanel()` shell as [pickers](picker.md). The panel only edits date and time
parts and returns text, which goes through the same server normalisation as typed text.

## PHP: `tool_mulib\local\date_util`

- `parse($text, $tz, $lang, $defaulthour, $defaultminute)` returns a timestamp, or null for empty text, and
  throws `InvalidArgumentException` for invalid text. `describe()` returns text, timestamp and components.
- `parse_range()` and `describe_range()` handle ranges of at most one day (`datetimerange`).
  `format_range_html()` prints frozen values.
- `get_format($lang)` is the display format from the `muform_datetimeformat` string. `get_date_format()`,
  `get_time_format()` and `uses_12_hour_clock()` are derived from it.
- `get_picker_settings()` returns the settings of the current user for date inputs outside forms:
  timezone, lang, dateformat, step, firstday and hour12.
- Interval and duration helpers used by the `duration` and `dateinterval` elements.

Everything is explicit (timezone, language), so the stateless endpoints `/date/datetime` and
`/date/datetimerange` (`tool_mulib\route\api\date\*`) can use it without a session.

## JavaScript

- `tool_mulib/date/api`:
  - `normaliseText(text, settings)` and `normalise(input, text)` call the endpoint;
  - `formatComponents()` builds the text the panel returns;
  - `formatDate()` formats a timestamp client side, date only, for labels such as report filter chips;
  - `todayIn(timezone)` gives today in a timezone.
- `tool_mulib/date/rangeapi`: `normaliseRange()` for the `datetimerange` element.
- `tool_mulib/date/input`: a React text input with a calendar button for code outside forms. Its value is a
  timestamp and its settings come from `date_util::get_picker_settings()`.
  Never use native date inputs.
- `tool_mulib/date/panel`:
  - `openDatePanel(options, trigger)` resolves the applied `{text}`, or null when closed without applying;
  - the default export `DatePicker` is the calendar button used by the elements and `date/input`;
  - `getPickerStrings()` loads the strings.

## The panel

- **Calendar:**
  - month and year arrows, month and weekday names from `Intl` in the page language;
  - the first day of the week from Moodle;
  - "Today" in the timezone of the value;
  - keyboard: arrows, Home/End within the week, PageUp/PageDown by month (with Shift by year).
- **Layout:** the calendar is centred, and the time is below it.
- **Time:**
  - Hour and Minute dropdowns;
  - minutes in `step` increments, and a typed minute that is not a step value stays in the list;
  - hours `12 AM … 11 PM` when the display format uses AM/PM;
  - empty values start at the default time of the input;
  - the range mode has Start and End rows and a Duration dropdown.
- **Recently used:** values committed in date inputs of the browser tab, typed or picked, are listed below the
  time, latest first, without repeats, at most 10 (`tool_mulib/date/recent`, `sessionStorage`). Ranges have their
  own list, with durations. Click selects the value, double click applies it. The footer stays visible and the body
  scrolls.
- **Closing:** Escape and the backdrop close only the panel, so a dialog below stays open. Apply returns the
  text; Clear returns empty text.

**Modes**

| Mode | Used by | Behaviour |
|---|---|---|
| `single` | `datetime` element, `date/input` | one date and time; double click on a day applies |
| `range` | `datetimerange` element | start date and time, end time with "+N" when on a later day, and a duration select; changing the start keeps the duration; an end time means the first such time after the start; at most 24 hours |

## Behat

Values are typed text, see the muform Behat section. To use the panel, click the calendar button ("Choose
date and time" or "Choose date and time range"), then the buttons inside
`.tool_mulib-date-panel`: day numbers, "Next month" and "Apply". Hours and minutes are the "Hour" and "Minute"
selects.
