// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

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

import {requireAsync} from '@moodle/lms/core/amd';
import Pending from '@moodle/lms/core/pending';
import {getStrings} from '@moodle/lms/core/stringUtils';

/** Dispatched by the filter bar on the report element, the detail has the count of active filters. */
const FILTERS_CHANGED = 'tool_mulib/reportbuilder:filterschanged';

/** Dispatched by core on the new table root after every reload, see core_table/local/dynamic/events. */
const TABLE_REFRESHED = 'core_table/dynamic:tableContentRefreshed';

/** Class of the report element that hides the core paging bar. */
const ACTIVE_CLASS = 'tool_mulib-report-pager-active';

/** Class of the report element that hides the core download form. */
const DOWNLOAD_CLASS = 'tool_mulib-report-pager-download';

/** Localised strings used by the pager. */
export interface Strings {
    /** Range of all records, with {$a->from}, {$a->to} and {$a->total} placeholders. */
    range: string;
    /** Range of filtered records, with the same placeholders. */
    rangefiltered: string;
    /** Page size option, with {$a} placeholder. */
    perpage: string;
    /** Label of the page size select. */
    perpagelabel: string;
    /** Label of the page navigation. */
    navigation: string;
    /** Label of a page number, with {$a} placeholder. */
    page: string;
    previous: string;
    next: string;
    /** Download menu button of reports without applied filters. */
    downloadall: string;
    /** Download menu button of filtered reports. */
    downloadfiltered: string;
}

/** Download format read from the core download form. */
export interface DownloadFormat {
    label: string;
    url: string;
}

/** Subset of core_table/dynamic. */
interface DynamicTable {
    setPageNumber(tableRoot: HTMLElement, pageNumber: number, refreshContent?: boolean): Promise<HTMLElement>;
    setPageSize(tableRoot: HTMLElement, pageSize: number, refreshContent?: boolean): Promise<HTMLElement>;
}

/**
 * Page numbers to show: first, last and the neighbours of the current page, null stands for an ellipsis.
 *
 * @param current current page, starting with 1
 * @param last number of pages
 * @returns pages such as [1, null, 4, 5, 6, null, 26]
 */
export const compactPages = (current: number, last: number): (number | null)[] => {
    const pages: (number | null)[] = [];
    let previous = 0;
    for (let page = 1; page <= last; page++) {
        if (page !== 1 && page !== last && Math.abs(page - current) > 1) {
            continue;
        }
        if (page - previous === 2) {
            // An ellipsis instead of one page would not save any space.
            pages.push(page - 1);
        } else if (page - previous > 2) {
            pages.push(null);
        }
        pages.push(page);
        previous = page;
    }
    return pages;
};

/**
 * Text with the range of shown records, such as "Showing 31–40 of 257".
 *
 * @param template string with {$a->from}, {$a->to} and {$a->total} placeholders
 * @param from first shown record
 * @param to last shown record
 * @param total number of records
 * @returns text
 */
export const rangeText = (template: string, from: number, to: number, total: number): string => template
    .replace('{$a->from}', () => String(from))
    .replace('{$a->to}', () => String(to))
    .replace('{$a->total}', () => String(total));

/**
 * Download links of all formats offered by the core download form of a table.
 *
 * @param form form.dataformatselector, a GET form with hidden parameters and a select of formats
 * @returns formats with download addresses
 */
export const downloadFormats = (form: HTMLFormElement): DownloadFormat[] => {
    const select = form.querySelector<HTMLSelectElement>('select[name]');
    if (!select) {
        return [];
    }
    const params: [string, string][] = [];
    for (const input of form.querySelectorAll<HTMLInputElement>('input[type="hidden"][name]')) {
        params.push([input.name, input.value]);
    }
    const formats: DownloadFormat[] = [];
    for (const option of select.options) {
        const query = new URLSearchParams([...params, [select.name, option.value]]);
        const action = form.getAttribute('action') ?? '';
        formats.push({label: option.text.trim(), url: action + (action.includes('?') ? '&' : '?') + query.toString()});
    }
    return formats;
};

/** One pager instance. */
class Pager {
    private readonly root: HTMLElement;
    private readonly reportId: number;
    private readonly sizes: number[];
    private readonly strings: Strings;
    private readonly download: boolean;
    private filtered: boolean;

