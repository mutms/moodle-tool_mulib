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
 * Date ranges are typed or picked in the timezone of the user, the server
 * interprets the texts through the muform datetime endpoint.
 *
 * @module     tool_mulib/reportbuilder/filterbar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import { requireAsync } from "@moodle/lms/core/amd";
import config from "@moodle/lms/core/config";
import { mountReactApp } from "@moodle/lms/core/mount";
import Pending from "@moodle/lms/core/pending";
import { getStrings } from "@moodle/lms/core/stringUtils";
import { normalise } from "../muform/datetimeapi";
import DateTimePicker from "../muform/datetimepicker";
const ANY_VALUE = 0;
const TEXT_CONTAINS = 1;
const SELECT_EQUAL_TO = 1;
const BOOLEAN_YES = 1;
const BOOLEAN_NO = 2;
const TABLE_RELOAD = "core_reportbuilder_table_reload";
const TEXT_IS_EMPTY = 7;
const TEXT_IS_NOT_EMPTY = 8;
const DATE_NOT_EMPTY = 1;
const DATE_EMPTY = 2;
const DATE_RANGE = 3;
const DATE_LAST = 4;
const DATE_CURRENT = 5;
const DATE_NEXT = 6;
const DATE_PAST = 7;
const DATE_FUTURE = 8;
const DATE_BEFORE = 9;
const DATE_AFTER = 10;
const DATE_UNIT_DAY = 1;
const DATE_RELATIVE = [DATE_LAST, DATE_NEXT, DATE_BEFORE, DATE_AFTER];
const DATE_UNIT_NAMES = { "5": "minute", "0": "hour", "1": "day", "2": "week", "3": "month", "4": "year" };
const SEARCH_THRESHOLD = 10;
const fill = /* @__PURE__ */ __name((template, value) => template.replace("{$a}", () => value), "fill");
const formatUnits = /* @__PURE__ */ __name((count, unit, settings) => {
  const lang = (settings?.lang ?? "en").replace("_", "-");
  try {
    return new Intl.NumberFormat(lang, { style: "unit", unit: DATE_UNIT_NAMES[unit], unitDisplay: "long" }).format(count);
  } catch {
    const label = settings?.units.find((option) => option.value === String(unit))?.label ?? "";
    return (count + " " + label).trim();
  }
}, "formatUnits");
const formatUnitName = /* @__PURE__ */ __name((unit, settings) => {
  const lang = (settings?.lang ?? "en").replace("_", "-");
  try {
    const names = new Intl.DisplayNames(lang, { type: "dateTimeField" });
    const name = names.of(DATE_UNIT_NAMES[unit] === "week" ? "weekOfYear" : DATE_UNIT_NAMES[unit]);
    if (name) {
      return name;
    }
  } catch {
  }
  return settings?.units.find((option) => option.value === String(unit))?.label ?? "";
}, "formatUnitName");
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
  if (filter.type === "date") {
    if (filter.operator === DATE_RANGE) {
      return (filter.from ?? 0) > 0 || (filter.to ?? 0) > 0;
    }
    if (filter.operator === DATE_LAST || filter.operator === DATE_NEXT) {
      return Number(filter.value) > 0;
    }
  }
  return true;
}, "isActive");
const describe = /* @__PURE__ */ __name((filter, strings, settings = null) => {
  if (filter.type === "other") {
    return "";
  }
  if (filter.type === "boolean") {
    return filter.operator === BOOLEAN_NO ? strings.no : strings.yes;
  }
  if (filter.type === "date") {
    return describeDate(filter, strings, settings);
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
const describeDate = /* @__PURE__ */ __name((filter, strings, settings) => {
  if (filter.operator === DATE_RANGE) {
    const from = filter.from ? filter.fromtext ?? "" : "";
    const to = filter.to ? filter.totext ?? "" : "";
    if (from !== "" && to !== "") {
      return from + " \u2013 " + to;
    }
    return from !== "" ? fill(strings.rangefrom, from) : fill(strings.rangeto, to);
  }
  const label = (filter.options.find((option) => option.value === String(filter.operator))?.label ?? "").toLowerCase();
  const unit = filter.unit ?? DATE_UNIT_DAY;
  if (DATE_RELATIVE.includes(filter.operator)) {
    return (label + " " + formatUnits(Number(filter.value), unit, settings)).trim();
  }
  if (filter.operator === DATE_CURRENT) {
    return (label + " " + formatUnitName(unit, settings)).trim();
  }
  return label;
}, "describeDate");
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
    if (filter.type === "date") {
      const relative = active && (DATE_RELATIVE.includes(filter.operator) || filter.operator === DATE_CURRENT);
      const range = active && filter.operator === DATE_RANGE;
      values[filter.key + "_value"] = relative && DATE_RELATIVE.includes(filter.operator) ? Number(filter.value) : 1;
      values[filter.key + "_unit"] = relative ? filter.unit ?? DATE_UNIT_DAY : DATE_UNIT_DAY;
      values[filter.key + "_from"] = range ? filter.from ?? 0 : 0;
      values[filter.key + "_to"] = range ? filter.to ?? 0 : 0;
      continue;
    }
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
  datetime;
  popover = null;
  /** Unmount functions of React islands in the popover. */
  popoverCleanup = [];
  /** Strings of the date picker, loaded with the first date editor. */
  pickerStrings = null;
  ownReload = false;
  coreFilters = null;
  constructor(root, strings) {
    this.root = root;
    this.reportId = Number(root.dataset.reportId);
    this.parameters = root.dataset.reportParameters ?? "[]";
    this.filters = JSON.parse(root.dataset.filters ?? "[]");
    this.datetime = JSON.parse(root.dataset.datetime || "null");
    this.strings = strings;
    this.root.style.position = "relative";
    document.addEventListener(TABLE_RELOAD, (event) => {
      if (!this.ownReload && event.target instanceof Node && this.getReport()?.contains(event.target)) {
        window.location.reload();
      }
    });
    document.addEventListener("click", (event) => {
      if (this.popover && !event.composedPath().includes(this.root)) {
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
    if (filter.type === "date") {
      filter.value = "1";
      filter.from = 0;
      filter.to = 0;
      filter.fromtext = "";
      filter.totext = "";
    }
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
      const description = describe(filter, this.strings, this.datetime);
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
      if (event.key === "Escape" && !event.defaultPrevented) {
        this.closePopover();
        anchor.focus();
      }
    });
    popover.append(content);
    this.root.append(popover);
    this.popover = popover;
    popover.querySelector("input, select, button")?.focus();
  }
  /** Close open popover. */
  closePopover() {
    for (const cleanup of this.popoverCleanup) {
      cleanup();
    }
    this.popoverCleanup = [];
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
    } else if (filter.type === "date") {
      content.append(this.dateEditor(filter));
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
  /**
   * Create label.
   *
   * @param text
   * @param input labelled element
   * @param hidden visually hidden label
   * @returns label
   */
  label(text, input, hidden = false) {
    const label = document.createElement("label");
    label.className = hidden ? "visually-hidden" : "form-label small mb-1";
    label.htmlFor = input.id;
    label.textContent = text;
    return label;
  }
  /**
   * Create select.
   *
   * @param id
   * @param options
   * @param value
   * @returns select
   */
  select(id, options, value) {
    const select = document.createElement("select");
    select.className = "form-select form-select-sm";
    select.id = id;
    for (const option of options) {
      select.append(new Option(option.label, option.value, false, option.value === value));
    }
    return select;
  }
  /**
   * Editor of date filter.
   *
   * @param filter
   * @returns form
   */
  dateEditor(filter) {
    const strings = this.strings;
    const id = this.root.id;
    const form = document.createElement("form");
    form.noValidate = true;
    const active = isActive(filter);
    const operators = filter.options;
    let operator = String(filter.operator);
    if (!active || !operators.some((option) => option.value === operator)) {
      const range2 = operators.find((option) => option.value === String(DATE_RANGE));
      operator = (range2 ?? operators[0])?.value ?? "";
    }
    const operatorSelect = this.select(id + "-operator", operators, operator);
    operatorSelect.classList.add("mb-2");
    form.append(this.label(fill(strings.fieldoperator, filter.label), operatorSelect, true), operatorSelect);
    const relative = document.createElement("div");
    relative.className = "d-flex gap-2 mb-2";
    const valueInput = document.createElement("input");
    valueInput.type = "number";
    valueInput.className = "form-control form-control-sm";
    valueInput.style.width = "5rem";
    valueInput.id = id + "-value";
    valueInput.value = active && filter.value !== "" ? filter.value : "1";
    const unitSelect = this.select(id + "-unit", this.datetime?.units ?? [], String(filter.unit ?? DATE_UNIT_DAY));
    relative.append(
      this.label(fill(strings.fieldvalue, filter.label), valueInput, true),
      valueInput,
      this.label(fill(strings.fieldunit, filter.label), unitSelect, true),
      unitSelect
    );
    form.append(relative);
    const range = document.createElement("div");
    const from = this.dateInput(range, id + "-from", strings.datefrom, filter.from ?? 0, filter.fromtext ?? "", 0, 0);
    const to = this.dateInput(range, id + "-to", strings.dateto, filter.to ?? 0, filter.totext ?? "", 23, 59);
    if (this.datetime?.timezonelabel) {
      const timezone = document.createElement("div");
      timezone.className = "form-text text-muted mb-2";
      timezone.textContent = this.datetime.timezonelabel;
      range.append(timezone);
    }
    form.append(range);
    const update = /* @__PURE__ */ __name(() => {
      const current = Number(operatorSelect.value);
      relative.hidden = !DATE_RELATIVE.includes(current) && current !== DATE_CURRENT;
      valueInput.hidden = current === DATE_CURRENT;
      valueInput.min = current === DATE_LAST || current === DATE_NEXT ? "1" : "0";
      range.hidden = current !== DATE_RANGE;
    }, "update");
    operatorSelect.addEventListener("change", update);
    update();
    const apply = this.button(strings.apply, "btn btn-sm btn-primary");
    apply.type = "submit";
    form.append(apply);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      void (async () => {
        const current = Number(operatorSelect.value);
        if (current === DATE_RANGE) {
          const [start, end] = await Promise.all([from.normalise(), to.normalise()]);
          if (!start || !end) {
            return;
          }
          if (start.timestamp !== null && end.timestamp !== null && start.timestamp > end.timestamp) {
            to.setError(strings.invalidrange);
            return;
          }
          filter.from = start.timestamp ?? 0;
          filter.fromtext = start.text;
          filter.to = end.timestamp ?? 0;
          filter.totext = end.text;
        } else if (DATE_RELATIVE.includes(current)) {
          const value = Number(valueInput.value);
          if (valueInput.value.trim() === "" || !Number.isInteger(value) || value < Number(valueInput.min)) {
            valueInput.classList.add("is-invalid");
            valueInput.focus();
            return;
          }
          filter.value = String(value);
        }
        filter.operator = current;
        filter.unit = Number(unitSelect.value);
        delete filter.operatorlabel;
        if (!isActive(filter)) {
          this.clear(filter);
        }
        await this.save();
      })();
    });
    return form;
  }
  /**
   * Date and time text input with picker, the server interprets the text.
   *
   * @param container
   * @param id
   * @param label
   * @param timestamp current value, 0 if none
   * @param text current value text
   * @param hour default hour of dates without time
   * @param minute default minute of dates without time
   * @returns normalise function that resolves to null if the text is invalid, and error setter
   */
  dateInput(container, id, label, timestamp, text, hour, minute) {
    const wrapper = document.createElement("div");
    wrapper.className = "mb-2";
    const row = document.createElement("div");
    row.className = "d-flex gap-1";
    const input = document.createElement("input");
    input.type = "text";
    input.className = "form-control form-control-sm";
    input.id = id;
    input.autocomplete = "off";
    input.value = timestamp ? text : "";
    input.dataset.muformDatetimeTimezone = this.datetime?.timezone ?? "";
    input.dataset.muformDatetimeLang = this.datetime?.lang ?? "";
    input.dataset.muformDatetimeDefaulthour = String(hour);
    input.dataset.muformDatetimeDefaultminute = String(minute);
    const feedback = document.createElement("div");
    feedback.className = "invalid-feedback";
    feedback.id = id + "-error";
    input.setAttribute("aria-describedby", feedback.id);
    const picker = document.createElement("span");
    row.append(input, picker);
    wrapper.append(this.label(label, input), row, feedback);
    container.append(wrapper);
    const setError = /* @__PURE__ */ __name((message) => {
      input.classList.toggle("is-invalid", message !== "");
      feedback.textContent = message;
      feedback.classList.toggle("d-block", message !== "");
    }, "setError");
    const apply = /* @__PURE__ */ __name(async (value) => {
      const answer = await normalise(input, value);
      if (!answer.valid) {
        setError(this.strings.invalid);
        return null;
      }
      input.value = answer.text;
      setError("");
      return answer;
    }, "apply");
    input.addEventListener("change", () => void apply(input.value));
    if (timestamp) {
      void normalise(input, String(timestamp));
    }
    void this.mountPicker(picker, input, async (value) => {
      await apply(value);
    });
    return { normalise: /* @__PURE__ */ __name(() => apply(input.value), "normalise"), setError };
  }
  /**
   * Mount date picker next to a date input.
   *
   * @param span
   * @param input
   * @param apply
   */
  async mountPicker(span, input, apply) {
    if (!this.pickerStrings) {
      const [pick, today, clear, applytext, hour, minute, monthprev, monthnext, yearprev, yearnext] = await getStrings(
        ["pickdatetime", "today", "clear", "apply", "hour", "minute", "monthprev", "monthnext", "yearprev", "yearnext"].map((key) => ({ key: "muform_" + key, component: "tool_mulib" }))
      );
      this.pickerStrings = { pick, today, clear, apply: applytext, hour, minute, monthprev, monthnext, yearprev, yearnext };
    }
    if (!span.isConnected) {
      return;
    }
    this.popoverCleanup.push(mountReactApp(span, DateTimePicker, {
      input,
      step: 5,
      firstDay: this.datetime?.firstday ?? null,
      hour12: this.datetime?.hour12 ?? false,
      disabled: false,
      strings: this.pickerStrings,
      apply
    }, { id: span.id || input.id + "-picker" }));
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
    const [
      add,
      advanced,
      remove,
      apply,
      useadvanced,
      rangefrom,
      rangeto,
      search,
      yes,
      no,
      datefrom,
      dateto,
      fieldoperator,
      fieldvalue,
      fieldunit,
      invalid,
      invalidrange
    ] = await getStrings([
      { key: "filterbar_add", component: "tool_mulib" },
      { key: "filterbar_advanced", component: "tool_mulib" },
      { key: "filterbar_remove", component: "tool_mulib" },
      { key: "filterbar_apply", component: "tool_mulib" },
      { key: "filterbar_useadvanced", component: "tool_mulib" },
      { key: "filterbar_rangefrom", component: "tool_mulib" },
      { key: "filterbar_rangeto", component: "tool_mulib" },
      { key: "search", component: "core" },
      { key: "yes", component: "core" },
      { key: "no", component: "core" },
      { key: "filterdatefrom", component: "core_reportbuilder" },
      { key: "filterdateto", component: "core_reportbuilder" },
      { key: "filterfieldoperator", component: "core_reportbuilder" },
      { key: "filterfieldvalue", component: "core_reportbuilder" },
      { key: "filterfieldunit", component: "core_reportbuilder" },
      { key: "muform_datetimeinvalid", component: "tool_mulib" },
      { key: "muform_datetimerangeinvalid", component: "tool_mulib" }
    ]);
    new FilterBar(root, {
      add,
      advanced,
      remove,
      apply,
      useadvanced,
      search,
      yes,
      no,
      rangefrom,
      rangeto,
      datefrom,
      dateto,
      fieldoperator,
      fieldvalue,
      fieldunit,
      invalid,
      invalidrange
    });
  } finally {
    pending.resolve();
  }
}, "initFilterBar");
export {
  ANY_VALUE,
  BOOLEAN_NO,
  BOOLEAN_YES,
  DATE_AFTER,
  DATE_BEFORE,
  DATE_CURRENT,
  DATE_EMPTY,
  DATE_FUTURE,
  DATE_LAST,
  DATE_NEXT,
  DATE_NOT_EMPTY,
  DATE_PAST,
  DATE_RANGE,
  DATE_UNIT_DAY,
  SELECT_EQUAL_TO,
  TEXT_CONTAINS,
  TEXT_IS_EMPTY,
  TEXT_IS_NOT_EMPTY,
  buildValues,
  describe,
  formatUnitName,
  formatUnits,
  initFilterBar,
  isActive
};
//# sourceMappingURL=filterbar.dev.js.map
