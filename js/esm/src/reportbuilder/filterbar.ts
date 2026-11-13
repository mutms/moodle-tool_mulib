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
 * Active filters are shown as buttons with their values, pinned filters are shown always,
 * other inactive filters are collapsed in an "add filter" menu, the core filters form is available as "Advanced filters" button.
 * Values are stored through the standard report builder web services
 * and the report table is reloaded without page reload.
 *
 * Date ranges are typed or picked in the timezone of the user, the server
 * interprets the texts through the muform datetime endpoint.
 *
 * @module     tool_mulib/reportbuilder/filterbar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {requireAsync} from '@moodle/lms/core/amd';
import config from '@moodle/lms/core/config';
import {mountReactApp} from '@moodle/lms/core/mount';
import Pending from '@moodle/lms/core/pending';
import {getStrings} from '@moodle/lms/core/stringUtils';
import {normalise, type Answer} from '../muform/datetimeapi';
import DateTimePicker, {type PickerStrings} from '../muform/datetimepicker';

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

/** Dispatched on the report element after filters are stored, the detail has the count of active filters. */
export const FILTERS_CHANGED = 'tool_mulib/reportbuilder:filterschanged';

/** Text filter operator "is empty". */
export const TEXT_IS_EMPTY = 7;

/** Text filter operator "is not empty". */
export const TEXT_IS_NOT_EMPTY = 8;

/** Date filter operator "is not empty". */
export const DATE_NOT_EMPTY = 1;

/** Date filter operator "is empty". */
export const DATE_EMPTY = 2;

/** Date filter operator "range". */
export const DATE_RANGE = 3;

/** Date filter operator "last". */
export const DATE_LAST = 4;

/** Date filter operator "current". */
export const DATE_CURRENT = 5;

/** Date filter operator "next". */
export const DATE_NEXT = 6;

/** Date filter operator "in the past". */
export const DATE_PAST = 7;

/** Date filter operator "in the future". */
export const DATE_FUTURE = 8;

/** Date filter operator "before". */
export const DATE_BEFORE = 9;

/** Date filter operator "after". */
export const DATE_AFTER = 10;

/** Date filter unit "day", the core default. */
export const DATE_UNIT_DAY = 1;

/** Date filter operators with number of units. */
const DATE_RELATIVE = [DATE_LAST, DATE_NEXT, DATE_BEFORE, DATE_AFTER];

/** Intl names of date filter units. */
const DATE_UNIT_NAMES: Record<number, string> = {'5': 'minute', '0': 'hour', '1': 'day', '2': 'week', '3': 'month', '4': 'year'};

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
    type: 'text' | 'select' | 'boolean' | 'date' | 'other';
    label: string;
    operator: number;
    /** Text, selected option or number of units of relative date operators. */
    value: string;
    /** Select options, operators offered in date filters, or values of boolean filters that do not offer both. */
    options: FilterOption[];
    /** Unit of relative date operators. */
    unit?: number;
    /** Start of date range, 0 means none. */
    from?: number;
    /** End of date range, 0 means none. */
    to?: number;
    /** Start of date range in the timezone of the user. */
    fromtext?: string;
    /** End of date range in the timezone of the user. */
    totext?: string;
    /** Name of an operator the bar does not offer, kept until the user changes the filter. */
    operatorlabel?: string;
    /** Stored values of "other" filter, kept unchanged unless the filter is removed. */
    values?: Record<string, unknown>;
    /** Is "other" filter restricting the report? */
    active?: boolean;
    /** Pinned filters are always shown in the bar. */
    pinned?: boolean;
    /** Text of a pinned filter that is not restricting the report, such as "Any". */
    anylabel?: string;
    /** Default value defined by the page, stored for users without stored filters. */
    default?: Pick<Filter, 'operator' | 'value' | 'unit' | 'from' | 'to' | 'fromtext' | 'totext' | 'operatorlabel'>;
}

