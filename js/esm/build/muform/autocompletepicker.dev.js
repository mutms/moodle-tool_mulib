var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Single value picker of the autocomplete element, a React island.
 *
 * The selected label is shown with its html in a form-control styled box,
 * activating it brings back the combobox input; typing searches the
 * tool_mulib autocomplete endpoint. Written independently of the token field on purpose,
 * this picker is expected to get its own better UI later.
 *
 * @module     tool_mulib/muform/autocompletepicker
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
  autoUpdate,
  flip,
  offset,
  size,
  useDismiss,
  useFloating,
  useInteractions,
  useListNavigation,
  useRole
} from "@floating-ui/react";
import Fetch from "@moodle/lms/core/fetch";
import { useEffect, useRef, useState } from "react";
function labelText(html) {
  const div = document.createElement("div");
  div.innerHTML = html;
  return (div.textContent ?? "").replace(/\s+/g, " ").trim();
}
__name(labelText, "labelText");
function FieldButton({ open, chosen, disabled, strings, onClose, onClear }) {
  if (disabled || !open && !chosen) {
    return null;
  }
  const label = open ? strings.close : strings.clearselection;
  return /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "btn btn-outline-secondary",
      "aria-label": label,
      title: label,
      "data-muform-autocomplete-close": open ? "" : void 0,
      onMouseDown: (event) => event.preventDefault(),
      onClick: open ? onClose : onClear,
      children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-times", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
        lineNumber: 121,
        columnNumber: 13
      }, this)
    },
    void 0,
    false,
    {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
      lineNumber: 116,
      columnNumber: 9
    },
    this
  );
}
__name(FieldButton, "FieldButton");
function Picker(props) {
  const { url, source, initial, name, id, placeholder, widthclass, disabled, strings, onChange } = props;
  const [selected, setSelected] = useState(initial);
  const [query, setQuery] = useState("");
  const [editing, setEditing] = useState(false);
  const [open, setOpen] = useState(false);
  const [results, setResults] = useState({ kind: "idle" });
  const [active, setActive] = useState(null);
  const listRef = useRef([]);
  const inputRef = useRef(null);
  const quietFocus = useRef(false);
  const focusInput = /* @__PURE__ */ __name(() => {
    quietFocus.current = true;
    inputRef.current?.focus();
    quietFocus.current = false;
  }, "focusInput");
  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange: /* @__PURE__ */ __name((state) => {
      setOpen(state);
      if (!state) {
        setEditing(false);
        setQuery("");
      }
    }, "onOpenChange"),
    placement: "bottom-start",
    // Fixed positioning escapes scrolling ancestors such as dialog bodies, which would clip the list.
    strategy: "fixed",
    middleware: [offset(4), flip(), size({
      apply({ rects, elements, availableHeight }) {
        elements.floating.style.minWidth = `${rects.reference.width}px`;
        elements.floating.style.maxHeight = `${Math.max(120, availableHeight - 8)}px`;
        elements.floating.style.overflowY = "auto";
      }
    })],
    whileElementsMounted: autoUpdate
  });
  const { getReferenceProps, getFloatingProps, getItemProps } = useInteractions([
    useRole(context, { role: "listbox" }),
    // The close button must receive its click, an outside press would turn it into the clear button first.
    useDismiss(context, {
      outsidePress: /* @__PURE__ */ __name((event) => !(event.target instanceof Element && event.target.closest("[data-muform-autocomplete-close]")), "outsidePress")
    }),
    useListNavigation(context, { listRef, activeIndex: active, onNavigate: setActive, virtual: true, loop: true })
  ]);
  useEffect(() => {
    if (active !== null) {
      listRef.current[active]?.scrollIntoView({ block: "nearest" });
    }
  }, [active]);
  useEffect(() => {
    if (!open) {
      return void 0;
    }
    setResults({ kind: "loading" });
    const timer = setTimeout(async () => {
      const body = { source: source.class, args: source.args, query };
      try {
        const response = await Fetch.performPost("tool_mulib", "muform/autocomplete", { body });
        const answer = await response.json();
        if (answer.overflow || answer.list === null) {
          setResults({ kind: "overflow" });
        } else {
          setResults({ kind: "list", items: answer.list });
          setActive(answer.list.length ? 0 : null);
        }
      } catch {
        setResults({ kind: "list", items: [] });
      }
    }, 250);
    return () => clearTimeout(timer);
  }, [open, query, source, url]);
  const update = /* @__PURE__ */ __name((next) => {
    setSelected(next);
    onChange(next ? next.value : null);
  }, "update");
  const pick = /* @__PURE__ */ __name((item) => {
    update({ value: item.value, label: item.label, error: null });
    setQuery("");
    setEditing(false);
    setOpen(false);
  }, "pick");
  const clear = /* @__PURE__ */ __name(() => {
    update(null);
    setQuery("");
    setEditing(true);
    focusInput();
  }, "clear");
  const closeSearch = /* @__PURE__ */ __name(() => {
    setOpen(false);
    setEditing(false);
    setQuery("");
  }, "closeSearch");
  const startEditing = /* @__PURE__ */ __name(() => {
    setEditing(true);
    setQuery("");
    setOpen(true);
  }, "startEditing");
  useEffect(() => {
    if (editing) {
      focusInput();
    }
  }, [editing]);
  const onSelectedKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Enter" || event.key === " " || event.key === "ArrowDown") {
      event.preventDefault();
      startEditing();
    } else if (event.key === "Backspace" || event.key === "Delete") {
      event.preventDefault();
      clear();
    }
  }, "onSelectedKeyDown");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      if (open && results.kind === "list" && active !== null && results.items[active]) {
        pick(results.items[active]);
      }
    } else if (event.key === "Tab") {
      closeSearch();
    } else if (event.key === "Escape") {
      setOpen(false);
    }
  }, "onKeyDown");
  const items = results.kind === "list" ? results.items : [];
  const empty = results.kind === "list" && items.length === 0;
  const showSelected = selected !== null && !editing;
  const inputClass = ["form-control", "muform-autocomplete-input", widthclass, showSelected ? "d-none" : ""].filter(Boolean).join(" ");
  const selectedClass = ["form-control", "muform-autocomplete-selected", widthclass, selected?.error ? "is-invalid" : ""].filter(Boolean).join(" ");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-start gap-1 w-100 muform-autocomplete-field", children: [
      showSelected && /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: selectedClass,
          tabIndex: disabled ? -1 : 0,
          role: "button",
          "data-muform-autocomplete-chosen": true,
          "aria-label": labelText(selected.label),
          onClick: disabled ? void 0 : startEditing,
          onKeyDown: disabled ? void 0 : onSelectedKeyDown,
          dangerouslySetInnerHTML: { __html: selected.label }
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
          lineNumber: 279,
          columnNumber: 21
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        "input",
        {
          ref: (node) => {
            inputRef.current = node;
            refs.setReference(node);
          },
          type: "text",
          id,
          className: inputClass,
          role: "combobox",
          "aria-autocomplete": "list",
          autoComplete: "off",
          placeholder: disabled ? void 0 : placeholder,
          disabled,
          value: query,
          ...getReferenceProps({
            onChange: /* @__PURE__ */ __name((event) => {
              setEditing(true);
              setQuery(event.target.value);
              setOpen(true);
            }, "onChange"),
            onFocus: /* @__PURE__ */ __name(() => {
              setEditing(true);
              if (!quietFocus.current) {
                setOpen(true);
              }
            }, "onFocus"),
            onClick: /* @__PURE__ */ __name(() => setOpen(true), "onClick"),
            onKeyDown
          })
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
          lineNumber: 284,
          columnNumber: 17
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        FieldButton,
        {
          open,
          chosen: selected !== null,
          disabled,
          strings,
          onClose: closeSearch,
          onClear: clear
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
          lineNumber: 314,
          columnNumber: 17
        },
        this
      ),
      /* @__PURE__ */ jsxDEV("input", { type: "hidden", name, value: selected ? selected.value : "", disabled }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
        lineNumber: 316,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
      lineNumber: 277,
      columnNumber: 13
    }, this),
    selected?.error && /* @__PURE__ */ jsxDEV("div", { className: "form-text text-danger w-100", children: selected.error }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
      lineNumber: 318,
      columnNumber: 33
    }, this),
    open && /* @__PURE__ */ jsxDEV(
      "ul",
      {
        ref: refs.setFloating,
        style: floatingStyles,
        className: "list-group shadow muform-autocomplete-listbox",
        ...getFloatingProps(),
        children: [
          results.kind === "loading" && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.searching }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
            lineNumber: 322,
            columnNumber: 52
          }, this),
          results.kind === "overflow" && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.toomanyresults }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
            lineNumber: 323,
            columnNumber: 53
          }, this),
          empty && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.noresults }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
            lineNumber: 324,
            columnNumber: 31
          }, this),
          items.map((item, index) => /* @__PURE__ */ jsxDEV(
            "li",
            {
              role: "option",
              "aria-selected": active === index,
              className: `list-group-item list-group-item-action${active === index ? " active" : ""}`,
              "data-muform-autocomplete-option": item.value,
              ref: (node) => {
                listRef.current[index] = node;
              },
              ...getItemProps({
                onClick: /* @__PURE__ */ __name(() => pick(item), "onClick")
              }),
              dangerouslySetInnerHTML: { __html: item.label }
            },
            item.value,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
              lineNumber: 326,
              columnNumber: 25
            },
            this
          ))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
        lineNumber: 320,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletepicker.tsx",
    lineNumber: 276,
    columnNumber: 9
  }, this);
}
__name(Picker, "Picker");
export {
  Picker as default
};
//# sourceMappingURL=autocompletepicker.dev.js.map
