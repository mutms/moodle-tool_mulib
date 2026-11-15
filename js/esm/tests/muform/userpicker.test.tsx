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
import Panel, {type Answer, type Strings} from '../../src/muform/userpicker/panel';
import type {PickerItem} from '../../src/muform/pickerpanel';

jest.mock('@moodle/lms/core/fetch', () => ({
    __esModule: true,
    'default': {performPost: jest.fn()},
}));

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const performPost = Fetch.performPost as jest.Mock;

const strings: Strings = {
    addselected: 'Add selected ({$a})', close: 'Close', found: 'Found: {$a}', name: 'Name', next: 'Next', noresults: 'No results',
    page: 'Page {$a}', previous: 'Previous', search: 'Search', searching: 'Searching', selectall: 'Select all', selectrow: 'Select',
    title: 'Select users',
};

/** Recorded request bodies. */
let bodies: Record<string, unknown>[] = [];

/** Picked items. */
let picked: PickerItem[][] = [];

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
    const rows = all.filter((user) => user.fullname.toLowerCase().includes(query) && !exclude.includes(user.id));
    return {columns: [{name: 'email', label: 'Email'}], rows, total: rows.length, page: 0, perpage: 50};
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

beforeEach(() => {
    bodies = [];
    picked = [];
    performPost.mockImplementation(async(component: string, action: string, {body}: {body: Record<string, unknown>}) => {
        expect(component).toBe('tool_mulib');
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

    it('shows an empty result', async() => {
        const container = await render(true);
        await type(container, 'nobody');
        expect(container.querySelector('[data-userpicker-empty]')!.textContent).toBe('No results');
        expect(container.querySelector<HTMLButtonElement>('[data-userpicker-add]')!.disabled).toBe(true);
    });
});
