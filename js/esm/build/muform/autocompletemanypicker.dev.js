var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Token field of the autocompletemany element, a React island.
 *
 * Selected values are pills before a combobox input, results come from the
 * tool_mulib autocompletemany endpoint. Written independently of the single
 * value picker on purpose, the two may diverge.
 *
 * @module     tool_mulib/muform/autocompletemanypicker
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
function ManyPicker({ url, source, initial, name, id, placeholder, disabled, strings, onChange }) {
  const [selected, setSelected] = useState(initial);
  const [query, setQuery] = useState("");
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
  const fieldRef = useRef(null);
  const focusPill = useRef(null);
  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange: /* @__PURE__ */ __name((state) => {
      setOpen(state);
      if (!state) {
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
    // The close button must receive its click, an outside press would remove it first.
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
      const body = { source: source.class, args: source.args, query, exclude: selected.map((item) => item.value) };
      try {
        const response = await Fetch.performPost("tool_mulib", "muform/autocompletemany", { body });
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
  }, [open, query, selected, source, url]);
  const update = /* @__PURE__ */ __name((next) => {
    setSelected(next);
    onChange(next.map((item) => item.value));
  }, "update");
  const pick = /* @__PURE__ */ __name((item) => {
    update([...selected, { value: item.value, label: item.label, error: null }]);
    setQuery("");
    setOpen(false);
    focusInput();
  }, "pick");
  const remove = /* @__PURE__ */ __name((value, frompill = false) => {
    const index = selected.findIndex((item) => item.value === value);
    const next = selected.filter((item) => item.value !== value);
    update(next);
    if (frompill && next.length) {
      focusPill.current = Math.min(index, next.length - 1);
    } else {
      focusInput();
    }
  }, "remove");
  useEffect(() => {
    if (focusPill.current === null) {
      return;
    }
    const buttons = fieldRef.current?.querySelectorAll("[data-muform-autocomplete-pill] button");
    buttons?.[focusPill.current]?.focus();
    focusPill.current = null;
  }, [selected]);
  const closeSearch = /* @__PURE__ */ __name(() => {
    setOpen(false);
    setQuery("");
  }, "closeSearch");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      if (open && results.kind === "list" && active !== null && results.items[active]) {
        pick(results.items[active]);
      }
    } else if (event.key === "Backspace" && query === "" && selected.length) {
      remove(selected[selected.length - 1].value);
    } else if (event.key === "Tab") {
      closeSearch();
    } else if (event.key === "Escape" && open) {
      event.preventDefault();
      setOpen(false);
    }
  }, "onKeyDown");
  const items = results.kind === "list" ? results.items : [];
  const fieldClass = disabled ? " disabled" : "";
  const pillClass = /* @__PURE__ */ __name((item) => item.error ? "text-bg-danger" : "bg-white text-body border fw-normal", "pillClass");
  const closeClass = /* @__PURE__ */ __name((item) => item.error ? "btn-close btn-close-white" : "btn-close", "closeClass");
  const empty = results.kind === "list" && items.length === 0;
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: `form-control d-flex flex-wrap align-items-center gap-1 muform-autocompletemany-field${fieldClass}`,
        ref: fieldRef,
        onClick: () => inputRef.current?.focus(),
        children: [
          selected.map((item) => /* @__PURE__ */ jsxDEV(
            "span",
            {
              className: `badge d-inline-flex align-items-center gap-1 ${pillClass(item)}`,
              title: item.error ?? void 0,
              "data-muform-autocomplete-pill": item.value,
              children: [
                /* @__PURE__ */ jsxDEV("span", { dangerouslySetInnerHTML: { __html: item.label } }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
                  lineNumber: 249,
                  columnNumber: 25
                }, this),
                item.error && /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: item.error }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
                  lineNumber: 250,
                  columnNumber: 40
                }, this),
                !disabled && /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: closeClass(item),
                    style: { fontSize: ".6em" },
                    "aria-label": strings.remove.replace("{$a}", labelText(item.label)),
                    onClick: (event) => {
                      event.stopPropagation();
                      remove(item.value, true);
                    }
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
                    lineNumber: 252,
                    columnNumber: 29
                  },
                  this
                )
              ]
            },
            item.value,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
              lineNumber: 247,
              columnNumber: 21
            },
            this
          )),
          /* @__PURE__ */ jsxDEV(
            "input",
            {
              ref: (node) => {
                inputRef.current = node;
                refs.setReference(node);
              },
              type: "text",
              id,
              className: "border-0 flex-grow-1 bg-transparent muform-autocomplete-input",
              role: "combobox",
              "aria-autocomplete": "list",
              autoComplete: "off",
              placeholder: disabled ? void 0 : placeholder,
              disabled,
              value: query,
              ...getReferenceProps({
                onChange: /* @__PURE__ */ __name((event) => {
                  setQuery(event.target.value);
                  setOpen(true);
                }, "onChange"),
                onFocus: /* @__PURE__ */ __name(() => {
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
              fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
              lineNumber: 261,
              columnNumber: 17
            },
            this
          ),
          open && !disabled && /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn-close ms-auto",
              style: { fontSize: ".6em" },
              "aria-label": strings.close,
              title: strings.close,
              "data-muform-autocomplete-close": true,
              onMouseDown: (event) => event.preventDefault(),
              onClick: (event) => {
                event.stopPropagation();
                closeSearch();
              }
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
              lineNumber: 290,
              columnNumber: 21
            },
            this
          ),
          /* @__PURE__ */ jsxDEV("input", { type: "hidden", name, value: selected.map((item) => item.value).join(","), disabled }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
            lineNumber: 298,
            columnNumber: 17
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
        lineNumber: 244,
        columnNumber: 13
      },
      this
    ),
    open && /* @__PURE__ */ jsxDEV(
      "ul",
      {
        ref: refs.setFloating,
        style: floatingStyles,
        className: "list-group shadow muform-autocomplete-listbox",
        ...getFloatingProps(),
        children: [
          results.kind === "loading" && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.searching }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
            lineNumber: 303,
            columnNumber: 52
          }, this),
          results.kind === "overflow" && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.toomanyresults }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
            lineNumber: 304,
            columnNumber: 53
          }, this),
          empty && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.noresults }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
            lineNumber: 305,
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
              fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
              lineNumber: 307,
              columnNumber: 25
            },
            this
          ))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
        lineNumber: 301,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/autocompletemanypicker.tsx",
    lineNumber: 243,
    columnNumber: 9
  }, this);
}
__name(ManyPicker, "ManyPicker");
export {
  ManyPicker as default
};
//# sourceMappingURL=autocompletemanypicker.dev.js.map
