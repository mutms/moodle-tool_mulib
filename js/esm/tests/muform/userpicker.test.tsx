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

import {act} from 'react';
import {createRoot, type Root} from 'react-dom/client';
import Fetch from '@moodle/lms/core/fetch';
import Panel, {type Answer, type Filter, type Strings} from '../../src/muform/userpicker/panel';
import type {PickerItem} from '../../src/muform/pickerpanel';

jest.mock('@moodle/lms/core/fetch', () => ({
    __esModule: true,
    'default': {performPost: jest.fn()},
}));

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const performPost = Fetch.performPost as jest.Mock;

const strings: Strings = {
    addselected: 'Add selected ({$a})', close: 'Close', filteradd: 'Add filter', filterremove: 'Remove filter',
    filterresetall: 'Reset filters', filterresetdefault: 'Reset to default', filternoresults: 'No matches',
    filtersearching: 'Looking', filtertoomanyresults: 'Too many', found: 'Found: {$a}', name: 'Name', next: 'Next',
    noresults: 'No results',
    page: 'Page {$a}', previous: 'Previous', search: 'Search', searching: 'Searching', selectall: 'Select all', selectrow: 'Select',
    title: 'Select users',
};

/** Recorded request bodies. */
let bodies: Record<string, unknown>[] = [];

/** Picked items. */
let picked: PickerItem[][] = [];

/** Filters returned by the endpoint, without values. */
let filters: Omit<Filter, 'value'>[] = [];

/** Recorded request bodies of the autocomplete endpoint. */
let lookups: Record<string, unknown>[] = [];

/** Mounted root. */
let root: Root | null = null;

/**
 * Answer with users matching the query.
 *
 * @param body request body
 * @returns answer
 */
function answer(body: Record<string, unknown>): Answer {
    const all = [
        {id: 2, fullname: 'Anna Alpha', fields: {email: 'anna@example.com'}, label: 'Anna Alpha <span>anna@example.com</span>'},
        {id: 3, fullname: 'Bert Beta', fields: {email: 'bert@example.com'}, label: 'Bert Beta'},
        {id: 4, fullname: 'Cyril Gamma', fields: {email: 'cyril@example.com'}, label: 'Cyril Gamma'},
    ];
    const query = String(body.query).toLowerCase();
    const exclude = (body.exclude as string[]).map(Number);
    const values = body.filters as Record<string, string> | undefined;
    const applied = filters.map((filter) => ({...filter, value: values ? (values[filter.key] ?? null) : filter.default}));
    const active = applied.find((filter) => filter.key === 'active')?.value ?? null;
    const letter = applied.find((filter) => filter.key === 'letter')?.value ?? null;
    const cohort = applied.find((filter) => filter.key === 'cohort');
    if (cohort && cohort.value !== null) {
        Object.assign(cohort, {valuelabel: `<b>Cohort</b> ${cohort.value}`});
    }
    const rows = all.filter((user) => user.fullname.toLowerCase().includes(query) && !exclude.includes(user.id))
        .filter((user) => active === null || (active === '1') === (user.id !== 4))
        .filter((user) => letter === null || user.fullname.startsWith(letter))
        .filter((user) => !cohort || cohort.value === null || String(user.id) === cohort.value);
    return {columns: [{name: 'email', label: 'Email'}], rows, total: rows.length, page: 0, perpage: 50, filters: applied};
}

/**
 * Wait for queued promises and timers.
 *
 * @param ms extra time to let the typing delay pass
 */
async function settle(ms = 0): Promise<void> {
    await act(async() => {
        await new Promise((resolve) => setTimeout(resolve, ms));
        for (let i = 0; i < 5; i++) {
            await new Promise((resolve) => setTimeout(resolve, 0));
        }
    });
}

/**
 * Render the panel.
 *
 * @param multiple
 * @param selected
 * @returns container
 */
async function render(multiple: boolean, selected: string[] = []): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.replaceChildren(container);
    root = createRoot(container);
    await act(async() => {
        root!.render(<Panel source={{'class': 'tool_x\\muform\\userpicker\\users', args: [5]}} multiple={multiple}
            selected={selected} strings={strings} onPick={(items) => picked.push(items)} onClose={() => undefined} />);
    });
    await settle();
    return container;
}

/**
 * Type into the search field.
 *
 * @param container
 * @param text
 */
async function type(container: HTMLElement, text: string): Promise<void> {
    const input = container.querySelector<HTMLInputElement>('[data-userpicker-search]')!;
    await act(async() => {
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
        setter.call(input, text);
        input.dispatchEvent(new Event('input', {bubbles: true}));
    });
    await settle(300);
}

/**
 * Press a key in the search field.
 *
 * @param container
 * @param key
 */
