var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Calendar and time picker of the datetime and datetimerange elements, a React island.
 *
 * The picker only edits date parts, the text sent to the element is parsed
 * by the server. In range mode it also edits the end time or duration, changing
 * the start keeps the duration. Month and weekday names come from Intl for display only,
 * the first day of week comes from Moodle. "Today" is in the timezone of the element,
 * empty values start at the default time of the element.
 *
 * @module     tool_mulib/muform/datetimepicker
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
  FloatingFocusManager,
  autoUpdate,
  flip,
  offset,
  shift,
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
  useRole
} from "@floating-ui/react";
import { useEffect, useRef, useState } from "react";
import { formatComponents, readComponents } from "./datetimeapi";
import { readEndComponents } from "./datetimerangeapi";
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
function todayIn(timezone) {
  const now = /* @__PURE__ */ new Date();
  try {
    const parts = new Intl.DateTimeFormat("en-GB", {
      timeZone: timezone,
      year: "numeric",
      month: "numeric",
      day: "numeric"
    }).formatToParts(now);
    const part = /* @__PURE__ */ __name((type) => Number(parts.find((item) => item.type === type)?.value), "part");
    return { year: part("year"), month: part("month"), day: part("day") };
  } catch {
    return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
  }
}
__name(todayIn, "todayIn");
function emptyParts(input) {
  return {
    ...todayIn(input.dataset.muformDatetimeTimezone ?? ""),
    hour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
    minute: Number(input.dataset.muformDatetimeDefaultminute) || 0,
    second: 0
  };
}
__name(emptyParts, "emptyParts");
function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}
__name(daysInMonth, "daysInMonth");
function addDays(parts, days) {
  const date = new Date(parts.year, parts.month - 1, parts.day + days);
  return { ...parts, year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
}
__name(addDays, "addDays");
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
function dayOffset(start, end) {
  const day = /* @__PURE__ */ __name((parts) => Date.UTC(parts.year, parts.month - 1, parts.day) / 864e5, "day");
  return Math.round(day(end) - day(start));
}
__name(dayOffset, "dayOffset");
function rangeMinutes(input, start, range) {
  const end = readEndComponents(input);
  if (end) {
    const minutes = wallMinutes(end) - wallMinutes(start);
    if (minutes > 0 && minutes <= 24 * 60) {
      return minutes;
    }
  }
  return Math.max(1, Math.round((range.defaultDuration ?? 3600) / 60));
}
__name(rangeMinutes, "rangeMinutes");
function DateTimePicker({ input, step, firstDay: moodleFirstDay, hour12 = false, disabled, strings, apply, range = null }) {
  const lang = document.documentElement.lang || "en";
  const timezone = input.dataset.muformDatetimeTimezone ?? "";
  const [open, setOpen] = useState(false);
  const [parts, setParts] = useState(() => readComponents(input) ?? emptyParts(input));
  const [view, setView] = useState({ year: parts.year, month: parts.month });
  const [duration, setDuration] = useState(() => range ? rangeMinutes(input, parts, range) : 0);
  const [focusDay, setFocusDay] = useState(false);
  const gridRef = useRef(null);
  const onOpenChange = /* @__PURE__ */ __name((state) => {
    if (state) {
      const current = readComponents(input) ?? emptyParts(input);
      setParts(current);
      setView({ year: current.year, month: current.month });
      if (range) {
        setDuration(rangeMinutes(input, current, range));
      }
    }
    setOpen(state);
  }, "onOpenChange");
  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange,
    placement: "bottom-end",
    // Fixed positioning escapes scrolling ancestors such as dialog bodies, which would clip the panel.
    strategy: "fixed",
    middleware: [offset(4), flip(), shift({ padding: 8 })],
    whileElementsMounted: autoUpdate
  });
  const { getReferenceProps, getFloatingProps } = useInteractions([
    useClick(context),
    useDismiss(context),
    useRole(context, { role: "dialog" })
  ]);
  useEffect(() => {
    if (focusDay && gridRef.current) {
      gridRef.current.querySelector('button[tabindex="0"]')?.focus();
      setFocusDay(false);
    }
  }, [focusDay, parts]);
  if (disabled) {
    return null;
  }
  const select = /* @__PURE__ */ __name((next) => {
    setParts(next);
    setView({ year: next.year, month: next.month });
  }, "select");
  const moveMonth = /* @__PURE__ */ __name((delta) => {
    const date = new Date(view.year, view.month - 1 + delta, 1);
    setView({ year: date.getFullYear(), month: date.getMonth() + 1 });
  }, "moveMonth");
  const onGridKeyDown = /* @__PURE__ */ __name((event) => {
    const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
    const delta = moves[event.key];
    if (delta === void 0) {
      return;
    }
    event.preventDefault();
    select(addDays(parts, delta));
    setFocusDay(true);
  }, "onGridKeyDown");
  const onPanelKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      setOpen(false);
    }
  }, "onPanelKeyDown");
  const applyAndClose = /* @__PURE__ */ __name((text) => {
    setOpen(false);
    void apply(text);
  }, "applyAndClose");
  const monthFormat = new Intl.DateTimeFormat(lang, { month: "long", year: "numeric" });
  const monthName = monthFormat.format(new Date(view.year, view.month - 1, 1));
  const weekdayFormat = new Intl.DateTimeFormat(lang, { weekday: "short" });
  const firstDay = firstDayOfWeek(moodleFirstDay, lang);
  const weekdays = Array.from({ length: 7 }, (_, i) => {
    const weekday = (firstDay - 1 + i) % 7 + 1;
    return weekdayFormat.format(new Date(2026, 0, 4 + weekday));
  });
  const today = todayIn(timezone);
  const first = new Date(view.year, view.month - 1, 1);
  const firstWeekday = first.getDay() === 0 ? 7 : first.getDay();
  const leading = (firstWeekday - firstDay + 7) % 7;
  const total = daysInMonth(view.year, view.month);
  const cells = [...Array(leading).fill(null)];
  for (let day = 1; day <= total; day++) {
    cells.push(day);
  }
  while (cells.length % 7 !== 0) {
    cells.push(null);
  }
  const rows = [];
  for (let i = 0; i < cells.length; i += 7) {
    rows.push(cells.slice(i, i + 7));
  }
  const selectedInView = parts.year === view.year && parts.month === view.month;
  const focusable = selectedInView ? parts.day : 1;
  const minuteOptions = /* @__PURE__ */ __name((current) => {
    const minutes2 = [];
    for (let minute = 0; minute < 60; minute += step) {
      minutes2.push(minute);
    }
    if (!minutes2.includes(current)) {
      minutes2.push(current);
      minutes2.sort((a, b) => a - b);
    }
    return minutes2;
  }, "minuteOptions");
  const minutes = minuteOptions(parts.minute);
  const hours = Array.from({ length: 24 }, (_, i) => i);
  const hourFormat = new Intl.DateTimeFormat(lang, { hour: "numeric", hour12: true });
  const hourLabel = /* @__PURE__ */ __name((hour) => hour12 ? hourFormat.format(new Date(2026, 0, 5, hour)) : String(hour).padStart(2, "0"), "hourLabel");
  const endParts = fromWallMinutes(wallMinutes(parts) + duration, parts.second);
  const selectedText = /* @__PURE__ */ __name((start) => {
    if (!range) {
      return formatComponents(start);
    }
    const end = fromWallMinutes(wallMinutes(start) + duration, start.second);
    return formatComponents(start) + " \u2013 " + formatComponents(end);
  }, "selectedText");
  const setEndTime = /* @__PURE__ */ __name((hour, minute) => {
    let minutes2 = wallMinutes({ ...parts, hour, minute }) - wallMinutes(parts);
    if (minutes2 <= 0) {
      minutes2 += 24 * 60;
    }
    setDuration(minutes2);
  }, "setEndTime");
  const durations = range ? range.durations.map((item) => ({ minutes: Math.round(item.seconds / 60), label: item.label })) : [];
  if (range && !durations.some((item) => item.minutes === duration)) {
    const label = `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, "0")}`;
    durations.push({ minutes: duration, label });
    durations.sort((a, b) => a.minutes - b.minutes);
  }
  const endOffset = dayOffset(parts, endParts);
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "btn btn-outline-secondary",
        ref: refs.setReference,
        "aria-label": strings.pick,
        title: strings.pick,
        ...getReferenceProps(),
        children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-calendar", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
          lineNumber: 400,
          columnNumber: 17
        }, this)
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
        lineNumber: 392,
        columnNumber: 13
      },
      this
    ),
    open && /* @__PURE__ */ jsxDEV(FloatingFocusManager, { context, modal: false, children: /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: refs.setFloating,
        style: floatingStyles,
        className: "muform-datetime-panel card shadow p-2",
        "aria-label": strings.pick,
        ...getFloatingProps({ onKeyDown: onPanelKeyDown }),
        children: [
          /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center justify-content-between mb-2", children: [
            /* @__PURE__ */ jsxDEV("span", { className: "d-flex gap-1", children: [
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "btn btn-sm btn-light",
                  "aria-label": strings.yearprev,
                  onClick: () => moveMonth(-12),
                  children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-angles-left", "aria-hidden": "true" }, void 0, false, {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 415,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 413,
                  columnNumber: 33
                },
                this
              ),
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "btn btn-sm btn-light",
                  "aria-label": strings.monthprev,
                  onClick: () => moveMonth(-1),
                  children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-left", "aria-hidden": "true" }, void 0, false, {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 419,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 417,
                  columnNumber: 33
                },
                this
              )
            ] }, void 0, true, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 412,
              columnNumber: 29
            }, this),
            /* @__PURE__ */ jsxDEV("span", { className: "fw-bold", "aria-live": "polite", children: monthName }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 422,
              columnNumber: 29
            }, this),
            /* @__PURE__ */ jsxDEV("span", { className: "d-flex gap-1", children: [
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "btn btn-sm btn-light",
                  "aria-label": strings.monthnext,
                  onClick: () => moveMonth(1),
                  children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-right", "aria-hidden": "true" }, void 0, false, {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 426,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 424,
                  columnNumber: 33
                },
                this
              ),
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "btn btn-sm btn-light",
                  "aria-label": strings.yearnext,
                  onClick: () => moveMonth(12),
                  children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-angles-right", "aria-hidden": "true" }, void 0, false, {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 430,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 428,
                  columnNumber: 33
                },
                this
              )
            ] }, void 0, true, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 423,
              columnNumber: 29
            }, this)
          ] }, void 0, true, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
            lineNumber: 411,
            columnNumber: 25
          }, this),
          /* @__PURE__ */ jsxDEV(
            "table",
            {
              className: "table table-sm table-borderless text-center mb-2",
              ref: gridRef,
              role: "grid",
              onKeyDown: onGridKeyDown,
              children: [
                /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: weekdays.map((name) => /* @__PURE__ */ jsxDEV("th", { scope: "col", className: "small fw-normal", children: name }, name, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 438,
                  columnNumber: 61
                }, this)) }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 437,
                  columnNumber: 33
                }, this) }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 436,
                  columnNumber: 29
                }, this),
                /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((row, r) => /* @__PURE__ */ jsxDEV("tr", { children: row.map((day, c) => {
                  if (day === null) {
                    return /* @__PURE__ */ jsxDEV("td", {}, c, false, {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 446,
                      columnNumber: 56
                    }, this);
                  }
                  const isSelected = selectedInView && day === parts.day;
                  const isToday = today.year === view.year && today.month === view.month && today.day === day;
                  const classes = ["btn", "btn-sm", "muform-datetime-day"];
                  classes.push(isSelected ? "btn-primary" : "btn-light");
                  if (isToday) {
                    classes.push("fw-bold");
                  }
                  return /* @__PURE__ */ jsxDEV("td", { children: /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: classes.join(" "),
                      tabIndex: day === focusable ? 0 : -1,
                      "aria-pressed": isSelected,
                      "aria-current": isToday ? "date" : void 0,
                      "data-muform-datetime-day": day,
                      onClick: () => select({ ...parts, year: view.year, month: view.month, day }),
                      onDoubleClick: () => applyAndClose(
                        selectedText({ ...parts, year: view.year, month: view.month, day })
                      ),
                      children: day
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 458,
                      columnNumber: 53
                    },
                    this
                  ) }, c, false, {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 457,
                    columnNumber: 49
                  }, this);
                }) }, r, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 443,
                  columnNumber: 37
                }, this)) }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 441,
                  columnNumber: 29
                }, this)
              ]
            },
            void 0,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 434,
              columnNumber: 25
            },
            this
          ),
          /* @__PURE__ */ jsxDEV(
            "div",
            {
              className: "d-flex align-items-center gap-1 mb-2",
              role: range ? "group" : void 0,
              "aria-label": range ? range.strings.start : void 0,
              children: [
                range && /* @__PURE__ */ jsxDEV("span", { className: "muform-datetime-rangelabel", "aria-hidden": "true", children: range.strings.start }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 481,
                  columnNumber: 39
                }, this),
                /* @__PURE__ */ jsxDEV(
                  "select",
                  {
                    className: "form-select form-select-sm w-auto",
                    "aria-label": strings.hour,
                    value: parts.hour,
                    onChange: (event) => setParts({ ...parts, hour: Number(event.target.value) }),
                    children: hours.map((hour) => /* @__PURE__ */ jsxDEV("option", { value: hour, children: hourLabel(hour) }, hour, false, {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 484,
                      columnNumber: 54
                    }, this))
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 482,
                    columnNumber: 29
                  },
                  this
                ),
                /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: ":" }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 486,
                  columnNumber: 29
                }, this),
                /* @__PURE__ */ jsxDEV(
                  "select",
                  {
                    className: "form-select form-select-sm w-auto",
                    "aria-label": strings.minute,
                    value: parts.minute,
                    onChange: (event) => setParts({ ...parts, minute: Number(event.target.value) }),
                    children: minutes.map((minute) => /* @__PURE__ */ jsxDEV("option", { value: minute, children: String(minute).padStart(2, "0") }, minute, false, {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 490,
                      columnNumber: 37
                    }, this))
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 487,
                    columnNumber: 29
                  },
                  this
                )
              ]
            },
            void 0,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 479,
              columnNumber: 25
            },
            this
          ),
          range && /* @__PURE__ */ jsxDEV(
            "div",
            {
              className: "d-flex align-items-center gap-1 mb-2",
              role: "group",
              "aria-label": range.strings.end,
              "data-muform-datetimerange-end": true,
              children: [
                /* @__PURE__ */ jsxDEV("span", { className: "muform-datetime-rangelabel", "aria-hidden": "true", children: range.strings.end }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 497,
                  columnNumber: 33
                }, this),
                /* @__PURE__ */ jsxDEV(
                  "select",
                  {
                    className: "form-select form-select-sm w-auto",
                    "aria-label": strings.hour,
                    value: endParts.hour,
                    onChange: (event) => setEndTime(Number(event.target.value), endParts.minute),
                    children: hours.map((hour) => /* @__PURE__ */ jsxDEV("option", { value: hour, children: hourLabel(hour) }, hour, false, {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 501,
                      columnNumber: 58
                    }, this))
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 498,
                    columnNumber: 33
                  },
                  this
                ),
                /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: ":" }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 503,
                  columnNumber: 33
                }, this),
                /* @__PURE__ */ jsxDEV(
                  "select",
                  {
                    className: "form-select form-select-sm w-auto",
                    "aria-label": strings.minute,
                    value: endParts.minute,
                    onChange: (event) => setEndTime(endParts.hour, Number(event.target.value)),
                    children: minuteOptions(endParts.minute).map((minute) => /* @__PURE__ */ jsxDEV("option", { value: minute, children: String(minute).padStart(2, "0") }, minute, false, {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 508,
                      columnNumber: 41
                    }, this))
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 504,
                    columnNumber: 33
                  },
                  this
                ),
                endOffset > 0 && /* @__PURE__ */ jsxDEV("sup", { "data-muform-datetimerange-dayoffset": true, children: [
                  "+",
                  endOffset
                ] }, void 0, true, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 511,
                  columnNumber: 51
                }, this)
              ]
            },
            void 0,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 495,
              columnNumber: 29
            },
            this
          ),
          range && /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center gap-1 mb-2", children: /* @__PURE__ */ jsxDEV(
            "select",
            {
              className: "form-select form-select-sm w-auto",
              "aria-label": range.strings.duration,
              title: range.strings.duration,
              value: duration,
              "data-muform-datetimerange-duration": true,
              onChange: (event) => setDuration(Number(event.target.value)),
              children: durations.map((item) => /* @__PURE__ */ jsxDEV("option", { value: item.minutes, children: item.label }, item.minutes, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                lineNumber: 521,
                columnNumber: 41
              }, this))
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 516,
              columnNumber: 33
            },
            this
          ) }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
            lineNumber: 515,
            columnNumber: 29
          }, this),
          /* @__PURE__ */ jsxDEV("div", { className: "d-flex gap-1", children: [
            /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "btn btn-sm btn-light",
                onClick: () => select({ ...parts, ...todayIn(timezone) }),
                children: strings.today
              },
              void 0,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                lineNumber: 527,
                columnNumber: 29
              },
              this
            ),
            /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-light", onClick: () => applyAndClose(""), children: strings.clear }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 531,
              columnNumber: 29
            }, this),
            /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "btn btn-sm btn-primary ms-auto",
                onClick: () => applyAndClose(selectedText(parts)),
                children: strings.apply
              },
              void 0,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                lineNumber: 534,
                columnNumber: 29
              },
              this
            )
          ] }, void 0, true, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
            lineNumber: 526,
            columnNumber: 25
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
        lineNumber: 404,
        columnNumber: 21
      },
      this
    ) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
      lineNumber: 403,
      columnNumber: 17
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
    lineNumber: 391,
    columnNumber: 9
  }, this);
}
__name(DateTimePicker, "DateTimePicker");
export {
  DateTimePicker as default,
  todayIn
};
//# sourceMappingURL=datetimepicker.dev.js.map
