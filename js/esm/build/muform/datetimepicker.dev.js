var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Calendar and time picker of the datetime element, a React island.
 *
 * The picker only edits date parts, the text sent to the element is parsed
 * by the server. Month and weekday names come from Intl for display only.
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
function firstDayOfWeek(lang) {
  try {
    const locale = new Intl.Locale(lang);
    const info = locale.getWeekInfo ? locale.getWeekInfo() : locale.weekInfo;
    return info?.firstDay ?? 1;
  } catch {
    return 1;
  }
}
__name(firstDayOfWeek, "firstDayOfWeek");
function nowParts(step) {
  const now = /* @__PURE__ */ new Date();
  return {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: now.getHours(),
    minute: now.getMinutes() - now.getMinutes() % step,
    second: 0
  };
}
__name(nowParts, "nowParts");
function nowDate() {
  const now = /* @__PURE__ */ new Date();
  return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
}
__name(nowDate, "nowDate");
function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}
__name(daysInMonth, "daysInMonth");
function addDays(parts, days) {
  const date = new Date(parts.year, parts.month - 1, parts.day + days);
  return { ...parts, year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
}
__name(addDays, "addDays");
function DateTimePicker({ input, step, disabled, strings, apply }) {
  const lang = document.documentElement.lang || "en";
  const [open, setOpen] = useState(false);
  const [parts, setParts] = useState(() => readComponents(input) ?? nowParts(step));
  const [view, setView] = useState({ year: parts.year, month: parts.month });
  const [focusDay, setFocusDay] = useState(false);
  const gridRef = useRef(null);
  const onOpenChange = /* @__PURE__ */ __name((state) => {
    if (state) {
      const current = readComponents(input) ?? nowParts(step);
      setParts(current);
      setView({ year: current.year, month: current.month });
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
  const firstDay = firstDayOfWeek(lang);
  const weekdays = Array.from({ length: 7 }, (_, i) => {
    const weekday = (firstDay - 1 + i) % 7 + 1;
    return weekdayFormat.format(new Date(2026, 0, 4 + weekday));
  });
  const today = nowDate();
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
  const minutes = [];
  for (let minute = 0; minute < 60; minute += step) {
    minutes.push(minute);
  }
  if (!minutes.includes(parts.minute)) {
    minutes.push(parts.minute);
    minutes.sort((a, b) => a - b);
  }
  const hours = Array.from({ length: 24 }, (_, i) => i);
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
          lineNumber: 267,
          columnNumber: 17
        }, this)
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
        lineNumber: 259,
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
                    lineNumber: 282,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 280,
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
                    lineNumber: 286,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 284,
                  columnNumber: 33
                },
                this
              )
            ] }, void 0, true, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 279,
              columnNumber: 29
            }, this),
            /* @__PURE__ */ jsxDEV("span", { className: "fw-bold", "aria-live": "polite", children: monthName }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 289,
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
                    lineNumber: 293,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 291,
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
                    lineNumber: 297,
                    columnNumber: 37
                  }, this)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 295,
                  columnNumber: 33
                },
                this
              )
            ] }, void 0, true, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 290,
              columnNumber: 29
            }, this)
          ] }, void 0, true, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
            lineNumber: 278,
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
                  lineNumber: 305,
                  columnNumber: 61
                }, this)) }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 304,
                  columnNumber: 33
                }, this) }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 303,
                  columnNumber: 29
                }, this),
                /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((row, r) => /* @__PURE__ */ jsxDEV("tr", { children: row.map((day, c) => {
                  if (day === null) {
                    return /* @__PURE__ */ jsxDEV("td", {}, c, false, {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 313,
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
                        formatComponents({ ...parts, year: view.year, month: view.month, day })
                      ),
                      children: day
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                      lineNumber: 325,
                      columnNumber: 53
                    },
                    this
                  ) }, c, false, {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                    lineNumber: 324,
                    columnNumber: 49
                  }, this);
                }) }, r, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 310,
                  columnNumber: 37
                }, this)) }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 308,
                  columnNumber: 29
                }, this)
              ]
            },
            void 0,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 301,
              columnNumber: 25
            },
            this
          ),
          /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center gap-1 mb-2", children: [
            /* @__PURE__ */ jsxDEV(
              "select",
              {
                className: "form-select form-select-sm w-auto",
                "aria-label": strings.hour,
                value: parts.hour,
                onChange: (event) => setParts({ ...parts, hour: Number(event.target.value) }),
                children: hours.map((hour) => /* @__PURE__ */ jsxDEV("option", { value: hour, children: String(hour).padStart(2, "0") }, hour, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                  lineNumber: 349,
                  columnNumber: 54
                }, this))
              },
              void 0,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                lineNumber: 347,
                columnNumber: 29
              },
              this
            ),
            /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: ":" }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 351,
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
                  lineNumber: 355,
                  columnNumber: 37
                }, this))
              },
              void 0,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                lineNumber: 352,
                columnNumber: 29
              },
              this
            )
          ] }, void 0, true, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
            lineNumber: 346,
            columnNumber: 25
          }, this),
          /* @__PURE__ */ jsxDEV("div", { className: "d-flex gap-1", children: [
            /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-light", onClick: () => select({ ...parts, ...nowDate() }), children: strings.today }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 360,
              columnNumber: 29
            }, this),
            /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-light", onClick: () => applyAndClose(""), children: strings.clear }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
              lineNumber: 363,
              columnNumber: 29
            }, this),
            /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "btn btn-sm btn-primary ms-auto",
                onClick: () => applyAndClose(formatComponents(parts)),
                children: strings.apply
              },
              void 0,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
                lineNumber: 366,
                columnNumber: 29
              },
              this
            )
          ] }, void 0, true, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
            lineNumber: 359,
            columnNumber: 25
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
        lineNumber: 271,
        columnNumber: 21
      },
      this
    ) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
      lineNumber: 270,
      columnNumber: 17
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimepicker.tsx",
    lineNumber: 258,
    columnNumber: 9
  }, this);
}
__name(DateTimePicker, "DateTimePicker");
export {
  DateTimePicker as default
};
//# sourceMappingURL=datetimepicker.dev.js.map