async function press(container: HTMLElement, key: string): Promise<void> {
    const input = container.querySelector<HTMLInputElement>('[data-userpicker-search]')!;
    await act(async() => {
        input.dispatchEvent(new KeyboardEvent('keydown', {key, bubbles: true}));
    });
    await settle();
}

/**
 * Click an element of the panel.
 *
 * @param container
 * @param selector
 */
async function click(container: HTMLElement, selector: string): Promise<void> {
    await act(async() => {
        container.querySelector<HTMLElement>(selector)!.click();
    });
    await settle();
}

/**
 * Texts of the filter pills and buttons.
 *
 * @param container
 * @returns texts
 */
function bar(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[data-picker-filters] > :not([data-picker-filter-popover])'))
        .map((element) => element.textContent ?? '');
}

beforeEach(() => {
    filters = [];
    bodies = [];
    picked = [];
    lookups = [];
    performPost.mockImplementation(async(component: string, action: string, {body}: {body: Record<string, unknown>}) => {
        expect(component).toBe('tool_mulib');
        if (action === 'muform/autocomplete') {
            lookups.push(body);
            const query = String(body.query);
            if (query === 'many') {
                return {json: async() => ({list: null, overflow: true, maxitems: 50})};
            }
            const list = [{value: '2', label: '<b>Cohort</b> 2'}, {value: '3', label: '<b>Cohort</b> 3'}]
                .filter((item) => item.label.includes(query));
            return {json: async() => ({list, overflow: false, maxitems: 50})};
        }
        expect(action).toBe('muform/userpicker/search');
        bodies.push(body);
        return {json: async() => answer(body)};
    });
});

afterEach(async() => {
    await act(async() => root?.unmount());
    root = null;
});

