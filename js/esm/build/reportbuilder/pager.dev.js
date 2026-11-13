var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Pager of a system report: number of records, page size, download menu and page numbers.
 *
 * It replaces the core paging bar, which is only hidden; pages and page size are changed
 * through the dynamic table of the report. The number of records is shown always,
 * also when all of them fit one page.
 *
 * The download menu replaces the core download form, which is hidden too and read
 * for the download address and formats; all records matching the filters are downloaded.
 *
 * The page size is remembered in the browser tab only, so that a large page
 * does not become the daily default of anybody.
 *
 * @module     tool_mulib/reportbuilder/pager
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { requireAsync } from "@moodle/lms/core/amd";
import Pending from "@moodle/lms/core/pending";
import { getStrings } from "@moodle/lms/core/stringUtils";
const FILTERS_CHANGED = "tool_mulib/reportbuilder:filterschanged";
const TABLE_REFRESHED = "core_table/dynamic:tableContentRefreshed";
const ACTIVE_CLASS = "tool_mulib-report-pager-active";
const DOWNLOAD_CLASS = "tool_mulib-report-pager-download";
const compactPages = /* @__PURE__ */ __name((current, last) => {
  const pages = [];
  let previous = 0;
  for (let page = 1; page <= last; page++) {
    if (page !== 1 && page !== last && Math.abs(page - current) > 1) {
      continue;
    }
    if (page - previous === 2) {
      pages.push(page - 1);
    } else if (page - previous > 2) {
      pages.push(null);
    }
    pages.push(page);
    previous = page;
  }
  return pages;
}, "compactPages");
const rangeText = /* @__PURE__ */ __name((template, from, to, total) => template.replace("{$a->from}", () => String(from)).replace("{$a->to}", () => String(to)).replace("{$a->total}", () => String(total)), "rangeText");
const downloadFormats = /* @__PURE__ */ __name((form) => {
  const select = form.querySelector("select[name]");
  if (!select) {
    return [];
  }
  const params = [];
  for (const input of form.querySelectorAll('input[type="hidden"][name]')) {
    params.push([input.name, input.value]);
  }
  const formats = [];
  for (const option of select.options) {
    const query = new URLSearchParams([...params, [select.name, option.value]]);
    const action = form.getAttribute("action") ?? "";
    formats.push({ label: option.text.trim(), url: action + (action.includes("?") ? "&" : "?") + query.toString() });
  }
  return formats;
}, "downloadFormats");
class Pager {
  static {
    __name(this, "Pager");
  }
  root;
  reportId;
  sizes;
  strings;
  download;
  filtered;
  constructor(root, strings) {
    this.root = root;
    this.reportId = Number(root.dataset.reportId);
    this.sizes = JSON.parse(root.dataset.sizes ?? "[]");
    this.filtered = root.dataset.filtered === "1";
    this.download = root.dataset.download === "1";
    this.strings = strings;
    document.addEventListener(TABLE_REFRESHED, (event) => {
      if (event.target instanceof Node && this.getReport()?.contains(event.target)) {
        this.render();
      }
    });
    document.addEventListener(FILTERS_CHANGED, (event) => {
      if (event.target === this.getReport()) {
        this.filtered = Number(event.detail?.count) > 0;
      }
    });
    this.getReport()?.classList.add(ACTIVE_CLASS);
    this.getReport()?.classList.toggle(DOWNLOAD_CLASS, this.download);
    this.render();
    const stored = this.getStoredSize();
    if (stored !== null && stored !== this.getNumber("tablePageSize")) {
      void this.setPageSize(stored);
    }
  }
  /** Report element of this pager. */
  getReport() {
    return document.querySelector(
      `[data-region="core_reportbuilder/report"][data-report-id="${this.reportId}"]`
    );
  }
  /** Current dynamic table root of the report. */
  getTable() {
    return this.getReport()?.querySelector('[data-region="core_table/dynamic"]') ?? null;
  }
  /**
   * Number from the data attributes of the dynamic table.
   *
   * @param name dataset key
   * @returns value, 0 if missing
   */
  getNumber(name) {
    const value = Number(this.getTable()?.dataset[name]);
    return Number.isFinite(value) ? value : 0;
  }
  /** Name of the page size in the session storage. */
  get storageKey() {
    return "tool_mulib-pagesize-" + this.reportId;
  }
  /**
   * Page size chosen earlier in this browser tab.
   *
   * @returns page size, null if none or not offered any more
   */
  getStoredSize() {
    try {
      const stored = Number(window.sessionStorage.getItem(this.storageKey));
      return this.sizes.includes(stored) ? stored : null;
    } catch {
      return null;
    }
  }
  /**
   * Change page size and go back to the first page.
   *
   * @param size
   */
  async setPageSize(size) {
    const table = this.getTable();
    if (!table) {
      return;
    }
    const pending = new Pending("tool_mulib/reportbuilder:pager");
    try {
      const dynamic = await requireAsync("core_table/dynamic");
      await dynamic.setPageNumber(table, 1, false);
      await dynamic.setPageSize(table, size);
    } finally {
      pending.resolve();
    }
  }
  /**
   * Go to page.
   *
   * @param page page number, starting with 1
   */
  async setPageNumber(page) {
    const table = this.getTable();
    if (!table) {
      return;
    }
    const pending = new Pending("tool_mulib/reportbuilder:pager");
    try {
      const dynamic = await requireAsync("core_table/dynamic");
      await dynamic.setPageNumber(table, page);
    } finally {
      pending.resolve();
    }
  }
  /** Render the pager from the current state of the table. */
  render() {
    const active = document.activeElement;
    const focused = active instanceof HTMLElement && this.root.contains(active) ? active.dataset.pagerControl : void 0;
    const total = this.getNumber("tableTotalRows");
    const size = this.getNumber("tablePageSize");
    this.root.replaceChildren();
    this.root.classList.toggle("d-none", total <= 0 || size <= 0);
    this.root.classList.toggle("d-flex", total > 0 && size > 0);
    if (total <= 0 || size <= 0) {
      return;
    }
    const last = Math.ceil(total / size);
    const current = Math.min(Math.max(this.getNumber("tablePageNumber"), 1), last);
    const range = document.createElement("div");
    range.className = "tool_mulib-report-pager-range text-muted";
    range.setAttribute("role", "status");
    range.textContent = rangeText(
      this.filtered ? this.strings.rangefiltered : this.strings.range,
      (current - 1) * size + 1,
      Math.min(current * size, total),
      total
    );
    this.root.append(range);
    const select = document.createElement("select");
    select.className = "tool_mulib-report-pager-size form-select form-select-sm w-auto";
    select.id = this.root.id + "-size";
    const label = document.createElement("label");
    label.className = "visually-hidden";
    label.htmlFor = select.id;
    label.textContent = this.strings.perpagelabel;
    select.dataset.pagerControl = "size";
    const sizes = this.sizes.includes(size) ? this.sizes : [...this.sizes, size].sort((a, b) => a - b);
    for (const option of sizes) {
      select.append(new Option(
        this.strings.perpage.replace("{$a}", () => String(option)),
        String(option),
        false,
        option === size
      ));
    }
    select.addEventListener("change", () => {
      const value = Number(select.value);
      try {
        window.sessionStorage.setItem(this.storageKey, String(value));
      } catch {
      }
      void this.setPageSize(value);
    });
    this.root.append(label, select);
    const form = this.download ? this.getTable()?.querySelector("form.dataformatselector") : null;
    const formats = form ? downloadFormats(form) : [];
    if (formats.length) {
      this.root.append(this.downloadMenu(formats));
    }
    if (last > 1) {
      this.root.append(this.navigation(current, last));
    }
    if (focused) {
      this.root.querySelector(`[data-pager-control="${focused}"]:not(:disabled)`)?.focus();
    }
  }
  /**
   * Menu with download formats.
   *
   * @param formats
   * @returns dropdown element
   */
  downloadMenu(formats) {
    const dropdown = document.createElement("div");
    dropdown.className = "tool_mulib-report-pager-download dropdown";
    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-sm btn-outline-secondary dropdown-toggle";
    button.dataset.bsToggle = "dropdown";
    button.dataset.pagerControl = "download";
    button.setAttribute("aria-expanded", "false");
    button.textContent = this.filtered ? this.strings.downloadfiltered : this.strings.downloadall;
    const menu = document.createElement("ul");
    menu.className = "dropdown-menu";
    for (const format of formats) {
      const item = document.createElement("li");
      const link = document.createElement("a");
      link.className = "dropdown-item";
      link.href = format.url;
      link.textContent = format.label;
      item.append(link);
      menu.append(item);
    }
    dropdown.append(button, menu);
    return dropdown;
  }
  /**
   * Previous, page numbers and next.
   *
   * @param current current page, starting with 1
   * @param last number of pages
   * @returns navigation element
   */
  navigation(current, last) {
    const nav = document.createElement("nav");
    nav.className = "ms-auto";
    nav.setAttribute("aria-label", this.strings.navigation);
    const list = document.createElement("ul");
    list.className = "pagination pagination-sm flex-wrap mb-0";
    nav.append(list);
    const add = /* @__PURE__ */ __name((text, page, control, label = "") => {
      const item = document.createElement("li");
      item.className = "page-item";
      if (page === null) {
        item.classList.add("disabled");
      }
      let link;
      if (control === "") {
        link = document.createElement("span");
        link.setAttribute("aria-hidden", "true");
      } else {
        const button = document.createElement("button");
        button.type = "button";
        button.disabled = page === null;
        button.dataset.pagerControl = control;
        if (label !== "") {
          button.setAttribute("aria-label", label);
        }
        if (page === current) {
          item.classList.add("active");
          button.setAttribute("aria-current", "page");
        } else if (page !== null) {
          button.addEventListener("click", () => void this.setPageNumber(page));
        }
        link = button;
      }
      link.classList.add("page-link");
      link.textContent = text;
      item.append(link);
      list.append(item);
    }, "add");
    add("\u2039 " + this.strings.previous, current > 1 ? current - 1 : null, "previous");
    for (const page of compactPages(current, last)) {
      if (page === null) {
        add("\u2026", null, "");
      } else {
        const label = this.strings.page.replace("{$a}", () => String(page));
        add(String(page), page, page === current ? "page" : "page" + page, label);
      }
    }
    add(this.strings.next + " \u203A", current < last ? current + 1 : null, "next");
    return nav;
  }
}
const initPager = /* @__PURE__ */ __name(async (root) => {
  if (!root || root.dataset.initialised) {
    return;
  }
  root.dataset.initialised = "1";
  const pending = new Pending("tool_mulib/reportbuilder:pager-init");
  try {
    const [
      range,
      rangefiltered,
      perpage,
      perpagelabel,
      navigation,
      page,
      previous,
      next,
      downloadall,
      downloadfiltered
    ] = await getStrings([
      { key: "pager_range", component: "tool_mulib" },
      { key: "pager_rangefiltered", component: "tool_mulib" },
      { key: "pager_perpage", component: "tool_mulib" },
      { key: "perpage", component: "core" },
      { key: "page", component: "core" },
      { key: "pagea", component: "core" },
      { key: "previous", component: "core" },
      { key: "next", component: "core" },
      { key: "pager_downloadall", component: "tool_mulib" },
      { key: "pager_downloadfiltered", component: "tool_mulib" }
    ]);
    new Pager(root, {
      range,
      rangefiltered,
      perpage,
      perpagelabel,
      navigation,
      page,
      previous,
      next,
      downloadall,
      downloadfiltered
    });
  } finally {
    pending.resolve();
  }
}, "initPager");
export {
  compactPages,
  downloadFormats,
  initPager,
  rangeText
};
//# sourceMappingURL=pager.dev.js.map
