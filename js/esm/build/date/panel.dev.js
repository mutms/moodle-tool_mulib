var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Date and time picker panel over the right half of the screen, the same for forms and reports.
 *
 * The panel only edits date parts, the text it returns is parsed by the server. Modes: one date and time,
 * and a range of at most one day (datetimerange element, changing the start keeps the duration).
 * Month and weekday names come from Intl for display only,
 * the first day of week comes from Moodle. "Today" is in the timezone of the value.
 *
 * @module     tool_mulib/date/panel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import Pending from "@moodle/lms/core/pending";
import { getString } from "@moodle/lms/core/stringUtils";
import { useEffect, useId, useRef, useState } from "react";
import { openPanel } from "../picker/panel";
import { formatComponents, todayIn } from "./api";
import { isRecent, recentDates } from "./recent";
async function getPickerStrings() {
  const keys = [
    "pickdatetime",
    "today",
    "clear",
    "apply",
    "close",
    "hour",
    "minute",
    "monthprev",
    "monthnext",
    "yearprev",
    "yearnext",
    "recent"
  ];
  const [pick, today, clear, apply, close, hour, minute, monthprev, monthnext, yearprev, yearnext, recent] = await Promise.all(
    keys.map((key) => key === "close" ? getString("closebuttontitle", "core") : getString(`muform_${key}`, "tool_mulib"))
  );
  return { pick, today, clear, apply, close, hour, minute, monthprev, monthnext, yearprev, yearnext, recent };
}
__name(getPickerStrings, "getPickerStrings");
function firstDayOfWeek(moodle, lang) {
  if (moodle !== null && moodle !== void 0 && Number.isInteger(moodle) && moodle >= 0 && moodle <= 6) {
    return moodle === 0 ? 7 : moodle;
  }
  try {
    const locale = new Intl.Locale(lang);
    const info = locale.getWeekInfo ? locale.getWeekInfo() : locale.weekInfo;
    return info?.firstDay ?? 1;
  } catch {
    return 1;
  }
}
__name(firstDayOfWeek, "firstDayOfWeek");
function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}
__name(daysInMonth, "daysInMonth");
function moveDay(day, days, months = 0) {
  const lastDay = daysInMonth(
    new Date(day.year, day.month - 1 + months, 1).getFullYear(),
    new Date(day.year, day.month - 1 + months, 1).getMonth() + 1
  );
  const date = new Date(day.year, day.month - 1 + months, Math.min(day.day, lastDay) + days);
  return { ...day, year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
}
__name(moveDay, "moveDay");
function dayNumber(day) {
  return Date.UTC(day.year, day.month - 1, day.day) / 864e5;
}
__name(dayNumber, "dayNumber");
function wallMinutes(parts) {
  return Math.floor(Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute) / 6e4);
}
__name(wallMinutes, "wallMinutes");
function fromWallMinutes(minutes, second) {
  const date = new Date(minutes * 6e4);
  return {
    year: date.getUTCFullYear(),
    month: date.getUTCMonth() + 1,
    day: date.getUTCDate(),
    hour: date.getUTCHours(),
    minute: date.getUTCMinutes(),
    second
  };
}
__name(fromWallMinutes, "fromWallMinutes");
function rangeMinutes(end, start, range) {
  if (end) {
    const minutes = wallMinutes(end) - wallMinutes(start);
    if (minutes > 0 && minutes <= 24 * 60) {
      return minutes;
    }
  }
  return Math.max(1, Math.round((range.defaultDuration ?? 3600) / 60));
}
__name(rangeMinutes, "rangeMinutes");
function Calendar(props) {
  const { view, focus, firstDay, today, lang, strings } = props;
  const gridRef = useRef(null);
  const keyboard = useRef(false);
  useEffect(() => {
    if (keyboard.current) {
      gridRef.current?.querySelector('button[tabindex="0"]')?.focus();
      keyboard.current = false;
    }
  }, [focus]);
  const moveMonth = /* @__PURE__ */ __name((delta) => {
    const date = new Date(view.year, view.month - 1 + delta, 1);
    props.onView({ year: date.getFullYear(), month: date.getMonth() + 1 });
  }, "moveMonth");
  const moveFocus = /* @__PURE__ */ __name((day) => {
    keyboard.current = true;
    props.onFocus(day);
    props.onView({ year: day.year, month: day.month });
  }, "moveFocus");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    const weekday = (new Date(focus.year, focus.month - 1, focus.day).getDay() + 7 - firstDay) % 7;
    const moves = {
      ArrowLeft: /* @__PURE__ */ __name(() => moveDay(focus, -1), "ArrowLeft"),
      ArrowRight: /* @__PURE__ */ __name(() => moveDay(focus, 1), "ArrowRight"),
      ArrowUp: /* @__PURE__ */ __name(() => moveDay(focus, -7), "ArrowUp"),
      ArrowDown: /* @__PURE__ */ __name(() => moveDay(focus, 7), "ArrowDown"),
      Home: /* @__PURE__ */ __name(() => moveDay(focus, -weekday), "Home"),
      End: /* @__PURE__ */ __name(() => moveDay(focus, 6 - weekday), "End"),
      PageUp: /* @__PURE__ */ __name(() => moveDay(focus, 0, event.shiftKey ? -12 : -1), "PageUp"),
      PageDown: /* @__PURE__ */ __name(() => moveDay(focus, 0, event.shiftKey ? 12 : 1), "PageDown")
    };
    if (moves[event.key]) {
      event.preventDefault();
      moveFocus(moves[event.key]());
    }
  }, "onKeyDown");
  const monthName = new Intl.DateTimeFormat(lang, { month: "long", year: "numeric" }).format(new Date(view.year, view.month - 1, 1));
  const weekdayFormat = new Intl.DateTimeFormat(lang, { weekday: "short" });
  const weekdays = Array.from({ length: 7 }, (_, i) => {
    const weekday = (firstDay - 1 + i) % 7 + 1;
    return weekdayFormat.format(new Date(2026, 0, 4 + weekday));
  });
  const first = new Date(view.year, view.month - 1, 1);
  const firstWeekday = first.getDay() === 0 ? 7 : first.getDay();
  const cells = Array((firstWeekday - firstDay + 7) % 7).fill(null);
  for (let day = 1; day <= daysInMonth(view.year, view.month); day++) {
    cells.push(day);
  }
  while (cells.length % 7 !== 0) {
    cells.push(null);
  }
  const rows = [];
  for (let i = 0; i < cells.length; i += 7) {
    rows.push(cells.slice(i, i + 7));
  }
  const focusInView = focus.year === view.year && focus.month === view.month;
  const navButton = /* @__PURE__ */ __name((label, icon, delta) => /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-light", "aria-label": label, title: label, onClick: () => moveMonth(delta), children: /* @__PURE__ */ jsxDEV("i", { className: `fa ${icon}`, "aria-hidden": "true" }, void 0, false, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 309,
    columnNumber: 13
  }, this) }, void 0, false, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 308,
    columnNumber: 9
  }, this), "navButton");
  return /* @__PURE__ */ jsxDEV("div", { className: "tool_mulib-date-calendar", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center justify-content-between mb-2", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "d-flex gap-1", children: [
        navButton(strings.yearprev, "fa-angles-left", -12),
        navButton(strings.monthprev, "fa-chevron-left", -1)
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 316,
        columnNumber: 17
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "fw-bold", "aria-live": "polite", children: monthName }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 320,
        columnNumber: 17
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "d-flex gap-1", children: [
        navButton(strings.monthnext, "fa-chevron-right", 1),
        navButton(strings.yearnext, "fa-angles-right", 12)
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 321,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 315,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("table", { className: "table table-borderless text-center mb-0", ref: gridRef, role: "grid", onKeyDown, children: [
      /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: weekdays.map((name) => /* @__PURE__ */ jsxDEV("th", { scope: "col", className: "small fw-normal", children: name }, name, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 328,
        columnNumber: 49
      }, this)) }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 328,
        columnNumber: 21
      }, this) }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 327,
        columnNumber: 17
      }, this),
      /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((row, r) => /* @__PURE__ */ jsxDEV("tr", { children: row.map((number, c) => {
        if (number === null) {
          return /* @__PURE__ */ jsxDEV("td", {}, c, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
            lineNumber: 335,
            columnNumber: 44
          }, this);
        }
        const day = { year: view.year, month: view.month, day: number };
        const state = props.selection(day);
        const isToday = dayNumber(day) === dayNumber(today);
        const classes = [
          "btn",
          "tool_mulib-date-day",
          state ? "btn-primary" : "btn-light",
          isToday ? "fw-bold" : ""
        ];
        return /* @__PURE__ */ jsxDEV("td", { children: /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: classes.join(" ").trim(),
            tabIndex: (focusInView ? focus.day : 1) === number ? 0 : -1,
            "aria-pressed": state,
            "aria-current": isToday ? "date" : void 0,
            "data-tool-mulib-date-day": number,
            onClick: () => {
              props.onFocus(day);
              props.onSelect(day);
            },
            onDoubleClick: () => props.onActivate?.(day),
            children: number
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
            lineNumber: 344,
            columnNumber: 41
          },
          this
        ) }, c, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 343,
          columnNumber: 37
        }, this);
      }) }, r, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 332,
        columnNumber: 25
      }, this)) }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 330,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 326,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 314,
    columnNumber: 9
  }, this);
}
__name(Calendar, "Calendar");
function TimePicker(props) {
  const { hour, minute, step, hour12, lang, strings } = props;
  const minutes = [];
  for (let item = 0; item < 60; item += Math.max(1, step)) {
    minutes.push(item);
  }
  if (!minutes.includes(minute)) {
    minutes.push(minute);
    minutes.sort((a, b) => a - b);
  }
  const hourFormat = new Intl.DateTimeFormat(lang, { hour: "numeric", hour12: true });
  const hourLabel = /* @__PURE__ */ __name((item) => hour12 ? hourFormat.format(new Date(2026, 0, 5, item)) : String(item).padStart(2, "0"), "hourLabel");
  return /* @__PURE__ */ jsxDEV("div", { role: "group", "aria-label": props.label, className: "d-flex align-items-center gap-2 tool_mulib-date-time", children: [
    props.label && /* @__PURE__ */ jsxDEV("span", { className: "tool_mulib-date-timelabel", "aria-hidden": "true", children: props.label }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 402,
      columnNumber: 29
    }, this),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        className: "form-select w-auto",
        "aria-label": strings.hour,
        value: hour,
        onChange: (event) => props.onChange(Number(event.target.value), minute),
        children: Array.from({ length: 24 }, (_, i) => i).map((item) => /* @__PURE__ */ jsxDEV("option", { value: item, children: hourLabel(item) }, item, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 405,
          columnNumber: 70
        }, this))
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 403,
        columnNumber: 13
      },
      this
    ),
    /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: ":" }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 407,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        className: "form-select w-auto",
        "aria-label": strings.minute,
        value: minute,
        onChange: (event) => props.onChange(hour, Number(event.target.value)),
        children: minutes.map((item) => /* @__PURE__ */ jsxDEV("option", { value: item, children: String(item).padStart(2, "0") }, item, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 410,
          columnNumber: 40
        }, this))
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 408,
        columnNumber: 13
      },
      this
    ),
    props.children
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 401,
    columnNumber: 9
  }, this);
}
__name(TimePicker, "TimePicker");
function Footer({ strings, onToday, onClear, onApply, onClose }) {
  return /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap align-items-center gap-2 p-3 border-top", children: [
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-light", onClick: onToday, children: strings.today }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 440,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-light", onClick: onClear, children: strings.clear }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 441,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("span", { className: "ms-auto d-inline-flex gap-2", children: [
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-primary", "data-tool-mulib-date-apply": true, onClick: onApply, children: strings.apply }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 443,
        columnNumber: 17
      }, this),
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary", onClick: onClose, children: strings.close }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 446,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 442,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 439,
    columnNumber: 9
  }, this);
}
__name(Footer, "Footer");
function Header({ title, close, onClose }) {
  return /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center gap-2 p-3 border-bottom", children: [
    /* @__PURE__ */ jsxDEV("h2", { className: "h5 mb-0 flex-grow-1", children: title }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 464,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn-close", "aria-label": close, title: close, onClick: onClose }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 465,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 463,
    columnNumber: 9
  }, this);
}
__name(Header, "Header");
function RecentList({ entries, title, label, current, onSelect, onActivate }) {
  const headingId = useId();
  return /* @__PURE__ */ jsxDEV("section", { className: "tool_mulib-date-recent", "aria-labelledby": headingId, "data-tool-mulib-date-recent": true, children: [
    /* @__PURE__ */ jsxDEV("h3", { className: "h6 mb-2", id: headingId, children: title }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 489,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "list-group", children: entries.map((entry) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        "aria-pressed": current(entry),
        className: `list-group-item list-group-item-action${current(entry) ? " active" : ""}`,
        onClick: () => onSelect(entry),
        onDoubleClick: () => onActivate(entry),
        children: label(entry)
      },
      entry.text,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 492,
        columnNumber: 21
      },
      this
    )) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 490,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 488,
    columnNumber: 9
  }, this);
}
__name(RecentList, "RecentList");
function SinglePanel({ options, onApply, onClose }) {
  const lang = document.documentElement.lang || "en";
  const { strings, range } = options;
  const empty = { ...todayIn(options.timezone), hour: options.defaultHour ?? 0, minute: options.defaultMinute ?? 0, second: 0 };
  const [parts, setParts] = useState(options.start ?? empty);
  const [view, setView] = useState({ year: parts.year, month: parts.month });
  const [duration, setDuration] = useState(() => range ? rangeMinutes(options.end ?? null, parts, range) : 0);
  const durationId = useId();
  const firstDay = firstDayOfWeek(options.firstDay, lang);
  const recent = recentDates(!!range);
  const textOf = /* @__PURE__ */ __name((start, minutes = duration) => {
    if (!range) {
      return formatComponents(start);
    }
    return formatComponents(start) + " \u2013 " + formatComponents(fromWallMinutes(wallMinutes(start) + minutes, start.second));
  }, "textOf");
  const pickRecent = /* @__PURE__ */ __name((entry) => {
    const minutes = range && entry.end ? wallMinutes(entry.end) - wallMinutes(entry.start) : duration;
    setParts(entry.start);
    setView({ year: entry.start.year, month: entry.start.month });
    setDuration(minutes);
    return minutes;
  }, "pickRecent");
  const endParts = fromWallMinutes(wallMinutes(parts) + duration, parts.second);
  const setEndTime = /* @__PURE__ */ __name((hour, minute) => {
    let minutes = wallMinutes({ ...parts, hour, minute }) - wallMinutes(parts);
    if (minutes <= 0) {
      minutes += 24 * 60;
    }
    setDuration(minutes);
  }, "setEndTime");
  const durations = range ? range.durations.map((item) => ({ minutes: Math.round(item.seconds / 60), label: item.label })) : [];
  if (range && !durations.some((item) => item.minutes === duration)) {
    durations.push({ minutes: duration, label: `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, "0")}` });
    durations.sort((a, b) => a.minutes - b.minutes);
  }
  const offset = dayNumber(endParts) - dayNumber(parts);
  const recentLabel = /* @__PURE__ */ __name((entry) => {
    if (!range || !entry.end) {
      return entry.text;
    }
    const minutes = wallMinutes(entry.end) - wallMinutes(entry.start);
    const known = range.durations.find((item) => Math.round(item.seconds / 60) === minutes);
    return `${entry.text} \xB7 ${known ? known.label : `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`}`;
  }, "recentLabel");
  const time = /* @__PURE__ */ __name((label) => /* @__PURE__ */ jsxDEV(
    TimePicker,
    {
      hour: parts.hour,
      minute: parts.minute,
      step: options.step,
      hour12: !!options.hour12,
      lang,
      strings,
      label,
      onChange: (hour, minute) => setParts({ ...parts, hour, minute })
    },
    void 0,
    false,
    {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 558,
      columnNumber: 9
    },
    this
  ), "time");
  return /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-column h-100 tool_mulib-date-panel", "data-tool-mulib-date-panel": options.mode, children: [
    /* @__PURE__ */ jsxDEV(Header, { title: options.title || strings.pick, close: strings.close, onClose }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 564,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "flex-grow-1 overflow-auto p-3 d-flex flex-column align-items-center gap-3", children: [
      /* @__PURE__ */ jsxDEV(
        Calendar,
        {
          view,
          onView: setView,
          focus: parts,
          onFocus: (day) => setParts({ ...parts, ...day }),
          selection: (day) => dayNumber(day) === dayNumber(parts),
          onSelect: (day) => setParts({ ...parts, ...day }),
          onActivate: (day) => onApply({ text: textOf({ ...parts, ...day }) }),
          firstDay,
          today: todayIn(options.timezone),
          lang,
          strings
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 566,
          columnNumber: 17
        },
        this
      ),
      /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-column gap-2", children: [
        time(range?.strings.start),
        range && /* @__PURE__ */ jsxDEV("div", { "data-muform-datetimerange-end": true, children: /* @__PURE__ */ jsxDEV(
          TimePicker,
          {
            hour: endParts.hour,
            minute: endParts.minute,
            step: options.step,
            hour12: !!options.hour12,
            lang,
            strings,
            label: range.strings.end,
            onChange: setEndTime,
            children: offset > 0 && /* @__PURE__ */ jsxDEV("sup", { "data-muform-datetimerange-dayoffset": true, children: [
              "+",
              offset
            ] }, void 0, true, {
              fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
              lineNumber: 578,
              columnNumber: 48
            }, this)
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
            lineNumber: 575,
            columnNumber: 29
          },
          this
        ) }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 574,
          columnNumber: 25
        }, this),
        range && /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center gap-2", children: [
          /* @__PURE__ */ jsxDEV("label", { className: "tool_mulib-date-timelabel mb-0", htmlFor: durationId, children: range.strings.duration }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
            lineNumber: 584,
            columnNumber: 29
          }, this),
          /* @__PURE__ */ jsxDEV(
            "select",
            {
              className: "form-select w-auto",
              id: durationId,
              value: duration,
              "data-muform-datetimerange-duration": true,
              onChange: (event) => setDuration(Number(event.target.value)),
              children: durations.map((item) => /* @__PURE__ */ jsxDEV("option", { value: item.minutes, children: item.label }, item.minutes, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
                lineNumber: 587,
                columnNumber: 58
              }, this))
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
              lineNumber: 585,
              columnNumber: 29
            },
            this
          )
        ] }, void 0, true, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 583,
          columnNumber: 25
        }, this)
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 571,
        columnNumber: 17
      }, this),
      recent.length > 0 && /* @__PURE__ */ jsxDEV(
        RecentList,
        {
          entries: recent,
          title: strings.recent,
          label: recentLabel,
          current: (entry) => isRecent(entry, parts, range ? endParts : null),
          onSelect: pickRecent,
          onActivate: (entry) => onApply({ text: textOf(entry.start, pickRecent(entry)) })
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 593,
          columnNumber: 21
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
      lineNumber: 565,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV(
      Footer,
      {
        strings,
        onClose,
        onClear: () => onApply({ text: "" }),
        onToday: () => {
          const today = todayIn(options.timezone);
          setParts({ ...parts, ...today });
          setView({ year: today.year, month: today.month });
        },
        onApply: () => onApply({ text: textOf(parts) })
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 599,
        columnNumber: 13
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 563,
    columnNumber: 9
  }, this);
}
__name(SinglePanel, "SinglePanel");
function openDatePanel(options, trigger) {
  return new Promise((resolve) => {
    let result = null;
    let pending = null;
    const label = options.title || options.strings.pick;
    const Panel = SinglePanel;
    openPanel({
      label,
      mount: /* @__PURE__ */ __name((dialog, close) => mountReactApp(dialog, Panel, {
        options,
        onApply: /* @__PURE__ */ __name((applied) => {
          result = applied;
          pending = new Pending("tool_mulib/date:panel");
          close();
        }, "onApply"),
        onClose: close
      }, { id: "tool_mulib-date-panel" }), "mount"),
      onClosed: /* @__PURE__ */ __name(() => {
        trigger?.focus();
        resolve(result);
        if (pending) {
          const applied = pending;
          setTimeout(() => applied.resolve(), 0);
        }
      }, "onClosed")
    });
  });
}
__name(openDatePanel, "openDatePanel");
function DatePicker(props) {
  const { strings, disabled, small = false } = props;
  const button = useRef(null);
  if (disabled) {
    return null;
  }
  const open = /* @__PURE__ */ __name(async () => {
    const result = await openDatePanel({
      mode: props.range ? "range" : "single",
      title: props.title,
      timezone: props.timezone,
      step: props.step,
      firstDay: props.firstDay,
      hour12: props.hour12,
      strings,
      start: props.getValue(),
      end: props.getEnd?.() ?? null,
      defaultHour: props.defaultHour,
      defaultMinute: props.defaultMinute,
      range: props.range ?? null
    }, button.current);
    if (result) {
      await props.apply(result.text);
    }
  }, "open");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        ref: button,
        type: "button",
        className: `btn btn-outline-secondary${small ? " btn-sm" : ""}`,
        "aria-label": strings.pick,
        title: strings.pick,
        "aria-haspopup": "dialog",
        onClick: () => {
          void open();
        },
        children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-calendar", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 702,
          columnNumber: 17
        }, this)
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 698,
        columnNumber: 13
      },
      this
    ),
    props.clearable && /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: `btn btn-outline-secondary${small ? " btn-sm" : ""}`,
        "data-muform-datetime-clear": true,
        "aria-label": strings.clear,
        title: strings.clear,
        onClick: () => {
          void props.apply("");
        },
        children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
          lineNumber: 709,
          columnNumber: 21
        }, this)
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
        lineNumber: 705,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/date/panel.tsx",
    lineNumber: 697,
    columnNumber: 9
  }, this);
}
__name(DatePicker, "DatePicker");
export {
  DatePicker as default,
  getPickerStrings,
  moveDay,
  openDatePanel
};
//# sourceMappingURL=panel.dev.js.map
