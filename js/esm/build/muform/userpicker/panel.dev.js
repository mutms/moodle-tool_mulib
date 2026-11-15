var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Panel of the users picker family, a React island inside a picker panel dialog.
 *
 * Search field and one page of users with full names and visible identity fields from the
 * tool_mulib userpicker endpoint. One user: Enter picks the highlighted row, a click picks any row.
 * Several users: rows are ticked, Enter ticks the highlighted row and clears the search for the next
 * name, Enter with an empty search adds the ticked users, the footer button adds all ticked.
 *
 * @module     tool_mulib/muform/userpicker/panel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
import { useEffect, useRef, useState } from "react";
const TYPING_DELAY = 250;
function Panel({ source, multiple, selected, strings, onPick, onClose }) {
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
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
      const body = { source: source.class, args: source.args, query, page, exclude: selected };
      try {
        const response = await Fetch.performPost("tool_mulib", "muform/userpicker/search", { body });
        const data = await response.json();
        if (id === request.current) {
          setAnswer(data);
          setActive(0);
          setLoading(false);
          if (enterPending.current) {
            enterPending.current = false;
            enterRows.current(data.rows);
          }
        }
      } catch {
        if (id === request.current) {
          enterPending.current = false;
          setAnswer({ columns: [], rows: [], total: 0, page: 0, perpage: 1 });
          setLoading(false);
        }
      }
    }, delay.current);
    delay.current = 0;
    return () => clearTimeout(timer);
  }, [query, page, source, selected]);
  const rows = answer?.rows ?? [];
  const columns = answer?.columns ?? [];
  const pages = answer ? Math.max(1, Math.ceil(answer.total / answer.perpage)) : 1;
  const isTicked = /* @__PURE__ */ __name((user) => ticked.some((item) => item.id === user.id), "isTicked");
  const pick = /* @__PURE__ */ __name((users) => {
    onPick(users.map((user) => ({ value: String(user.id), label: user.label })));
  }, "pick");
  const toggle = /* @__PURE__ */ __name((user) => {
    setTicked(isTicked(user) ? ticked.filter((item) => item.id !== user.id) : [...ticked, user]);
  }, "toggle");
  const choose = /* @__PURE__ */ __name((user) => {
    if (multiple) {
      toggle(user);
    } else {
      pick([user]);
    }
  }, "choose");
  const enter = /* @__PURE__ */ __name((user) => {
    if (!user) {
      return;
    }
    if (multiple) {
      if (!isTicked(user)) {
        setTicked([...ticked, user]);
      }
      setQuery("");
      setPage(0);
    } else {
      pick([user]);
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
  const allTicked = rows.length > 0 && rows.every(isTicked);
  const toggleAll = /* @__PURE__ */ __name(() => {
    if (allTicked) {
      setTicked(ticked.filter((item) => !rows.some((row) => row.id === item.id)));
    } else {
      setTicked([...ticked, ...rows.filter((row) => !isTicked(row))]);
    }
  }, "toggleAll");
  return /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-column h-100", "data-userpicker-panel": true, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-center gap-2 p-3 border-bottom", children: [
      /* @__PURE__ */ jsxDEV("h2", { className: "h5 mb-0 flex-grow-1", children: strings.title }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 212,
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
          fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
          lineNumber: 213,
          columnNumber: 17
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
      lineNumber: 211,
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
        "data-userpicker-search": true,
        onChange: (event) => {
          delay.current = TYPING_DELAY;
          setQuery(event.target.value);
          setPage(0);
        },
        onKeyDown: onSearchKeyDown
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 217,
        columnNumber: 17
      },
      this
    ) }, void 0, false, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
      lineNumber: 216,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "flex-grow-1 overflow-auto", "aria-busy": loading, children: [
      /* @__PURE__ */ jsxDEV("table", { className: "table table-sm table-hover align-middle mb-0", children: [
        /* @__PURE__ */ jsxDEV("thead", { className: "sticky-top", children: /* @__PURE__ */ jsxDEV("tr", { children: [
          multiple && /* @__PURE__ */ jsxDEV("th", { scope: "col", className: "ps-3", children: /* @__PURE__ */ jsxDEV(
            "input",
            {
              type: "checkbox",
              className: "form-check-input",
              checked: allTicked,
              disabled: !rows.length,
              "aria-label": strings.selectall,
              title: strings.selectall,
              onChange: toggleAll
            },
            void 0,
            false,
            {
              fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
              lineNumber: 232,
              columnNumber: 37
            },
            this
          ) }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 231,
            columnNumber: 33
          }, this),
          /* @__PURE__ */ jsxDEV("th", { scope: "col", className: multiple ? "" : "ps-3", children: strings.name }, void 0, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 237,
            columnNumber: 29
          }, this),
          columns.map((column) => /* @__PURE__ */ jsxDEV("th", { scope: "col", children: column.label }, column.name, false, {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 238,
            columnNumber: 54
          }, this))
        ] }, void 0, true, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
          lineNumber: 229,
          columnNumber: 25
        }, this) }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
          lineNumber: 228,
          columnNumber: 21
        }, this),
        /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((user, index) => /* @__PURE__ */ jsxDEV(
          "tr",
          {
            role: "button",
            className: index === active ? "table-active" : "",
            "data-userpicker-row": user.id,
            "data-userpicker-name": user.fullname,
            onClick: () => choose(user),
            children: [
              multiple && /* @__PURE__ */ jsxDEV("td", { className: "ps-3", children: /* @__PURE__ */ jsxDEV(
                "input",
                {
                  type: "checkbox",
                  className: "form-check-input",
                  checked: isTicked(user),
                  "aria-label": `${strings.selectrow}: ${user.fullname}`,
                  onClick: (event) => event.stopPropagation(),
                  onChange: () => toggle(user)
                },
                void 0,
                false,
                {
                  fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
                  lineNumber: 248,
                  columnNumber: 41
                },
                this
              ) }, void 0, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
                lineNumber: 247,
                columnNumber: 37
              }, this),
              /* @__PURE__ */ jsxDEV("td", { className: multiple ? "" : "ps-3", children: multiple ? user.fullname : /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-link p-0 text-start", children: user.fullname }, void 0, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
                lineNumber: 256,
                columnNumber: 41
              }, this) }, void 0, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
                lineNumber: 254,
                columnNumber: 33
              }, this),
              columns.map((column) => /* @__PURE__ */ jsxDEV("td", { "data-userpicker-field": column.name, children: user.fields[column.name] ?? "" }, column.name, false, {
                fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
                lineNumber: 260,
                columnNumber: 37
              }, this))
            ]
          },
          user.id,
          true,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 243,
            columnNumber: 29
          },
          this
        )) }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
          lineNumber: 241,
          columnNumber: 21
        }, this)
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 227,
        columnNumber: 17
      }, this),
      !rows.length && /* @__PURE__ */ jsxDEV("p", { className: "text-muted p-3 mb-0", "data-userpicker-empty": true, children: loading ? strings.searching : strings.noresults }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 267,
        columnNumber: 21
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
      lineNumber: 226,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap align-items-center gap-2 p-3 border-top", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "text-muted small", "data-userpicker-total": answer ? answer.total : void 0, children: answer ? strings.found.replace("{$a}", String(answer.total)) : "" }, void 0, false, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 271,
        columnNumber: 17
      }, this),
      pages > 1 && answer && /* @__PURE__ */ jsxDEV("span", { className: "d-inline-flex align-items-center gap-1", children: [
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            disabled: answer.page <= 0,
            "aria-label": strings.previous,
            title: strings.previous,
            onClick: () => setPage(answer.page - 1),
            children: "\u2039"
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 276,
            columnNumber: 25
          },
          this
        ),
        /* @__PURE__ */ jsxDEV("span", { className: "small", children: strings.page.replace("{$a}", `${answer.page + 1} / ${pages}`) }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
          lineNumber: 279,
          columnNumber: 25
        }, this),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            disabled: answer.page >= pages - 1,
            "aria-label": strings.next,
            title: strings.next,
            onClick: () => setPage(answer.page + 1),
            children: "\u203A"
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 280,
            columnNumber: 25
          },
          this
        )
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 275,
        columnNumber: 21
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "ms-auto d-inline-flex gap-2", children: [
        multiple && /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-primary",
            disabled: !ticked.length,
            "data-userpicker-add": true,
            onClick: () => pick(ticked),
            children: strings.addselected.replace("{$a}", String(ticked.length))
          },
          void 0,
          false,
          {
            fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
            lineNumber: 287,
            columnNumber: 25
          },
          this
        ),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary", onClick: onClose, children: strings.close }, void 0, false, {
          fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
          lineNumber: 292,
          columnNumber: 21
        }, this)
      ] }, void 0, true, {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
        lineNumber: 285,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
      lineNumber: 270,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/userpicker/panel.tsx",
    lineNumber: 210,
    columnNumber: 9
  }, this);
}
__name(Panel, "Panel");
export {
  Panel as default
};
//# sourceMappingURL=panel.dev.js.map