    constructor(root: HTMLElement, strings: Strings) {
        this.root = root;
        this.reportId = Number(root.dataset.reportId);
        this.sizes = JSON.parse(root.dataset.sizes ?? '[]') as number[];
        this.filtered = root.dataset.filtered === '1';
        this.download = root.dataset.download === '1';
        this.strings = strings;

        // The table root is replaced on every reload, the events bubble from the new one.
        document.addEventListener(TABLE_REFRESHED, (event) => {
            if (event.target instanceof Node && this.getReport()?.contains(event.target)) {
                this.render();
            }
        });
        document.addEventListener(FILTERS_CHANGED, (event) => {
            if (event.target === this.getReport()) {
                // The table is being reloaded, the range is rendered again after that.
                this.filtered = Number((event as CustomEvent<{count?: number}>).detail?.count) > 0;
            }
        });

        this.getReport()?.classList.add(ACTIVE_CLASS);
        this.getReport()?.classList.toggle(DOWNLOAD_CLASS, this.download);
        this.render();

        const stored = this.getStoredSize();
        if (stored !== null && stored !== this.getNumber('tablePageSize')) {
            void this.setPageSize(stored);
        }
    }

    /** Report element of this pager. */
    private getReport(): HTMLElement | null {
        return document.querySelector<HTMLElement>(
            `[data-region="core_reportbuilder/report"][data-report-id="${this.reportId}"]`
        );
    }

    /** Current dynamic table root of the report. */
    private getTable(): HTMLElement | null {
        return this.getReport()?.querySelector<HTMLElement>('[data-region="core_table/dynamic"]') ?? null;
    }

    /**
     * Number from the data attributes of the dynamic table.
     *
     * @param name dataset key
     * @returns value, 0 if missing
     */
    private getNumber(name: string): number {
        const value = Number(this.getTable()?.dataset[name]);
        return Number.isFinite(value) ? value : 0;
    }

    /** Name of the page size in the session storage. */
    private get storageKey(): string {
        return 'tool_mulib-pagesize-' + this.reportId;
    }