/** Date and time settings of the user, exported when the report has date filters. */
export interface DateTimeSettings {
    timezone: string;
    /** Shown in the editor when the user is not in the site timezone. */
    timezonelabel: string;
    /** Moodle language code. */
    lang: string;
    firstday: number;
    hour12: boolean;
    units: FilterOption[];
}

/** Localised strings used by the bar. */
export interface Strings {
    add: string;
    advanced: string;
    remove: string;
    apply: string;
    useadvanced: string;
    resetdefault: string;
    resetall: string;
    search: string;
    yes: string;
    no: string;
    /** Description of ranges without end, with {$a} placeholder. */
    rangefrom: string;
    /** Description of ranges without start, with {$a} placeholder. */
    rangeto: string;
    datefrom: string;
    dateto: string;
    /** Labels of date filter fields, with {$a} placeholder for filter name. */
    fieldoperator: string;
    fieldvalue: string;
    fieldunit: string;
    invalid: string;
    invalidrange: string;
}

/**
 * Replace {$a} placeholder in a string.
 *
 * @param template string with placeholder
 * @param value
 * @returns text
 */
const fill = (template: string, value: string): string => template.replace('{$a}', () => value);

/**
 * Plain text of a string exported with clean_string(), which has HTML entities and no tags.
 *
 * All texts are added to the page as text nodes, entities would be shown as they are.
 *
 * @param html
 * @returns text
 */
export const decodeEntities = (html: string): string => {
    if (!html.includes('&')) {
        return html;
    }
    // Parsed documents are inert, nothing is loaded or executed.
    return new DOMParser().parseFromString(html, 'text/html').body.textContent ?? '';
};

/**
 * Number of date units in words, such as "2 weeks".
 *
 * @param count
 * @param unit date filter unit
 * @param settings
 * @returns text
 */
export const formatUnits = (count: number, unit: number, settings: DateTimeSettings | null): string => {
    const lang = (settings?.lang ?? 'en').replace('_', '-');
    try {
        return new Intl.NumberFormat(lang, {style: 'unit', unit: DATE_UNIT_NAMES[unit], unitDisplay: 'long'}).format(count);
    } catch {
        const label = settings?.units.find((option) => option.value === String(unit))?.label ?? '';
        return (count + ' ' + label).trim();
    }
};

/**
 * Name of a date unit, such as "week".
 *
 * @param unit date filter unit
 * @param settings
 * @returns text
 */
export const formatUnitName = (unit: number, settings: DateTimeSettings | null): string => {
    const lang = (settings?.lang ?? 'en').replace('_', '-');
    try {
        const names = new Intl.DisplayNames(lang, {type: 'dateTimeField' as Intl.DisplayNamesType});
        // Intl calls the week field "weekOfYear".
        const name = names.of(DATE_UNIT_NAMES[unit] === 'week' ? 'weekOfYear' : DATE_UNIT_NAMES[unit]);
        if (name) {
            return name;
        }
    } catch {
        // Fall back to the unit label below.
    }
    return settings?.units.find((option) => option.value === String(unit))?.label ?? '';
};

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
    if (filter.type === 'date') {
        if (filter.operator === DATE_RANGE) {
            // Core ignores ranges without dates.
            return (filter.from ?? 0) > 0 || (filter.to ?? 0) > 0;
        }
        if (filter.operator === DATE_LAST || filter.operator === DATE_NEXT) {
            // Core ignores zero units here, not in "before" and "after".
            return Number(filter.value) > 0;
        }
    }
    return true;
};

/**
 * Human readable value of an active filter.
 *
 * @param filter
 * @param strings
 * @param settings date and time settings, null if there are no date filters
 * @returns value text
 */