describe('tool_mulib/muform/userpicker/panel', () => {
    it('lists users with identity columns and picks one', async() => {
        const container = await render(false, ['4']);
        expect(bodies[0]).toEqual({source: 'tool_x\\muform\\userpicker\\users', args: [5], query: '', page: 0, exclude: ['4']});
        expect(Array.from(container.querySelectorAll('thead th')).map((th) => th.textContent)).toEqual(['Name', 'Email']);
        expect(container.querySelectorAll('[data-userpicker-row]')).toHaveLength(2);
        expect(container.querySelector('[data-userpicker-total]')!.textContent).toBe('Found: 2');

        await act(async() => {
            container.querySelector<HTMLElement>('[data-userpicker-row="3"]')!.click();
        });
        expect(picked).toEqual([[{value: '3', label: 'Bert Beta'}]]);
    });

    it('picks the first found user with Enter', async() => {
        const container = await render(false);
        await type(container, 'anna');
        expect(bodies[bodies.length - 1].query).toBe('anna');
        await press(container, 'Enter');
        expect(picked).toEqual([[{value: '2', label: 'Anna Alpha <span>anna@example.com</span>'}]]);
    });

    it('ticks several users', async() => {
        const container = await render(true);
        await type(container, 'bert');
        await press(container, 'Enter');
        expect(container.querySelector<HTMLInputElement>('[data-userpicker-search]')!.value).toBe('');
        await settle();
        await act(async() => {
            container.querySelector<HTMLElement>('[data-userpicker-row="4"]')!.click();
        });
        expect(container.querySelector('[data-userpicker-add]')!.textContent).toBe('Add selected (2)');
        await press(container, 'Enter');
        expect(picked).toEqual([[{value: '3', label: 'Bert Beta'}, {value: '4', label: 'Cyril Gamma'}]]);
    });

    it('has no filters when the source has none', async() => {
        const container = await render(true);
        expect(container.querySelector('[data-picker-filters]')).toBeNull();
    });

    it('filters users with pills', async() => {
        const yesno = [{value: '1', label: 'Yes'}, {value: '0', label: 'No'}];
        filters = [
            {key: 'active', type: 'boolean', label: 'Active', options: yesno, 'default': '1', pinned: true, anylabel: 'Any'},
            {key: 'letter', type: 'select', label: 'Letter', 'default': null,
                options: [{value: 'A', label: 'A names'}, {value: 'B', label: 'B names'}]},
        ];
        const container = await render(true);
        // Default values are applied by the endpoint.
        expect(bodies[0]).not.toHaveProperty('filters');
        expect(bar(container)).toEqual(['Active: Yes×', '+ Add filter']);
        expect(container.querySelectorAll('[data-userpicker-row]')).toHaveLength(2);
        expect(container.querySelector('[data-picker-filter="active"] .btn')!.className).toBe('btn btn-primary');
        expect(container.querySelector('[data-picker-filter-remove]')!.getAttribute('aria-label'))
            .toBe('Remove filter: Active');

        await click(container, '[data-picker-filter-remove]');
        expect(bodies[bodies.length - 1].filters).toEqual({});
        // Pinned filters stay in the bar without a value.
        expect(bar(container)).toEqual(['Active: Any', '+ Add filter', '↺ Reset filters']);
        expect(container.querySelector('[data-picker-filter="active"] .btn')!.className).toBe('btn btn-outline-secondary');
        expect(container.querySelector('[data-picker-filter-remove]')).toBeNull();
        expect(container.querySelectorAll('[data-userpicker-row]')).toHaveLength(3);

        await click(container, '[data-picker-filter-add]');
        expect(Array.from(container.querySelectorAll('[data-picker-filter-choice]')).map((item) => item.textContent))
            .toEqual(['Letter']);
        await click(container, '[data-picker-filter-choice="letter"]');
        await click(container, '[data-picker-filter-option="B"]');
        expect(bodies[bodies.length - 1].filters).toEqual({letter: 'B'});
        expect(bodies[bodies.length - 1].page).toBe(0);
        expect(container.querySelector('[data-picker-filter-popover]')).toBeNull();
        expect(bar(container)).toEqual(['Active: Any', 'Letter: B names×', '↺ Reset filters']);
        expect(container.querySelectorAll('[data-userpicker-row]')).toHaveLength(1);

        await click(container, '[data-picker-filter="active"] .btn');
        await click(container, '[data-picker-filter-option="0"]');
        expect(bodies[bodies.length - 1].filters).toEqual({active: '0', letter: 'B'});
        expect(bar(container)).toEqual(['Active: No×', 'Letter: B names×', '↺ Reset filters']);
        expect(container.querySelectorAll('[data-userpicker-row]')).toHaveLength(0);

        // The editor of a filter offers its default value.
        await click(container, '[data-picker-filter="active"] .btn');
        const popover = container.querySelector('[data-picker-filter-popover]')!;
        expect(popover.querySelector('[data-picker-filter-option="0"]')!.className).toBe('btn btn-sm btn-primary');
        expect(popover.querySelector('.btn-link')!.textContent).toBe('Reset to default');
        await act(async() => {
            popover.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
        });
        expect(container.querySelector('[data-picker-filter-popover]')).toBeNull();

        await click(container, '[data-picker-filter-reset]');
        expect(bodies[bodies.length - 1].filters).toEqual({active: '1'});
        expect(bar(container)).toEqual(['Active: Yes×', '+ Add filter']);
        expect(container.querySelectorAll('[data-userpicker-row]')).toHaveLength(2);
    });

    it('searches values of autocomplete filters', async() => {
        const source = {'class': 'tool_x\\muform\\autocomplete\\cohorts', args: [1]};
        filters = [{key: 'cohort', type: 'autocomplete', label: 'Cohort', options: [], 'default': null, source}];
        const container = await render(true);
        expect(bar(container)).toEqual(['+ Add filter']);

        await click(container, '[data-picker-filter-add]');
        await click(container, '[data-picker-filter-choice="cohort"]');
        expect(lookups).toEqual([{source: 'tool_x\\muform\\autocomplete\\cohorts', args: [1], query: '', exclude: []}]);
        const options = (): string[] => Array.from(container.querySelectorAll('[data-picker-filter-option]'))
            .map((item) => item.textContent ?? '');
        expect(options()).toEqual(['Cohort 2', 'Cohort 3']);
        expect(container.querySelector('[data-picker-filter-message]')).toBeNull();

        const search = async(text: string): Promise<void> => {
            const input = container.querySelector<HTMLInputElement>('[data-picker-filter-search]')!;
            await act(async() => {
                Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(input, text);
                input.dispatchEvent(new Event('input', {bubbles: true}));
            });
            await settle(300);
        };
        await search('many');
        expect(options()).toEqual([]);
        expect(container.querySelector('[data-picker-filter-message]')!.textContent).toBe('Too many');
        await search('nothing');
        expect(container.querySelector('[data-picker-filter-message]')!.textContent).toBe('No matches');
        await search('3');
        expect(lookups[lookups.length - 1].query).toBe('3');
        expect(options()).toEqual(['Cohort 3']);

        await click(container, '[data-picker-filter-option="3"]');
        expect(bodies[bodies.length - 1].filters).toEqual({cohort: '3'});
        expect(bar(container)).toEqual(['Cohort: Cohort 3×', '↺ Reset filters']);
        const rows = Array.from(container.querySelectorAll('[data-userpicker-row]'));
        expect(rows.map((row) => row.getAttribute('data-userpicker-row'))).toEqual(['3']);

        await click(container, '[data-picker-filter-remove]');
        expect(bodies[bodies.length - 1].filters).toEqual({});
        expect(bar(container)).toEqual(['+ Add filter']);
    });

    it('shows an empty result', async() => {
        const container = await render(true);
        await type(container, 'nobody');
        expect(container.querySelector('[data-userpicker-empty]')!.textContent).toBe('No results');
        expect(container.querySelector<HTMLButtonElement>('[data-userpicker-add]')!.disabled).toBe(true);
    });
});
