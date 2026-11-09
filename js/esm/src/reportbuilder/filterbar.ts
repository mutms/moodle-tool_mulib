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

import {fetchOne} from '@moodle/lms/core/ajax';
import {requireAsync} from '@moodle/lms/core/amd';
import config from '@moodle/lms/core/config';
import Pending from '@moodle/lms/core/pending';
import {getStrings} from '@moodle/lms/core/stringUtils';

/** Operator meaning "any value" in all supported filter types. */
export const ANY_VALUE = 0;

/** Text filter operator "contains". */
export const TEXT_CONTAINS = 1;

/** Select filter operator "is equal to". */
export const SELECT_EQUAL_TO = 1;

/** Boolean select operator "yes". */
export const BOOLEAN_YES = 1;

/** Boolean select operator "no". */
export const BOOLEAN_NO = 2;

/** Report table reload event, see core_reportbuilder/local/events. */
const TABLE_RELOAD = 'core_reportbuilder_table_reload';

/** Text filter operator "is empty". */
export const TEXT_IS_EMPTY = 7;

/** Text filter operator "is not empty". */
export const TEXT_IS_NOT_EMPTY = 8;

/** Select filters with more options get a search box. */
const SEARCH_THRESHOLD = 10;

/** Option of a select filter. */
export interface FilterOption {
    value: string;
    label: string;
}

/** Filter data exported by tool_mulib\output\report_filter_bar. */
export interface Filter {
    key: string;
    /** Filters of type "other" can be changed in the core form only. */
    type: 'text' | 'select' | 'boolean' | 'other';
    label: string;
    operator: number;
    value: string;
    options: FilterOption[];
    /** Name of an operator the bar does not offer, kept until the user changes the filter. */
    operatorlabel?: string;
    /** Stored values of "other" filter, kept unchanged unless the filter is removed. */
    values?: Record<string, unknown>;
    /** Is "other" filter restricting the report? */
    active?: boolean;
}

/** Localised strings used by the bar. */
export interface Strings {
    add: string;
    advanced: string;
    remove: string;
    apply: string;
    useadvanced: string;
    search: string;
    yes: string;
    no: string;
}

/**
 * Is the filter restricting the report?
 *
 * @param filter
 * @returns true if active
 */
export const isActive = (filter: Filter): boolean => {
    if (filter.type === 'other') {
        return filter.active === true;
    }
    if (filter.operator === ANY_VALUE) {
        return false;
    }
    if (filter.type === 'text') {
        return filter.value.trim() !== '' || filter.operator === TEXT_IS_EMPTY || filter.operator === TEXT_IS_NOT_EMPTY;
    }
    return true;
};

/**
 * Human readable value of an active filter.
 *
 * @param filter
 * @param strings
 * @returns value text
 */
export const describe = (filter: Filter, strings: Strings): string => {
    if (filter.type === 'other') {
        return '';
    }
    if (filter.type === 'boolean') {
        return filter.operator === BOOLEAN_NO ? strings.no : strings.yes;
    }
    let value = filter.value;
    if (filter.type === 'select') {
        value = filter.options.find((option) => option.value === filter.value)?.label ?? filter.value;
    }
    if (filter.operatorlabel) {
        return (filter.operatorlabel.toLowerCase() + ' ' + value).trim();
    }
    return value;
};

/**
 * Filter values in the format of report builder filter forms.
 *
 * @param filters
 * @returns values keyed by form element names
 */
export const buildValues = (filters: Filter[]): Record<string, unknown> => {
    const values: Record<string, unknown> = {};
    for (const filter of filters) {
        const active = isActive(filter);
        if (filter.type === 'other') {
            // Core form defaults are used for values that are not stored.
            if (active) {
                Object.assign(values, filter.values);
            }
            continue;
        }
        values[filter.key + '_operator'] = active ? filter.operator : ANY_VALUE;
        if (filter.type !== 'boolean') {
            values[filter.key + '_value'] = active ? filter.value : '';
        }
    }
    return values;
};

