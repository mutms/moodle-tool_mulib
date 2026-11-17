var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Filters of picker panels, pills that look and work like the report filter bar.
 *
 * A pill opens the editor of the filter value in a popover, its cross removes the value;
 * filters without a value are offered by the "Add filter" button, pinned filters stay in the bar instead.
 * Values of autocomplete filters are searched in their source through the tool_mulib autocomplete endpoint.
 * Any picker family can use it: the panel renders this component with the filters exported
 * by tool_mulib\muform\picker\filter_set and sends the changed values to its endpoint.
 *
 * @module     tool_mulib/muform/pickerfilters
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
import { useEffect, useRef, useState } from "react";
import { labelText } from "./pickerpanel";
const SEARCH_THRESHOLD = 10;
const TYPING_DELAY = 250;
function AutocompleteEditor({ filter, strings, onValue }) {
  const [query, setQuery] = useState("");
  const [answer, setAnswer] = useState(null);
  const request = useRef(0);
  const delay = useRef(0);
  useEffect(() => {
    setAnswer(null);
    const id = ++request.current;
    const timer = setTimeout(async () => {
      const body = { source: filter.source?.class ?? "", args: filter.source?.args ?? [], query, exclude: [] };
      let data = { list: [], overflow: false };
      try {
        const response = await Fetch.performPost("tool_mulib", "muform/autocomplete", { body });
        data = await response.json();
      } catch {
      }
      if (id === request.current) {
        setAnswer(data);
      }
    }, delay.current);
    delay.current = 0;
    return () => clearTimeout(timer);
  }, [query, filter.source]);
  const list = answer?.list ?? [];
  let message = "";
  if (!answer) {
    message = strings.filtersearching;
  } else if (answer.overflow || answer.list === null) {
    message = strings.filtertoomanyresults;
  } else if (!list.length) {
    message = strings.filternoresults;
  }
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      "input",
      {
        type: "search",
        className: "form-control form-control-sm mb-2",
        placeholder: strings.search,
        "aria-label": strings.search,
        value: query,
        "data-picker-filter-search": true,
        onChange: (event) => {
          delay.current = TYPING_DELAY;
          setQuery(event.target.value);
        },
        onKeyDown: (event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            if (list.length === 1) {
              onValue(list[0].value, list[0].label);
            }
          }
        }
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
        lineNumber: 156,
        columnNumber: 13
      },
      this
    ),
    /* @__PURE__ */ jsxDEV("div", { className: "list-group list-group-flush", "aria-busy": !answer, children: list.map((option) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        "data-picker-filter-option": option.value,
        className: "list-group-item list-group-item-action border-0" + (option.value === filter.value ? " active" : ""),
        onClick: () => onValue(option.value, option.label),
        children: labelText(option.label)
      },
      option.value,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
        lineNumber: 173,
        columnNumber: 21
      },
      this
    )) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 171,
      columnNumber: 13
    }, this),
    message !== "" && /* @__PURE__ */ jsxDEV("div", { className: "text-muted small", "data-picker-filter-message": true, children: message }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 181,
      columnNumber: 32
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
    lineNumber: 155,
    columnNumber: 9
  }, this);
}
__name(AutocompleteEditor, "AutocompleteEditor");
function Editor({ filter, strings, onValue }) {
  const [query, setQuery] = useState("");
  const search = query.trim().toLowerCase();
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "fw-bold mb-2", children: filter.label }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 200,
      columnNumber: 13
    }, this),
    filter.type === "autocomplete" && /* @__PURE__ */ jsxDEV(AutocompleteEditor, { filter, strings, onValue }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 201,
      columnNumber: 48
    }, this),
    filter.type === "boolean" && /* @__PURE__ */ jsxDEV("div", { className: "d-flex gap-2", children: filter.options.map((option) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        "data-picker-filter-option": option.value,
        className: "btn btn-sm " + (option.value === filter.value ? "btn-primary" : "btn-outline-primary"),
        onClick: () => onValue(option.value),
        children: option.label
      },
      option.value,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
        lineNumber: 205,
        columnNumber: 25
      },
      this
    )) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 203,
      columnNumber: 17
    }, this),
    filter.type === "select" && /* @__PURE__ */ jsxDEV(Fragment, { children: [
      filter.options.length > SEARCH_THRESHOLD && /* @__PURE__ */ jsxDEV(
        "input",
        {
          type: "search",
          className: "form-control form-control-sm mb-2",
          placeholder: strings.search,
          "aria-label": strings.search,
          value: query,
          onChange: (event) => setQuery(event.target.value)
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
          lineNumber: 216,
          columnNumber: 25
        },
        this
      ),
      /* @__PURE__ */ jsxDEV("div", { className: "list-group list-group-flush", children: filter.options.filter((option) => option.label.toLowerCase().includes(search)).map((option) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          "data-picker-filter-option": option.value,
          className: "list-group-item list-group-item-action border-0" + (option.value === filter.value ? " active" : ""),
          onClick: () => onValue(option.value),
          children: option.label
        },
        option.value,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
          lineNumber: 221,
          columnNumber: 29
        },
        this
      )) }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
        lineNumber: 219,
        columnNumber: 21
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 214,
      columnNumber: 17
    }, this),
    filter.default !== null && filter.value !== filter.default && /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "btn btn-sm btn-link px-0 mt-2 align-self-start",
        onClick: () => onValue(filter.default),
        children: strings.filterresetdefault
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
        lineNumber: 232,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
    lineNumber: 199,
    columnNumber: 9
  }, this);
}
__name(Editor, "Editor");
function Filters({ filters, strings, onChange, onCancel }) {
  const [popover, setPopover] = useState(null);
  const [picked, setPicked] = useState({});
  const rootRef = useRef(null);
  useEffect(() => {
    if (!popover) {
      return void 0;
    }
    rootRef.current?.querySelector("[data-picker-filter-popover] :is(input, button)")?.focus();
    const close = /* @__PURE__ */ __name((event) => {
      if (event.target instanceof Node && !rootRef.current?.contains(event.target)) {
        setPopover(null);
      }
    }, "close");
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [popover]);
  if (!filters.length) {
    return null;
  }
  const inactive = filters.filter((filter) => filter.value === null && !filter.pinned);
  const edited = popover?.key ? filters.find((filter) => filter.key === popover.key) : void 0;
  const valueText = /* @__PURE__ */ __name((filter) => {
    if (filter.type === "autocomplete") {
      const known = picked[filter.key]?.value === filter.value ? picked[filter.key].label : filter.valuelabel;
      return known ? labelText(known) : filter.value ?? "";
    }
    return filter.options.find((option) => option.value === filter.value)?.label ?? filter.value ?? "";
  }, "valueText");
  const change = /* @__PURE__ */ __name((changes) => {
    setPopover(null);
    onChange(changes);
  }, "change");
  const toggle = /* @__PURE__ */ __name((key, anchor) => {
    if (popover && popover.key === key) {
      setPopover(null);
      return;
    }
    setPopover({ key, left: anchor.offsetLeft, top: anchor.offsetTop + anchor.offsetHeight + 4 });
  }, "toggle");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape" && popover) {
      event.preventDefault();
      event.stopPropagation();
      setPopover(null);
      onCancel();
    }
  }, "onKeyDown");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: rootRef,
      className: "d-flex flex-wrap align-items-center gap-2 mt-2 position-relative",
      "data-picker-filters": true,
      onKeyDown,
      children: [
        filters.filter((filter) => filter.value !== null || filter.pinned).map((filter) => /* @__PURE__ */ jsxDEV("div", { className: "btn-group btn-group-sm", "data-picker-filter": filter.key, children: [
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: filter.value === null ? "btn btn-outline-secondary" : "btn btn-primary",
              onClick: (event) => toggle(filter.key, event.currentTarget.parentElement ?? event.currentTarget),
              children: [
                filter.label,
                ": ",
                filter.value === null ? filter.anylabel ?? "" : valueText(filter)
              ]
            },
            void 0,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
              lineNumber: 315,
              columnNumber: 21
            },
            this
          ),
          filter.value !== null && /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-primary",
              title: strings.filterremove,
              "aria-label": `${strings.filterremove}: ${filter.label}`,
              "data-picker-filter-remove": true,
              onClick: () => change({ [filter.key]: null }),
              children: "\xD7"
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
              lineNumber: 320,
              columnNumber: 25
            },
            this
          )
        ] }, filter.key, true, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
          lineNumber: 314,
          columnNumber: 17
        }, this)),
        inactive.length > 0 && /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            "data-picker-filter-add": true,
            onClick: (event) => toggle(null, event.currentTarget),
            children: [
              "+ ",
              strings.filteradd
            ]
          },
          void 0,
          true,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
            lineNumber: 327,
            columnNumber: 17
          },
          this
        ),
        filters.some((filter) => filter.value !== filter.default) && /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-link",
            "data-picker-filter-reset": true,
            onClick: () => change(Object.fromEntries(filters.map((filter) => [filter.key, filter.default]))),
            children: [
              "\u21BA ",
              strings.filterresetall
            ]
          },
          void 0,
          true,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
            lineNumber: 333,
            columnNumber: 17
          },
          this
        ),
        popover && /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "card shadow p-2 position-absolute overflow-auto",
            "data-picker-filter-popover": true,
            style: { left: popover.left, top: popover.top, zIndex: 1050, minWidth: "16rem", maxHeight: "20rem" },
            children: edited ? /* @__PURE__ */ jsxDEV(
              Editor,
              {
                filter: edited,
                strings,
                onValue: (value, label) => {
                  if (value !== null && label !== void 0) {
                    setPicked({ ...picked, [edited.key]: { value, label } });
                  }
                  change({ [edited.key]: value });
                }
              },
              edited.key,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
                lineNumber: 342,
                columnNumber: 25
              },
              this
            ) : /* @__PURE__ */ jsxDEV("div", { className: "list-group list-group-flush", children: inactive.map((filter) => /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "list-group-item list-group-item-action border-0",
                "data-picker-filter-choice": filter.key,
                onClick: () => setPopover({ ...popover, key: filter.key }),
                children: filter.label
              },
              filter.key,
              false,
              {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
                lineNumber: 352,
                columnNumber: 33
              },
              this
            )) }, void 0, false, {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
              lineNumber: 350,
              columnNumber: 25
            }, this)
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
            lineNumber: 339,
            columnNumber: 17
          },
          this
        )
      ]
    },
    void 0,
    true,
    {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/pickerfilters.tsx",
      lineNumber: 311,
      columnNumber: 9
    },
    this
  );
}
__name(Filters, "Filters");
export {
  Filters as default
};
//# sourceMappingURL=pickerfilters.dev.js.map