    /**
     * Page size chosen earlier in this browser tab.
     *
     * @returns page size, null if none or not offered any more
     */
    private getStoredSize(): number | null {
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
    private async setPageSize(size: number): Promise<void> {
        const table = this.getTable();
        if (!table) {
            return;
        }
        const pending = new Pending('tool_mulib/reportbuilder:pager');
        try {
            const dynamic = await requireAsync<DynamicTable>('core_table/dynamic');
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
    private async setPageNumber(page: number): Promise<void> {
        const table = this.getTable();
        if (!table) {
            return;
        }
        const pending = new Pending('tool_mulib/reportbuilder:pager');
        try {
            const dynamic = await requireAsync<DynamicTable>('core_table/dynamic');
            await dynamic.setPageNumber(table, page);
        } finally {
            pending.resolve();
        }
    }

    /** Render the pager from the current state of the table. */
    render(): void {
        // Keep keyboard focus on the control that was used, all of them are created again.
        const active = document.activeElement;
        const focused = active instanceof HTMLElement && this.root.contains(active) ? active.dataset.pagerControl : undefined;

        const total = this.getNumber('tableTotalRows');
        const size = this.getNumber('tablePageSize');
        this.root.replaceChildren();
        // Reports without records print their own notice.
        this.root.classList.toggle('d-none', total <= 0 || size <= 0);
        this.root.classList.toggle('d-flex', total > 0 && size > 0);
        if (total <= 0 || size <= 0) {
            return;
        }
        const last = Math.ceil(total / size);
        const current = Math.min(Math.max(this.getNumber('tablePageNumber'), 1), last);

        const range = document.createElement('div');
        range.className = 'tool_mulib-report-pager-range text-muted';
        range.setAttribute('role', 'status');
        range.textContent = rangeText(
            this.filtered ? this.strings.rangefiltered : this.strings.range,
            (current - 1) * size + 1,
            Math.min(current * size, total),
            total,
        );
        this.root.append(range);

        const select = document.createElement('select');
        select.className = 'tool_mulib-report-pager-size form-select form-select-sm w-auto';
        select.id = this.root.id + '-size';
        const label = document.createElement('label');
        label.className = 'visually-hidden';
        label.htmlFor = select.id;
        label.textContent = this.strings.perpagelabel;
        select.dataset.pagerControl = 'size';
        // The current size may be something else after "Show all" of core tables.
        const sizes = this.sizes.includes(size) ? this.sizes : [...this.sizes, size].sort((a, b) => a - b);
        for (const option of sizes) {
            select.append(new Option(this.strings.perpage.replace('{$a}', () => String(option)), String(option), false,
                option === size));
        }
        select.addEventListener('change', () => {
            const value = Number(select.value);
            try {
                window.sessionStorage.setItem(this.storageKey, String(value));
            } catch {
                // The size is not remembered then.
            }
            void this.setPageSize(value);
        });
        this.root.append(label, select);

        const form = this.download ? this.getTable()?.querySelector<HTMLFormElement>('form.dataformatselector') : null;
        const formats = form ? downloadFormats(form) : [];
        if (formats.length) {
            this.root.append(this.downloadMenu(formats));
        }

        if (last > 1) {
            this.root.append(this.navigation(current, last));
        }

        if (focused) {
            this.root.querySelector<HTMLElement>(`[data-pager-control="${focused}"]:not(:disabled)`)?.focus();
        }
    }

    /**
     * Menu with download formats.
     *
     * @param formats
     * @returns dropdown element
     */
    private downloadMenu(formats: DownloadFormat[]): HTMLElement {
        const dropdown = document.createElement('div');
        dropdown.className = 'tool_mulib-report-pager-download dropdown';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary dropdown-toggle';
        button.dataset.bsToggle = 'dropdown';
        button.dataset.pagerControl = 'download';
        button.setAttribute('aria-expanded', 'false');
        // The download has all pages, but only the records matching the filters.
        button.textContent = this.filtered ? this.strings.downloadfiltered : this.strings.downloadall;
        const menu = document.createElement('ul');
        menu.className = 'dropdown-menu';
        for (const format of formats) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.className = 'dropdown-item';
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
    private navigation(current: number, last: number): HTMLElement {
        const nav = document.createElement('nav');
        nav.className = 'ms-auto';
        nav.setAttribute('aria-label', this.strings.navigation);
        const list = document.createElement('ul');
        list.className = 'pagination pagination-sm flex-wrap mb-0';
        nav.append(list);

        const add = (text: string, page: number | null, control: string, label = ''): void => {
            const item = document.createElement('li');
            item.className = 'page-item';
            if (page === null) {
                // Ellipsis or a disabled button.
                item.classList.add('disabled');
            }
            let link: HTMLElement;
            if (control === '') {
                link = document.createElement('span');
                link.setAttribute('aria-hidden', 'true');
            } else {
                const button = document.createElement('button');
                button.type = 'button';
                button.disabled = page === null;
                button.dataset.pagerControl = control;
                if (label !== '') {
                    button.setAttribute('aria-label', label);
                }
                if (page === current) {
                    item.classList.add('active');
                    button.setAttribute('aria-current', 'page');
                } else if (page !== null) {
                    button.addEventListener('click', () => void this.setPageNumber(page));
                }
                link = button;
            }
            link.classList.add('page-link');
            link.textContent = text;
            item.append(link);
            list.append(item);
        };

        add('‹ ' + this.strings.previous, current > 1 ? current - 1 : null, 'previous');
        for (const page of compactPages(current, last)) {
            if (page === null) {
                add('…', null, '');
            } else {
                // The current page keeps the focus after going to another page with a page number.
                const label = this.strings.page.replace('{$a}', () => String(page));
                add(String(page), page, page === current ? 'page' : 'page' + page, label);
            }
        }
        add(this.strings.next + ' ›', current < last ? current + 1 : null, 'next');
        return nav;
    }
}

/**
 * Initialise pager.
 *
 * @param root element rendered by tool_mulib/report_pager template
 */
export const initPager = async(root: HTMLElement | null): Promise<void> => {
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    const pending = new Pending('tool_mulib/reportbuilder:pager-init');
    try {
        const [
            range, rangefiltered, perpage, perpagelabel, navigation, page, previous, next, downloadall, downloadfiltered,
        ] = await getStrings([
            {key: 'pager_range', component: 'tool_mulib'},
            {key: 'pager_rangefiltered', component: 'tool_mulib'},
            {key: 'pager_perpage', component: 'tool_mulib'},
            {key: 'perpage', component: 'core'},
            {key: 'page', component: 'core'},
            {key: 'pagea', component: 'core'},
            {key: 'previous', component: 'core'},
            {key: 'next', component: 'core'},
            {key: 'pager_downloadall', component: 'tool_mulib'},
            {key: 'pager_downloadfiltered', component: 'tool_mulib'},
        ]);
        new Pager(root, {
            range, rangefiltered, perpage, perpagelabel, navigation, page, previous, next, downloadall, downloadfiltered,
        });
    } finally {
        pending.resolve();
    }
};
