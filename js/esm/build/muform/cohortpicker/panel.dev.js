var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Panel of the cohorts picker family, a React island inside a picker panel dialog.
 *
 * Search field and the cohorts the source offers, each below the course category it belongs to; only
 * categories with found cohorts and their parents are shown, so a search by name keeps the matching
 * cohorts in their place of the tree. One cohort: Enter picks the highlighted row, a click picks any row.
 * Several cohorts: rows are ticked, Enter ticks the highlighted row and clears the search for the next
 * name, Enter with an empty search adds the ticked cohorts, the footer button adds all ticked.
 *
 * @module     tool_mulib/muform/cohortpicker/panel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
import { useEffect, useRef, useState } from "react";
const TYPING_DELAY = 250;
const INDENT = 1.25;
function Panel({ source, multiple, selected, strings, onPick, onClose }) {
  const [query, setQuery] = useState("");
  const [answer, setAnswer] = useState(null);
  const [loading, setLoading] = useState(true);
  const [active, setActive] = useState(0);
  const [ticked, setTicked] = useState([]);
  const searchRef = useRef(null);
  const request = useRef(0);
  const delay = useRef(0);
  const enterPending = useRef(false);
  const enterRows = useRef(() => void 0);
  useEffect(() => {
    searchRef.current?.focus();
  }, []);
  useEffect(() => {
    setLoading(true);
    const id = ++request.current;
    const timer = setTimeout(async () => {
      const body = { source: source.class, args: source.args, query, exclude: selected };
      let data = { nodes: [], cohorts: [], total: 0, overflow: false };
      let failed = false;
      try {
        const response = await Fetch.performPost("tool_mulib", "muform/cohortpicker/search", { body });
        data = await response.json();
      } catch {
        failed = true;
      }
      if (id !== request.current) {
        return;
      }
      const ordered = inTreeOrder(data);
      setAnswer(data);
      setActive(0);
      setLoading(false);
      if (enterPending.current) {
        enterPending.current = false;
        if (!failed) {
          enterRows.current(ordered);
        }
      }
    }, delay.current);
    delay.current = 0;
    return () => clearTimeout(timer);
  }, [query, source, selected]);
  const rows = answer ? inTreeOrder(answer) : [];
  const isTicked = /* @__PURE__ */ __name((cohort) => ticked.some((item) => item.id === cohort.id), "isTicked");
  const pick = /* @__PURE__ */ __name((cohorts) => {
    onPick(cohorts.map((cohort) => ({ value: String(cohort.id), label: cohort.label })));
  }, "pick");
  const toggle = /* @__PURE__ */ __name((cohort) => {
    setTicked(isTicked(cohort) ? ticked.filter((item) => item.id !== cohort.id) : [...ticked, cohort]);
  }, "toggle");
  const choose = /* @__PURE__ */ __name((cohort) => {
    if (multiple) {
      toggle(cohort);
    } else {
      pick([cohort]);
    }
  }, "choose");
  const enter = /* @__PURE__ */ __name((cohort) => {
    if (!cohort) {
      return;
    }
    if (multiple) {
      if (!isTicked(cohort)) {
        setTicked([...ticked, cohort]);
      }
      setQuery("");
    } else {
      pick([cohort]);
    }
  }, "enter");
  enterRows.current = (found) => enter(found[0]);
  const onSearchKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      setActive(Math.min(active + 1, Math.max(0, rows.length - 1)));
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setActive(Math.max(active - 1, 0));
    } else if (event.key === "Enter") {
      event.preventDefault();
      if (multiple && query.trim() === "" && ticked.length) {
        pick(ticked);
      } else if (loading) {
        enterPending.current = true;
      } else {
        enter(rows[active]);
      }
    } else {
      enterPending.current = false;
    }
  }, "onSearchKeyDown");
  return /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-column h-100", "data-cohortpicker-panel": true, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center gap-2 p-3 border-bottom", children: [
      /* @__PURE__ */ jsxDEV("h2", { className: "h5 mb-0 flex-grow-1", children: strings.title }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 204,
        columnNumber: 17
      }, this),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn-close",
          "aria-label": strings.close,
          title: strings.close,
          onClick: onClose
        },
        void 0,
        false,
        {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
          lineNumber: 205,
          columnNumber: 17
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
      lineNumber: 203,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "p-3 border-bottom", children: /* @__PURE__ */ jsxDEV(
      "input",
      {
        ref: searchRef,
        type: "search",
        className: "form-control",
        autoComplete: "off",
        placeholder: strings.search,
        "aria-label": strings.search,
        value: query,
        "data-cohortpicker-search": true,
        onChange: (event) => {
          delay.current = TYPING_DELAY;
          setQuery(event.target.value);
        },
        onKeyDown: onSearchKeyDown
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 209,
        columnNumber: 17
      },
      this
    ) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
      lineNumber: 208,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "flex-grow-1 overflow-auto py-2", "aria-busy": loading, children: [
      (answer?.nodes ?? []).map((node) => /* @__PURE__ */ jsxDEV("div", { "data-cohortpicker-node": node.id, children: [
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "fw-bold pe-3 py-1",
            style: { paddingInlineStart: `${1 + node.depth * INDENT}rem` },
            "data-cohortpicker-node-name": node.name,
            "data-cohortpicker-depth": node.depth,
            children: node.name
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
            lineNumber: 220,
            columnNumber: 25
          },
          this
        ),
        rows.filter((cohort) => cohort.nodeid === node.id).map((cohort) => /* @__PURE__ */ jsxDEV(
          "div",
          {
            role: "button",
            className: "d-flex align-items-center gap-2 pe-3 py-1" + (rows[active]?.id === cohort.id ? " bg-body-secondary" : ""),
            style: { paddingInlineStart: `${1 + (node.depth + 1) * INDENT}rem` },
            "data-cohortpicker-row": cohort.id,
            "data-cohortpicker-name": cohort.name,
            "data-cohortpicker-idnumber": cohort.idnumber,
            onClick: () => choose(cohort),
            children: [
              multiple && /* @__PURE__ */ jsxDEV(
                "input",
                {
                  type: "checkbox",
                  className: "form-check-input mt-0",
                  checked: isTicked(cohort),
                  "aria-label": `${strings.selectrow}: ${cohort.name}`,
                  onClick: (event) => event.stopPropagation(),
                  onChange: () => toggle(cohort)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
                  lineNumber: 233,
                  columnNumber: 37
                },
                this
              ),
              multiple ? /* @__PURE__ */ jsxDEV("span", { children: cohort.name }, void 0, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
                lineNumber: 238,
                columnNumber: 45
              }, this) : /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-link p-0 text-start", children: cohort.name }, void 0, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
                lineNumber: 239,
                columnNumber: 37
              }, this),
              cohort.idnumber !== "" && /* @__PURE__ */ jsxDEV("span", { className: "text-muted small", children: cohort.idnumber }, void 0, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
                lineNumber: 241,
                columnNumber: 60
              }, this)
            ]
          },
          cohort.id,
          true,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
            lineNumber: 225,
            columnNumber: 29
          },
          this
        ))
      ] }, node.id, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 219,
        columnNumber: 21
      }, this)),
      !rows.length && /* @__PURE__ */ jsxDEV("p", { className: "text-muted px-3 mb-0", "data-cohortpicker-empty": true, children: loading ? strings.searching : strings.noresults }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 247,
        columnNumber: 21
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
      lineNumber: 217,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap align-items-center gap-2 p-3 border-top", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "text-muted small", "data-cohortpicker-total": answer ? answer.total : void 0, children: answer ? strings.found.replace("{$a}", String(answer.total)) : "" }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 253,
        columnNumber: 17
      }, this),
      answer?.overflow && /* @__PURE__ */ jsxDEV("span", { className: "text-warning-emphasis small", "data-cohortpicker-overflow": true, children: strings.overflow }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 257,
        columnNumber: 21
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "ms-auto d-inline-flex gap-2", children: [
        multiple && /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-primary",
            disabled: !ticked.length,
            "data-cohortpicker-add": true,
            onClick: () => pick(ticked),
            children: strings.addselected.replace("{$a}", String(ticked.length))
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
            lineNumber: 261,
            columnNumber: 25
          },
          this
        ),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary", onClick: onClose, children: strings.close }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
          lineNumber: 266,
          columnNumber: 21
        }, this)
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
        lineNumber: 259,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
      lineNumber: 252,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/cohortpicker/panel.tsx",
    lineNumber: 202,
    columnNumber: 9
  }, this);
}
__name(Panel, "Panel");
function inTreeOrder(answer) {
  return answer.nodes.flatMap((node) => answer.cohorts.filter((cohort) => cohort.nodeid === node.id));
}
__name(inTreeOrder, "inTreeOrder");
export {
  Panel as default
};
//# sourceMappingURL=panel.dev.js.map
