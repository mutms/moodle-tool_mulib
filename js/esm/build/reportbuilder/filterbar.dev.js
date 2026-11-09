var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Always visible filter bar of a system report.
 *
 * Active filters are shown as buttons with their values, inactive filters are collapsed
 * in an "add filter" menu, the core filters form is available as "Advanced filters" button.
 * Values are stored through the standard report builder web services
 * and the report table is reloaded without page reload.
 *
 * @module     tool_mulib/reportbuilder/filterbar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import { requireAsync } from "@moodle/lms/core/amd";
import config from "@moodle/lms/core/config";
import Pending from "@moodle/lms/core/pending";
import { getStrings } from "@moodle/lms/core/stringUtils";
const ANY_VALUE = 0;
const TEXT_CONTAINS = 1;
const SELECT_EQUAL_TO = 1;
const BOOLEAN_YES = 1;
const BOOLEAN_NO = 2;
const TABLE_RELOAD = "core_reportbuilder_table_reload";
const TEXT_IS_EMPTY = 7;
const TEXT_IS_NOT_EMPTY = 8;
const SEARCH_THRESHOLD = 10;
const isActive = /* @__PURE__ */ __name((filter) => {
  if (filter.type === "other") {
    return filter.active === true;
  }
  if (filter.operator === ANY_VALUE) {
    return false;
  }
  if (filter.type === "text") {
    return filter.value.trim() !== "" || filter.operator === TEXT_IS_EMPTY || filter.operator === TEXT_IS_NOT_EMPTY;
  }
  return true;
}, "isActive");
const describe = /* @__PURE__ */ __name((filter, strings) => {
  if (filter.type === "other") {
    return "";
  }
  if (filter.type === "boolean") {
    return filter.operator === BOOLEAN_NO ? strings.no : strings.yes;
  }
  let value = filter.value;
  if (filter.type === "select") {
    value = filter.options.find((option) => option.value === filter.value)?.label ?? filter.value;
  }
  if (filter.operatorlabel) {
    return (filter.operatorlabel.toLowerCase() + " " + value).trim();
  }
  return value;
}, "describe");
const buildValues = /* @__PURE__ */ __name((filters) => {
  const values = {};
  for (const filter of filters) {
    const active = isActive(filter);
    if (filter.type === "other") {
      if (active) {
        Object.assign(values, filter.values);
      }
      continue;
    }
    values[filter.key + "_operator"] = active ? filter.operator : ANY_VALUE;
    if (filter.type !== "boolean") {
      values[filter.key + "_value"] = active ? filter.value : "";
    }
  }
  return values;
}, "buildValues");
class FilterBar {
  static {
    __name(this, "FilterBar");
  }
  root;
  reportId;
  parameters;
  filters;
  strings;
  popover = null;
  ownReload = false;
  coreFilters = null;
  constructor(root, strings) {
    this.root = root;
    this.reportId = Number(root.dataset.reportId);
    this.parameters = root.dataset.reportParameters ?? "[]";
    this.filters = JSON.parse(root.dataset.filters ?? "[]");
    this.strings = strings;
    this.root.style.position = "relative";
    document.addEventListener(TABLE_RELOAD, (event) => {
      if (!this.ownReload && event.target instanceof Node && this.getReport()?.contains(event.target)) {
        window.location.reload();
      }
    });
    document.addEventListener("click", (event) => {
      if (this.popover && !this.root.contains(event.target)) {
        this.closePopover();
      }
    });
    this.render();
    void this.adoptCoreFilters();
  }
  /**
   * Move core filters dropdown into the bar as "Advanced filters" button.
   *
   * The core form must be initialised first, it looks for its elements inside the report.
   */
  async adoptCoreFilters() {
    const report = this.getReport();
    const button = report?.querySelector("#dropdownFiltersButton");
    const form = report?.querySelector('[data-region="filters-form"]');
    const menu = button?.nextElementSibling;
    if (!report || !button || !form || !(menu instanceof HTMLElement)) {
      return;
    }
    const pending = new Pending("tool_mulib/reportbuilder:filterbar-adopt");
    for (let i = 0; i < 100 && !form.dataset.initialized; i++) {
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    const container = button.parentElement;
    const label = document.createElement("span");
    label.dataset.region = "filter-button-label";
    label.hidden = true;
    container?.append(label);
    if (container && container.querySelectorAll(":scope > :not([hidden])").length === 0) {
      container.classList.add("d-none");
    }
    const icon = button.querySelector(".icon");
    button.className = "btn btn-sm btn-outline-secondary dropdown-toggle" + (this.isAdvanced() ? " fw-bold" : "");
    button.removeAttribute("title");
    button.replaceChildren(...icon ? [icon] : [], this.strings.advanced);
    const wrapper = document.createElement("div");
    wrapper.className = "dropdown ms-auto";
    wrapper.append(button, menu);
    this.coreFilters = wrapper;
    this.render();
    pending.resolve();
  }
  /**
   * Is any value set that only the core filters form can edit?
   *
   * @returns true if advanced filters are used
   */
  isAdvanced() {
    return this.filters.some((filter) => filter.operatorlabel || filter.type === "other" && isActive(filter));
  }
  /**
   * Remove filter value.
   *
   * @param filter
   */
  clear(filter) {
    filter.operator = ANY_VALUE;
    filter.value = "";
    delete filter.operatorlabel;
    if (filter.type === "other") {
      filter.active = false;
      filter.values = {};
    }
  }
  /** Render buttons of the bar. */
  render() {
    this.closePopover();
    this.root.replaceChildren();
    const inactive = [];
    for (const filter of this.filters) {
      if (!isActive(filter)) {
        if (filter.type !== "other") {
          inactive.push(filter);
        }
        continue;
      }
      const group = document.createElement("div");
      group.className = "btn-group btn-group-sm";
      group.dataset.filterKey = filter.key;
      const description = describe(filter, this.strings);
      const edit = this.button(description === "" ? filter.label : filter.label + ": " + description, "btn btn-primary");
      edit.addEventListener("click", () => this.openEditor(filter, group));
      group.append(edit);
      const remove = this.button("\xD7", "btn btn-primary");
      remove.setAttribute("aria-label", this.strings.remove + ": " + filter.label);
      remove.title = this.strings.remove;
      remove.addEventListener("click", () => {
        this.clear(filter);
        void this.save();
      });
      group.append(remove);
      this.root.append(group);
    }
    if (inactive.length) {
      const more = this.button("+ " + this.strings.add, "btn btn-sm btn-outline-secondary");
      more.addEventListener("click", () => this.openMore(inactive, more));
      this.root.append(more);
    }
    if (this.coreFilters) {
      this.root.append(this.coreFilters);
    }
  }
  /**
   * Create button.
   *
   * @param text
   * @param className
   * @returns button
   */
  button(text, className) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.textContent = text;
    return button;
  }
  /**
   * Show popover below an anchor element.
   *
   * @param anchor
   * @param content
   */
  openPopover(anchor, content) {
    this.closePopover();
    const popover = document.createElement("div");
    popover.className = "tool_mulib-report-filter-bar-popover card shadow p-2";
    popover.style.position = "absolute";
    popover.style.zIndex = "1050";
    popover.style.minWidth = "16rem";
    popover.style.maxHeight = "20rem";
    popover.style.overflowY = "auto";
    popover.style.left = anchor.offsetLeft + "px";
    popover.style.top = anchor.offsetTop + anchor.offsetHeight + 4 + "px";
    popover.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        this.closePopover();
        anchor.focus();
      }
    });
    popover.append(content);
    this.root.append(popover);
    this.popover = popover;
    popover.querySelector("input, button")?.focus();
  }
  /** Close open popover. */
  closePopover() {
    this.popover?.remove();
    this.popover = null;
  }
  /**
   * List of inactive filters.
   *
   * @param inactive
   * @param anchor
   */
  openMore(inactive, anchor) {
    const list = document.createElement("div");
    list.className = "list-group list-group-flush";
    for (const filter of inactive) {
      const item = this.button(filter.label, "list-group-item list-group-item-action border-0");
      item.addEventListener("click", (event) => {
        event.stopPropagation();
        this.openEditor(filter, anchor);
      });
      list.append(item);
    }
    this.openPopover(anchor, list);
  }
  /**
   * Value editor of one filter.
   *
   * @param filter
   * @param anchor
   */
  openEditor(filter, anchor) {
    const content = document.createElement("div");
    const title = document.createElement(filter.type === "text" ? "label" : "div");
    title.className = "fw-bold mb-2";
    title.textContent = filter.label;
    content.append(title);
    if (filter.type === "other") {
      const info = document.createElement("div");
      info.className = "text-muted";
      info.textContent = this.strings.useadvanced;
      content.append(info);
    } else if (filter.type === "text") {
      const form = document.createElement("form");
      form.className = "d-flex gap-2";
      const input = document.createElement("input");
      input.type = "text";
      input.className = "form-control form-control-sm";
      input.value = isActive(filter) && !filter.operatorlabel ? filter.value : "";
      input.id = this.root.id + "-value";
      if (title instanceof HTMLLabelElement) {
        title.htmlFor = input.id;
      }
      const apply = this.button(this.strings.apply, "btn btn-sm btn-primary");
      apply.type = "submit";
      form.append(input, apply);
      form.addEventListener("submit", (event) => {
        event.preventDefault();
        filter.value = input.value.trim();
        filter.operator = filter.value === "" ? ANY_VALUE : TEXT_CONTAINS;
        delete filter.operatorlabel;
        void this.save();
      });
      content.append(form);
    } else if (filter.type === "boolean") {
      const group = document.createElement("div");
      group.className = "d-flex gap-2";
      for (const [operator, text] of [[BOOLEAN_YES, this.strings.yes], [BOOLEAN_NO, this.strings.no]]) {
        const selected = filter.operator === operator;
        const button = this.button(text, selected ? "btn btn-sm btn-primary" : "btn btn-sm btn-outline-primary");
        button.addEventListener("click", () => {
          filter.operator = operator;
          void this.save();
        });
        group.append(button);
      }
      content.append(group);
    } else {
      const list = document.createElement("div");
      list.className = "list-group list-group-flush";
      const options = filter.options.filter((option) => option.value !== "");
      const renderOptions = /* @__PURE__ */ __name((query) => {
        list.replaceChildren();
        const search = query.toLowerCase();
        for (const option of options) {
          if (search !== "" && !option.label.toLowerCase().includes(search)) {
            continue;
          }
          const selected = isActive(filter) && filter.value === option.value;
          const item = this.button(option.label, "list-group-item list-group-item-action border-0" + (selected ? " active" : ""));
          item.addEventListener("click", () => {
            filter.value = option.value;
            filter.operator = SELECT_EQUAL_TO;
            delete filter.operatorlabel;
            void this.save();
          });
          list.append(item);
        }
      }, "renderOptions");
      if (options.length > SEARCH_THRESHOLD) {
        const input = document.createElement("input");
        input.type = "search";
        input.className = "form-control form-control-sm mb-2";
        input.placeholder = this.strings.search;
        input.setAttribute("aria-label", this.strings.search);
        input.addEventListener("input", () => renderOptions(input.value.trim()));
        content.append(input);
      }
      renderOptions("");
      content.append(list);
    }
    this.openPopover(anchor, content);
  }
  /** Report element of this bar. */
  getReport() {
    return document.querySelector(
      `[data-region="core_reportbuilder/report"][data-report-id="${this.reportId}"]`
    );
  }
  /** Reload report table. */
  reload() {
    this.ownReload = true;
    try {
      this.getReport()?.dispatchEvent(new CustomEvent(TABLE_RELOAD, { bubbles: true, detail: {} }));
    } finally {
      this.ownReload = false;
    }
  }
  /** Update advanced filters button and reload core form with current values. */
  async refreshCoreFilters() {
    const button = this.coreFilters?.querySelector("#dropdownFiltersButton");
    button?.classList.toggle("fw-bold", this.isAdvanced());
    const container = this.coreFilters?.querySelector('[data-region="filters-form"]');
    if (!container) {
      return;
    }
    const fragment = await requireAsync("core/fragment");
    const templates = await requireAsync("core/templates");
    await new Promise((resolve, reject) => {
      fragment.loadFragment("core_reportbuilder", "filters_form", Number(config.contextid), {
        reportid: this.reportId,
        parameters: this.parameters
      }).then((html, js) => {
        templates.replaceNodeContents(container, html, js);
        return resolve();
      }, reject);
    });
  }
  /** Store current values and reload report. */
  async save() {
    const pending = new Pending("tool_mulib/reportbuilder:filterbar");
    this.render();
    try {
      await fetchOne({
        methodname: "core_reportbuilder_set_filters",
        args: {
          reportid: this.reportId,
          parameters: this.parameters,
          values: JSON.stringify(buildValues(this.filters))
        }
      });
      this.reload();
      await this.refreshCoreFilters();
    } finally {
      pending.resolve();
    }
  }
}
const initFilterBar = /* @__PURE__ */ __name(async (root) => {
  if (!root || root.dataset.initialised) {
    return;
  }
  root.dataset.initialised = "1";
  const pending = new Pending("tool_mulib/reportbuilder:filterbar-init");
  try {
    const [add, advanced, remove, apply, useadvanced, search, yes, no] = await getStrings([
      { key: "filterbar_add", component: "tool_mulib" },
      { key: "filterbar_advanced", component: "tool_mulib" },
      { key: "filterbar_remove", component: "tool_mulib" },
      { key: "filterbar_apply", component: "tool_mulib" },
      { key: "filterbar_useadvanced", component: "tool_mulib" },
      { key: "search", component: "core" },
      { key: "yes", component: "core" },
      { key: "no", component: "core" }
    ]);
    new FilterBar(root, { add, advanced, remove, apply, useadvanced, search, yes, no });
  } finally {
    pending.resolve();
  }
}, "initFilterBar");
export {
  ANY_VALUE,
  BOOLEAN_NO,
  BOOLEAN_YES,
  SELECT_EQUAL_TO,
  TEXT_CONTAINS,
  TEXT_IS_EMPTY,
  TEXT_IS_NOT_EMPTY,
  buildValues,
  describe,
  initFilterBar,
  isActive
};
//# sourceMappingURL=filterbar.dev.js.map