/** Subset of core/fragment. */
interface FragmentApi {
    loadFragment(component: string, callback: string, contextid: number, params: Record<string, unknown>): {
        then(done: (html: string, js: string) => void, fail: (error: unknown) => void): unknown;
    };
}

/** Subset of core/templates. */
interface TemplatesApi {
    replaceNodeContents(element: Element, html: string, js: string): void;
}

/** One filter bar instance. */
class FilterBar {
    private readonly root: HTMLElement;
    private readonly reportId: number;
    private readonly parameters: string;
    private readonly filters: Filter[];
    private readonly strings: Strings;
    private popover: HTMLElement | null = null;
    private ownReload = false;
    private coreFilters: HTMLElement | null = null;

    constructor(root: HTMLElement, strings: Strings) {
        this.root = root;
        this.reportId = Number(root.dataset.reportId);
        this.parameters = root.dataset.reportParameters ?? '[]';
        this.filters = JSON.parse(root.dataset.filters ?? '[]') as Filter[];
        this.strings = strings;
        this.root.style.position = 'relative';
        // Core filters form reloads only the table, reload whole page so that the bar shows current values.
        document.addEventListener(TABLE_RELOAD, (event) => {
            if (!this.ownReload && event.target instanceof Node && this.getReport()?.contains(event.target)) {
                window.location.reload();
            }
        });
        document.addEventListener('click', (event) => {
            if (this.popover && !this.root.contains(event.target as Node)) {
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
    private async adoptCoreFilters(): Promise<void> {
        const report = this.getReport();
        const button = report?.querySelector<HTMLElement>('#dropdownFiltersButton');
        const form = report?.querySelector<HTMLElement>('[data-region="filters-form"]');
        const menu = button?.nextElementSibling;
        if (!report || !button || !form || !(menu instanceof HTMLElement)) {
            return;
        }
        const pending = new Pending('tool_mulib/reportbuilder:filterbar-adopt');
        for (let i = 0; i < 100 && !form.dataset.initialized; i++) {
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        const container = button.parentElement;

        // Core updates the applied filters count in a label inside the report, keep it there hidden.
        const label = document.createElement('span');
        label.dataset.region = 'filter-button-label';
        label.hidden = true;
        container?.append(label);
        if (container && container.querySelectorAll(':scope > :not([hidden])').length === 0) {
            container.classList.add('d-none');
        }

        // Keep core filter icon, the caret shows it is a dropdown.
        const icon = button.querySelector('.icon');
        button.className = 'btn btn-sm btn-outline-secondary dropdown-toggle' + (this.isAdvanced() ? ' fw-bold' : '');
        button.removeAttribute('title');
        button.replaceChildren(...(icon ? [icon] : []), this.strings.advanced);
        const wrapper = document.createElement('div');
        wrapper.className = 'dropdown ms-auto';
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
    private isAdvanced(): boolean {
        return this.filters.some((filter) => filter.operatorlabel || (filter.type === 'other' && isActive(filter)));
    }

    /**
     * Remove filter value.
     *
     * @param filter
     */
    private clear(filter: Filter): void {
        filter.operator = ANY_VALUE;
        filter.value = '';
        delete filter.operatorlabel;
        if (filter.type === 'other') {
            filter.active = false;
            filter.values = {};
        }
    }

    /** Render buttons of the bar. */
    render(): void {
        this.closePopover();
        this.root.replaceChildren();

        const inactive: Filter[] = [];
        for (const filter of this.filters) {
            if (!isActive(filter)) {
                if (filter.type !== 'other') {
                    inactive.push(filter);
                }
                continue;
            }
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';
            group.dataset.filterKey = filter.key;

            const description = describe(filter, this.strings);
            const edit = this.button(description === '' ? filter.label : filter.label + ': ' + description, 'btn btn-primary');
            edit.addEventListener('click', () => this.openEditor(filter, group));
            group.append(edit);

            const remove = this.button('×', 'btn btn-primary');
            remove.setAttribute('aria-label', this.strings.remove + ': ' + filter.label);
            remove.title = this.strings.remove;
            remove.addEventListener('click', () => {
                this.clear(filter);
                void this.save();
            });
            group.append(remove);

            this.root.append(group);
        }

        if (inactive.length) {
            const more = this.button('+ ' + this.strings.add, 'btn btn-sm btn-outline-secondary');
            more.addEventListener('click', () => this.openMore(inactive, more));
            this.root.append(more);
        }

        // Core filters form for anything the bar cannot do, including reset of all filters.
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
    private button(text: string, className: string): HTMLButtonElement {
        const button = document.createElement('button');
        button.type = 'button';
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
    private openPopover(anchor: HTMLElement, content: HTMLElement): void {
        this.closePopover();
        const popover = document.createElement('div');
        popover.className = 'tool_mulib-report-filter-bar-popover card shadow p-2';
        popover.style.position = 'absolute';
        popover.style.zIndex = '1050';
        popover.style.minWidth = '16rem';
        popover.style.maxHeight = '20rem';
        popover.style.overflowY = 'auto';
        popover.style.left = anchor.offsetLeft + 'px';
        popover.style.top = (anchor.offsetTop + anchor.offsetHeight + 4) + 'px';
        popover.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closePopover();
                anchor.focus();
            }
        });
        popover.append(content);
        this.root.append(popover);
        this.popover = popover;
        popover.querySelector<HTMLElement>('input, button')?.focus();
    }

    /** Close open popover. */
    private closePopover(): void {
        this.popover?.remove();
        this.popover = null;
    }

    /**
     * List of inactive filters.
     *
     * @param inactive
     * @param anchor
     */
    private openMore(inactive: Filter[], anchor: HTMLElement): void {
        const list = document.createElement('div');
        list.className = 'list-group list-group-flush';
        for (const filter of inactive) {
            const item = this.button(filter.label, 'list-group-item list-group-item-action border-0');
            item.addEventListener('click', (event) => {
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
    private openEditor(filter: Filter, anchor: HTMLElement): void {
        const content = document.createElement('div');
        const title = document.createElement(filter.type === 'text' ? 'label' : 'div');
        title.className = 'fw-bold mb-2';
        title.textContent = filter.label;
        content.append(title);

        if (filter.type === 'other') {
            const info = document.createElement('div');
            info.className = 'text-muted';
            info.textContent = this.strings.useadvanced;
            content.append(info);
        } else if (filter.type === 'text') {
            const form = document.createElement('form');
            form.className = 'd-flex gap-2';
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.value = isActive(filter) && !filter.operatorlabel ? filter.value : '';
            input.id = this.root.id + '-value';
            if (title instanceof HTMLLabelElement) {
                title.htmlFor = input.id;
            }
            const apply = this.button(this.strings.apply, 'btn btn-sm btn-primary');
            apply.type = 'submit';
            form.append(input, apply);
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                filter.value = input.value.trim();
                filter.operator = filter.value === '' ? ANY_VALUE : TEXT_CONTAINS;
                delete filter.operatorlabel;
                void this.save();
            });
            content.append(form);
        } else if (filter.type === 'boolean') {
            const group = document.createElement('div');
            group.className = 'd-flex gap-2';
            for (const [operator, text] of [[BOOLEAN_YES, this.strings.yes], [BOOLEAN_NO, this.strings.no]] as const) {
                const selected = filter.operator === operator;
                const button = this.button(text, selected ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary');
                button.addEventListener('click', () => {
                    filter.operator = operator;
                    void this.save();
                });
                group.append(button);
            }
            content.append(group);
        } else {
            const list = document.createElement('div');
            list.className = 'list-group list-group-flush';
            const options = filter.options.filter((option) => option.value !== '');
            const renderOptions = (query: string): void => {
                list.replaceChildren();
                const search = query.toLowerCase();
                for (const option of options) {
                    if (search !== '' && !option.label.toLowerCase().includes(search)) {
                        continue;
                    }
                    const selected = isActive(filter) && filter.value === option.value;
                    const item = this.button(option.label, 'list-group-item list-group-item-action border-0'
                        + (selected ? ' active' : ''));
                    item.addEventListener('click', () => {
                        filter.value = option.value;
                        filter.operator = SELECT_EQUAL_TO;
                        delete filter.operatorlabel;
                        void this.save();
                    });
                    list.append(item);
                }
            };
            if (options.length > SEARCH_THRESHOLD) {
                const input = document.createElement('input');
                input.type = 'search';
                input.className = 'form-control form-control-sm mb-2';
                input.placeholder = this.strings.search;
                input.setAttribute('aria-label', this.strings.search);
                input.addEventListener('input', () => renderOptions(input.value.trim()));
                content.append(input);
            }
            renderOptions('');
            content.append(list);
        }

        this.openPopover(anchor, content);
    }

    /** Report element of this bar. */
    private getReport(): HTMLElement | null {
        return document.querySelector<HTMLElement>(
            `[data-region="core_reportbuilder/report"][data-report-id="${this.reportId}"]`
        );
    }

    /** Reload report table. */
    private reload(): void {
        this.ownReload = true;
        try {
            this.getReport()?.dispatchEvent(new CustomEvent(TABLE_RELOAD, {bubbles: true, detail: {}}));
        } finally {
            this.ownReload = false;
        }
    }

    /** Update advanced filters button and reload core form with current values. */
    private async refreshCoreFilters(): Promise<void> {
        const button = this.coreFilters?.querySelector<HTMLElement>('#dropdownFiltersButton');
        button?.classList.toggle('fw-bold', this.isAdvanced());
        const container = this.coreFilters?.querySelector<HTMLElement>('[data-region="filters-form"]');
        if (!container) {
            return;
        }
        const fragment = await requireAsync<FragmentApi>('core/fragment');
        const templates = await requireAsync<TemplatesApi>('core/templates');
        await new Promise<void>((resolve, reject) => {
            fragment.loadFragment('core_reportbuilder', 'filters_form', Number(config.contextid), {
                reportid: this.reportId,
                parameters: this.parameters,
            }).then((html, js) => {
                templates.replaceNodeContents(container, html, js);
                return resolve();
            }, reject);
        });
    }

    /** Store current values and reload report. */
    async save(): Promise<void> {
        const pending = new Pending('tool_mulib/reportbuilder:filterbar');
        this.render();
        try {
            await fetchOne({
                methodname: 'core_reportbuilder_set_filters',
                args: {
                    reportid: this.reportId,
                    parameters: this.parameters,
                    values: JSON.stringify(buildValues(this.filters)),
                },
            });
            this.reload();
            await this.refreshCoreFilters();
        } finally {
            pending.resolve();
        }
    }
}

/**
 * Initialise filter bar.
 *
 * @param root element rendered by tool_mulib/report_filter_bar template
 */
export const initFilterBar = async(root: HTMLElement | null): Promise<void> => {
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    const pending = new Pending('tool_mulib/reportbuilder:filterbar-init');
    try {
        const [add, advanced, remove, apply, useadvanced, search, yes, no] = await getStrings([
            {key: 'filterbar_add', component: 'tool_mulib'},
            {key: 'filterbar_advanced', component: 'tool_mulib'},
            {key: 'filterbar_remove', component: 'tool_mulib'},
            {key: 'filterbar_apply', component: 'tool_mulib'},
            {key: 'filterbar_useadvanced', component: 'tool_mulib'},
            {key: 'search', component: 'core'},
            {key: 'yes', component: 'core'},
            {key: 'no', component: 'core'},
        ]);
        new FilterBar(root, {add, advanced, remove, apply, useadvanced, search, yes, no});
    } finally {
        pending.resolve();
    }
};
