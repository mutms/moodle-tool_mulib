var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Tag field of the tags element, a React island.
 *
 * Tags are pills before a combobox input. Typed text becomes a tag on Enter or comma,
 * unless only standard tags may be used; standard tags are suggested by the tool_mulib
 * tags endpoint. Written independently of the autocomplete pickers on purpose.
 *
 * @module     tool_mulib/muform/tagspicker
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
function normaliseTag(text) {
  return text.replace(/\s+/g, " ").trim();
}
__name(normaliseTag, "normaliseTag");
function TagsPicker({ area, initial, suggest, standardonly, name, id, placeholder, disabled, strings, onChange }) {
  const [tags, setTags] = useState(initial);
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
      if (!state && standardonly) {
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
      outsidePress: /* @__PURE__ */ __name((event) => !(event.target instanceof Element && event.target.closest("[data-muform-tags-close]")), "outsidePress")
    }),
    useListNavigation(context, { listRef, activeIndex: active, onNavigate: setActive, virtual: true, loop: true })
  ]);
  useEffect(() => {
    if (active !== null) {
      listRef.current[active]?.scrollIntoView({ block: "nearest" });
    }
  }, [active]);
  useEffect(() => {
    if (!open || !suggest) {
      return void 0;
    }
    setResults({ kind: "loading" });
    const timer = setTimeout(async () => {
      const body = { area: area.class, args: area.args, query, exclude: tags.map((tag) => tag.name) };
      try {
        const response = await Fetch.performPost("tool_mulib", "muform/tags", { body });
        const answer = await response.json();
        if (answer.overflow || answer.list === null) {
          setResults({ kind: "overflow" });
        } else {
          setResults({ kind: "list", items: answer.list });
          setActive(standardonly && answer.list.length ? 0 : null);
        }
      } catch {
        setResults({ kind: "list", items: [] });
      }
    }, 250);
    return () => clearTimeout(timer);
  }, [open, suggest, standardonly, query, tags, area]);
  const update = /* @__PURE__ */ __name((next) => {
    setTags(next);
    onChange(next.map((tag) => tag.name));
  }, "update");
  const add = /* @__PURE__ */ __name((text, refocus = true) => {
    const tag = normaliseTag(text);
    setQuery("");
    setOpen(false);
    if (refocus) {
      focusInput();
    }
    if (tag === "" || tags.some((existing) => existing.name.toLowerCase() === tag.toLowerCase())) {
      return;
    }
    update([...tags, { name: tag, error: null }]);
  }, "add");
  const remove = /* @__PURE__ */ __name((tag, frompill = false) => {
    const index = tags.findIndex((existing) => existing.name === tag);
    const next = tags.filter((existing) => existing.name !== tag);
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
    const buttons = fieldRef.current?.querySelectorAll("[data-muform-tags-pill] button");
    buttons?.[focusPill.current]?.focus();
    focusPill.current = null;
  }, [tags]);
  const items = results.kind === "list" ? results.items : [];
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Enter" || event.key === ",") {
      if (event.key === "Enter" && query === "" && active === null) {
        return;
      }
      event.preventDefault();
      if (open && active !== null && items[active]) {
        add(items[active]);
      } else if (!standardonly) {
        add(query);
      }
    } else if (event.key === "Backspace" && query === "" && tags.length) {
      remove(tags[tags.length - 1].name);
    } else if (event.key === "Tab") {
      setOpen(false);
      if (standardonly) {
        setQuery("");
      }
    } else if (event.key === "Escape") {
      setOpen(false);
    }
  }, "onKeyDown");
  const fieldClass = disabled ? " disabled" : "";
  const pillClass = /* @__PURE__ */ __name((tag) => tag.error ? "text-bg-danger" : "bg-secondary-subtle text-body fw-normal", "pillClass");
  const closeClass = /* @__PURE__ */ __name((tag) => tag.error ? "btn-close btn-close-white" : "btn-close", "closeClass");
  const empty = results.kind === "list" && items.length === 0;
  const showlist = open && suggest && (results.kind !== "list" || items.length > 0 || query !== "");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: `form-control d-flex flex-wrap align-items-center gap-1 muform-tags-field${fieldClass}`,
        ref: fieldRef,
        onClick: () => inputRef.current?.focus(),
        children: [
          tags.map((tag) => /* @__PURE__ */ jsxDEV(
            "span",
            {
              className: `badge d-inline-flex align-items-center gap-1 ${pillClass(tag)}`,
              title: tag.error ?? void 0,
              "data-muform-tags-pill": tag.name,
              children: [
                /* @__PURE__ */ jsxDEV("span", { children: tag.name }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
                  lineNumber: 265,
                  columnNumber: 25
                }, this),
                tag.error && /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: tag.error }, void 0, false, {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
                  lineNumber: 266,
                  columnNumber: 39
                }, this),
                !disabled && /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: closeClass(tag),
                    style: { fontSize: ".6em" },
                    "aria-label": strings.remove.replace("{$a}", tag.name),
                    onClick: (event) => {
                      event.stopPropagation();
                      remove(tag.name, true);
                    }
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
                    lineNumber: 268,
                    columnNumber: 29
                  },
                  this
                )
              ]
            },
            tag.name,
            true,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
              lineNumber: 263,
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
              className: "border-0 flex-grow-1 bg-transparent muform-tags-input",
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
                onBlur: /* @__PURE__ */ __name(() => {
                  if (!standardonly && normaliseTag(query) !== "") {
                    add(query, false);
                  }
                }, "onBlur"),
                onKeyDown
              })
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
              lineNumber: 277,
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
              "data-muform-tags-close": true,
              onMouseDown: (event) => event.preventDefault(),
              onClick: (event) => {
                event.stopPropagation();
                setOpen(false);
                setQuery("");
              }
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
              lineNumber: 312,
              columnNumber: 21
            },
            this
          ),
          /* @__PURE__ */ jsxDEV("input", { type: "hidden", name, value: tags.map((tag) => tag.name).join(","), disabled }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
            lineNumber: 321,
            columnNumber: 17
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
        lineNumber: 260,
        columnNumber: 13
      },
      this
    ),
    showlist && /* @__PURE__ */ jsxDEV(
      "ul",
      {
        ref: refs.setFloating,
        style: floatingStyles,
        className: "list-group shadow muform-tags-listbox",
        ...getFloatingProps(),
        children: [
          results.kind === "loading" && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.searching }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
            lineNumber: 326,
            columnNumber: 52
          }, this),
          results.kind === "overflow" && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.toomanyresults }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
            lineNumber: 327,
            columnNumber: 53
          }, this),
          empty && /* @__PURE__ */ jsxDEV("li", { className: "list-group-item text-muted", children: strings.noresults }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
            lineNumber: 328,
            columnNumber: 31
          }, this),
          items.map((item, index) => /* @__PURE__ */ jsxDEV(
            "li",
            {
              role: "option",
              "aria-selected": active === index,
              className: `list-group-item list-group-item-action${active === index ? " active" : ""}`,
              "data-muform-tags-option": item,
              ref: (node) => {
                listRef.current[index] = node;
              },
              ...getItemProps({
                // Keep focus in the input, the blur would add the typed text.
                onMouseDown: /* @__PURE__ */ __name((event) => event.preventDefault(), "onMouseDown"),
                onClick: /* @__PURE__ */ __name(() => add(item), "onClick")
              }),
              children: item
            },
            item,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
              lineNumber: 330,
              columnNumber: 25
            },
            this
          ))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
        lineNumber: 324,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/tagspicker.tsx",
    lineNumber: 259,
    columnNumber: 9
  }, this);
}
__name(TagsPicker, "TagsPicker");
export {
  TagsPicker as default,
  normaliseTag
};
//# sourceMappingURL=tagspicker.dev.js.map