export const describe = (filter: Filter, strings: Strings, settings: DateTimeSettings | null = null): string => {
    if (filter.type === 'other') {
        return '';
    }
    if (filter.type === 'boolean') {
        return filter.operator === BOOLEAN_NO ? strings.no : strings.yes;
    }
    if (filter.type === 'date') {
        return describeDate(filter, strings, settings);
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
 * Human readable value of an active date filter.
 *
 * @param filter
 * @param strings
 * @param settings
 * @returns value text
 */
const describeDate = (filter: Filter, strings: Strings, settings: DateTimeSettings | null): string => {
    if (filter.operator === DATE_RANGE) {
        const from = filter.from ? (filter.fromtext ?? '') : '';
        const to = filter.to ? (filter.totext ?? '') : '';
        if (from !== '' && to !== '') {
            return from + ' \u2013 ' + to;
        }
        return from !== '' ? fill(strings.rangefrom, from) : fill(strings.rangeto, to);
    }
    const label = (filter.options.find((option) => option.value === String(filter.operator))?.label ?? '').toLowerCase();
    const unit = filter.unit ?? DATE_UNIT_DAY;
    if (DATE_RELATIVE.includes(filter.operator)) {
        return (label + ' ' + formatUnits(Number(filter.value), unit, settings)).trim();
    }
    if (filter.operator === DATE_CURRENT) {
        return (label + ' ' + formatUnitName(unit, settings)).trim();
    }
    return label;
};

/**
 * Text of the filter button in the bar.
 *
 * @param filter active or pinned filter
 * @param strings
 * @param settings date and time settings, null if there are no date filters
 * @returns button text
 */
export const buttonLabel = (filter: Filter, strings: Strings, settings: DateTimeSettings | null = null): string => {
    if (!isActive(filter)) {
        return filter.label + ': ' + (filter.anylabel ?? '');
    }
    const description = describe(filter, strings, settings);
    return description === '' ? filter.label : filter.label + ': ' + description;
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
        if (filter.type === 'date') {
            // Defaults of the core form for unused parts.
            const relative = active && (DATE_RELATIVE.includes(filter.operator) || filter.operator === DATE_CURRENT);
            const range = active && filter.operator === DATE_RANGE;
            values[filter.key + '_value'] = relative && DATE_RELATIVE.includes(filter.operator) ? Number(filter.value) : 1;
            values[filter.key + '_unit'] = relative ? (filter.unit ?? DATE_UNIT_DAY) : DATE_UNIT_DAY;
            values[filter.key + '_from'] = range ? (filter.from ?? 0) : 0;
            values[filter.key + '_to'] = range ? (filter.to ?? 0) : 0;
            continue;
        }
        if (filter.type !== 'boolean') {
            values[filter.key + '_value'] = active ? filter.value : '';
        }
    }
    return values;
};

/**
 * Has the filter its default value?
 *
 * @param filter
 * @returns true if the stored values would be the same as the default, false for filters without default
 */
export const isDefault = (filter: Filter): boolean => {
    if (!filter.default) {
        return false;
    }
    return JSON.stringify(buildValues([filter])) === JSON.stringify(buildValues([{...filter, ...filter.default}]));
};

/**
 * Is the filter in the state of a report that was never filtered?
 *
 * @param filter
 * @returns true if the filter has its default value, or does not filter when it has no default
 */
export const isInitial = (filter: Filter): boolean => (filter.default ? isDefault(filter) : !isActive(filter));

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
    private readonly datetime: DateTimeSettings | null;
    private popover: HTMLElement | null = null;
    /** Unmount functions of React islands in the popover. */
    private popoverCleanup: (() => void)[] = [];
    /** Strings of the date picker, loaded with the first date editor. */
    private pickerStrings: PickerStrings | null = null;
    private ownReload = false;
    private coreFilters: HTMLElement | null = null;

    constructor(root: HTMLElement, strings: Strings) {
        this.root = root;
        this.reportId = Number(root.dataset.reportId);
        this.parameters = root.dataset.reportParameters ?? '[]';
        this.filters = JSON.parse(root.dataset.filters ?? '[]') as Filter[];
        for (const filter of this.filters) {
            filter.label = decodeEntities(filter.label);
            if (filter.anylabel !== undefined) {
                filter.anylabel = decodeEntities(filter.anylabel);
            }
            for (const option of filter.options) {
                option.label = decodeEntities(option.label);
            }
        }
        this.datetime = JSON.parse(root.dataset.datetime || 'null') as DateTimeSettings | null;
        this.strings = strings;
        this.root.style.position = 'relative';
        // Core filters form reloads only the table, reload whole page so that the bar shows current values.
        document.addEventListener(TABLE_RELOAD, (event) => {
            if (!this.ownReload && event.target instanceof Node && this.getReport()?.contains(event.target)) {
                window.location.reload();
            }
        });
        document.addEventListener('click', (event) => {
            // The path is kept when the clicked element was removed, for example the date picker apply button.
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
        if (filter.type === 'date') {
            filter.value = '1';
            filter.from = 0;
            filter.to = 0;
            filter.fromtext = '';
            filter.totext = '';
        }
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
            const active = isActive(filter);
            if (!active && !filter.pinned) {
                if (filter.type !== 'other') {
                    inactive.push(filter);
                }
                continue;
            }
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';
            group.dataset.filterKey = filter.key;

            // Pinned filters stay in the bar with the "any" text when they do not filter.
            const edit = this.button(
                buttonLabel(filter, this.strings, this.datetime),
                active ? 'btn btn-primary' : 'btn btn-outline-secondary',
            );
            edit.addEventListener('click', () => this.openEditor(filter, group));
            group.append(edit);

            if (active) {
                const remove = this.button('×', 'btn btn-primary');
                remove.setAttribute('aria-label', this.strings.remove + ': ' + filter.label);
                remove.title = this.strings.remove;
                remove.addEventListener('click', () => {
                    this.clear(filter);
                    void this.save();
                });
                group.append(remove);
            }

            this.root.append(group);
        }

        if (inactive.length) {
            const more = this.button('+ ' + this.strings.add, 'btn btn-sm btn-outline-secondary');
            more.addEventListener('click', () => this.openMore(inactive, more));
            this.root.append(more);
        }

        // One click back to the initial state: default values, nothing else.
        if (!this.filters.every(isInitial)) {
            const reset = this.button('↺ ' + this.strings.resetall, 'btn btn-sm btn-link');
            reset.addEventListener('click', () => {
                for (const filter of this.filters) {
                    this.clear(filter);
                    Object.assign(filter, filter.default);
                }
                void this.save();
            });
            this.root.append(reset);
        }

        // Core filters form for anything the bar cannot do.
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
            // The date picker closes itself only.
            if (event.key === 'Escape' && !event.defaultPrevented) {
                this.closePopover();
                anchor.focus();
            }
        });
        popover.append(content);
        this.root.append(popover);
        this.popover = popover;
        popover.querySelector<HTMLElement>('input, select, button')?.focus();
    }

    /** Close open popover. */
    private closePopover(): void {
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
        const advanced = this.coreFilters?.querySelector<HTMLElement>('#dropdownFiltersButton');
        if (filter.type === 'other' && filter.pinned && advanced) {
            // Only the core form can edit the filter, wait until this click is over so that it does not close the dropdown.
            this.closePopover();
            setTimeout(() => advanced.click(), 0);
            return;
        }
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
        } else if (filter.type === 'date') {
            content.append(this.dateEditor(filter));
        } else if (filter.type === 'boolean') {
            const group = document.createElement('div');
            group.className = 'd-flex gap-2';
            // Filters limited to some of the values export them as options.
            const choices: [number, string][] = filter.options.length
                ? filter.options.map((option) => [Number(option.value), option.label])
                : [[BOOLEAN_YES, this.strings.yes], [BOOLEAN_NO, this.strings.no]];
            for (const [operator, text] of choices) {
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

        if (filter.default && !isDefault(filter)) {
            const reset = this.button(this.strings.resetdefault, 'btn btn-sm btn-link px-0 mt-2');
            reset.addEventListener('click', () => {
                delete filter.operatorlabel;
                Object.assign(filter, filter.default);
                void this.save();
            });
            content.append(reset);
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
    private label(text: string, input: HTMLElement, hidden = false): HTMLLabelElement {
        const label = document.createElement('label');
        label.className = hidden ? 'visually-hidden' : 'form-label small mb-1';
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
    private select(id: string, options: FilterOption[], value: string): HTMLSelectElement {
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
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
    private dateEditor(filter: Filter): HTMLFormElement {
        const strings = this.strings;
        const id = this.root.id;
        const form = document.createElement('form');
        form.noValidate = true;

        // Operators of new filters: range is the most common, unless the filter does not allow it.
        const active = isActive(filter);
        const operators = filter.options;
        let operator = String(filter.operator);
        if (!active || !operators.some((option) => option.value === operator)) {
            const range = operators.find((option) => option.value === String(DATE_RANGE));
            operator = (range ?? operators[0])?.value ?? '';
        }
        const operatorSelect = this.select(id + '-operator', operators, operator);
        operatorSelect.classList.add('mb-2');
        form.append(this.label(fill(strings.fieldoperator, filter.label), operatorSelect, true), operatorSelect);

        // Number of units for relative operators.
        const relative = document.createElement('div');
        relative.className = 'd-flex gap-2 mb-2';
        const valueInput = document.createElement('input');
        valueInput.type = 'number';
        valueInput.className = 'form-control form-control-sm';
        valueInput.style.width = '5rem';
        valueInput.id = id + '-value';
        valueInput.value = active && filter.value !== '' ? filter.value : '1';
        const unitSelect = this.select(id + '-unit', this.datetime?.units ?? [], String(filter.unit ?? DATE_UNIT_DAY));
        relative.append(
            this.label(fill(strings.fieldvalue, filter.label), valueInput, true), valueInput,
            this.label(fill(strings.fieldunit, filter.label), unitSelect, true), unitSelect,
        );
        form.append(relative);

        // Date range in the timezone of the user.
        const range = document.createElement('div');
        const from = this.dateInput(range, id + '-from', strings.datefrom, filter.from ?? 0, filter.fromtext ?? '', 0, 0);
        const to = this.dateInput(range, id + '-to', strings.dateto, filter.to ?? 0, filter.totext ?? '', 23, 59);
        if (this.datetime?.timezonelabel) {
            const timezone = document.createElement('div');
            timezone.className = 'form-text text-muted mb-2';
            timezone.textContent = this.datetime.timezonelabel;
            range.append(timezone);
        }
        form.append(range);

        const update = (): void => {
            const current = Number(operatorSelect.value);
            relative.hidden = !DATE_RELATIVE.includes(current) && current !== DATE_CURRENT;
            valueInput.hidden = current === DATE_CURRENT;
            // Core ignores zero units in "last" and "next" only.
            valueInput.min = (current === DATE_LAST || current === DATE_NEXT) ? '1' : '0';
            range.hidden = current !== DATE_RANGE;
        };
        operatorSelect.addEventListener('change', update);
        update();

        const apply = this.button(strings.apply, 'btn btn-sm btn-primary');
        apply.type = 'submit';
        form.append(apply);

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            void (async(): Promise<void> => {
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
                    if (valueInput.value.trim() === '' || !Number.isInteger(value) || value < Number(valueInput.min)) {
                        valueInput.classList.add('is-invalid');
                        valueInput.focus();
                        return;
                    }
                    filter.value = String(value);
                }
                filter.operator = current;
                filter.unit = Number(unitSelect.value);
                delete filter.operatorlabel;
                if (!isActive(filter)) {
                    // Empty range removes the filter.
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
    private dateInput(container: HTMLElement, id: string, label: string, timestamp: number, text: string, hour: number,
            minute: number): {normalise: () => Promise<Answer | null>; setError: (message: string) => void} {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-2';
        const row = document.createElement('div');
        row.className = 'd-flex gap-1';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm';
        input.id = id;
        input.autocomplete = 'off';
        input.value = timestamp ? text : '';
        input.dataset.muformDatetimeTimezone = this.datetime?.timezone ?? '';
        input.dataset.muformDatetimeLang = this.datetime?.lang ?? '';
        input.dataset.muformDatetimeDefaulthour = String(hour);
        input.dataset.muformDatetimeDefaultminute = String(minute);
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.id = id + '-error';
        input.setAttribute('aria-describedby', feedback.id);
        const picker = document.createElement('span');
        row.append(input, picker);
        wrapper.append(this.label(label, input), row, feedback);
        container.append(wrapper);

        const setError = (message: string): void => {
            input.classList.toggle('is-invalid', message !== '');
            feedback.textContent = message;
            // Bootstrap shows feedback of invalid inputs that are siblings only.
            feedback.classList.toggle('d-block', message !== '');
        };
        const apply = async(value: string): Promise<Answer | null> => {
            const answer = await normalise(input, value);
            if (!answer.valid) {
                setError(this.strings.invalid);
                return null;
            }
            input.value = answer.text;
            setError('');
            return answer;
        };
        input.addEventListener('change', () => void apply(input.value));
        if (timestamp) {
            // The picker opens at the current date, the server tells its parts.
            void normalise(input, String(timestamp));
        }
        void this.mountPicker(picker, input, async(value) => {
            await apply(value);
        });
        return {normalise: () => apply(input.value), setError};
    }

    /**
     * Mount date picker next to a date input.
     *
     * @param span
     * @param input
     * @param apply
     */
    private async mountPicker(span: HTMLElement, input: HTMLInputElement, apply: (text: string) => Promise<void>): Promise<void> {
        if (!this.pickerStrings) {
            const [pick, today, clear, applytext, hour, minute, monthprev, monthnext, yearprev, yearnext] = await getStrings(
                ['pickdatetime', 'today', 'clear', 'apply', 'hour', 'minute', 'monthprev', 'monthnext', 'yearprev', 'yearnext']
                    .map((key) => ({key: 'muform_' + key, component: 'tool_mulib'}))
            );
            this.pickerStrings = {pick, today, clear, apply: applytext, hour, minute, monthprev, monthnext, yearprev, yearnext};
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
            apply,
        }, {id: span.id || input.id + '-picker'}));
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
            this.getReport()?.dispatchEvent(new CustomEvent(FILTERS_CHANGED, {
                bubbles: true,
                detail: {count: this.filters.filter(isActive).length},
            }));
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
        const [
            add, advanced, remove, apply, useadvanced, resetdefault, resetall, rangefrom, rangeto, search, yes, no,
            datefrom, dateto, fieldoperator, fieldvalue, fieldunit, invalid, invalidrange,
        ] = await getStrings([
            {key: 'filterbar_add', component: 'tool_mulib'},
            {key: 'filterbar_advanced', component: 'tool_mulib'},
            {key: 'filterbar_remove', component: 'tool_mulib'},
            {key: 'filterbar_apply', component: 'tool_mulib'},
            {key: 'filterbar_useadvanced', component: 'tool_mulib'},
            {key: 'filterbar_resetdefault', component: 'tool_mulib'},
            {key: 'filterbar_resetall', component: 'tool_mulib'},
            {key: 'filterbar_rangefrom', component: 'tool_mulib'},
            {key: 'filterbar_rangeto', component: 'tool_mulib'},
            {key: 'search', component: 'core'},
            {key: 'yes', component: 'core'},
            {key: 'no', component: 'core'},
            {key: 'filterdatefrom', component: 'core_reportbuilder'},
            {key: 'filterdateto', component: 'core_reportbuilder'},
            {key: 'filterfieldoperator', component: 'core_reportbuilder'},
            {key: 'filterfieldvalue', component: 'core_reportbuilder'},
            {key: 'filterfieldunit', component: 'core_reportbuilder'},
            {key: 'muform_datetimeinvalid', component: 'tool_mulib'},
            {key: 'muform_datetimerangeinvalid', component: 'tool_mulib'},
        ]);
        new FilterBar(root, {
            add, advanced, remove, apply, useadvanced, resetdefault, resetall, search, yes, no, rangefrom, rangeto,
            datefrom, dateto, fieldoperator, fieldvalue, fieldunit, invalid, invalidrange,
        });
    } finally {
        pending.resolve();
    }
};
