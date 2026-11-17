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
import Panel, {type Answer, type Strings} from '../../src/muform/cohortpicker/panel';
import type {PickerItem} from '../../src/muform/pickerpanel';

jest.mock('@moodle/lms/core/fetch', () => ({
    __esModule: true,
    'default': {performPost: jest.fn()},
}));

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const performPost = Fetch.performPost as jest.Mock;

const strings: Strings = {
    addselected: 'Add selected ({$a})', close: 'Close', found: 'Found: {$a}', noresults: 'No results', overflow: 'Too many',
    search: 'Search', searching: 'Searching', selectrow: 'Select', title: 'Select cohorts',
};

const nodes = [
    {id: 1, name: 'System', depth: 0, parentid: null},
    {id: 10, name: 'Faculty', depth: 1, parentid: 1},
    {id: 11, name: 'Department', depth: 2, parentid: 10},
];

// Ordered by name as the endpoint does, not by the tree.
const cohorts = [
    {id: 5, name: 'Dept students', idnumber: 'D1', nodeid: 11, label: 'Dept <b>students</b>'},
    {id: 6, name: 'Faculty staff', idnumber: '', nodeid: 10, label: 'Faculty staff'},
    {id: 7, name: 'Site students', idnumber: 'S1', nodeid: 1, label: 'Site students'},
];

/** Recorded request bodies. */
let bodies: Record<string, unknown>[] = [];

/** Picked items. */
let picked: PickerItem[][] = [];

/** Mounted root. */
let root: Root | null = null;

/**
 * Answer with cohorts matching the query and the nodes leading to them.
 *
 * @param body request body
 * @returns answer
 */
function answer(body: Record<string, unknown>): Answer {
    const query = String(body.query).toLowerCase();
    const exclude = (body.exclude as string[]).map(Number);
    const found = cohorts.filter((cohort) => cohort.name.toLowerCase().includes(query) && !exclude.includes(cohort.id));
    const needed = new Set<number>();
    for (const cohort of found) {
        let node = nodes.find((item) => item.id === cohort.nodeid);
        while (node) {
            needed.add(node.id);
            const parentid = node.parentid;
            node = nodes.find((item) => item.id === parentid);
        }
    }
    return {nodes: nodes.filter((node) => needed.has(node.id)), cohorts: found, total: found.length, overflow: query === 's'};
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
        root!.render(<Panel source={{'class': 'tool_x\\muform\\cohortpicker\\cohorts', args: [5]}} multiple={multiple}
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
    const input = container.querySelector<HTMLInputElement>('[data-cohortpicker-search]')!;
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
    const input = container.querySelector<HTMLInputElement>('[data-cohortpicker-search]')!;
    await act(async() => {
        input.dispatchEvent(new KeyboardEvent('keydown', {key, bubbles: true}));
    });
    await settle();
}

/**
 * Node names and cohort names in the order they are shown.
 *
 * @param container
 * @returns texts, cohorts prefixed with a dash
 */
function tree(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[data-cohortpicker-node-name], [data-cohortpicker-row]')).map((element) => (
        element.hasAttribute('data-cohortpicker-row')
            ? '- ' + element.getAttribute('data-cohortpicker-name')
            : element.getAttribute('data-cohortpicker-node-name') ?? ''
    ));
}

beforeEach(() => {
    bodies = [];
    picked = [];
    performPost.mockImplementation(async(component: string, action: string, {body}: {body: Record<string, unknown>}) => {
        expect(component).toBe('tool_mulib');
        expect(action).toBe('muform/cohortpicker/search');
        bodies.push(body);
        return {json: async() => answer(body)};
    });
});

afterEach(async() => {
    await act(async() => root?.unmount());
    root = null;
});

describe('tool_mulib/muform/cohortpicker/panel', () => {
    it('shows cohorts in the tree and picks one', async() => {
        const container = await render(false, ['9']);
        expect(bodies[0]).toEqual({source: 'tool_x\\muform\\cohortpicker\\cohorts', args: [5], query: '', exclude: ['9']});
        expect(tree(container))
            .toEqual(['System', '- Site students', 'Faculty', '- Faculty staff', 'Department', '- Dept students']);
        expect(container.querySelector('[data-cohortpicker-total]')!.textContent).toBe('Found: 3');
        expect(container.querySelector('[data-cohortpicker-overflow]')).toBeNull();
        expect(container.querySelector('[data-cohortpicker-row="5"]')!.textContent).toBe('Dept studentsD1');
        const depths = Array.from(container.querySelectorAll('[data-cohortpicker-depth]'))
            .map((element) => element.getAttribute('data-cohortpicker-depth'));
        expect(depths).toEqual(['0', '1', '2']);

        await act(async() => {
            container.querySelector<HTMLElement>('[data-cohortpicker-row="5"]')!.click();
        });
        expect(picked).toEqual([[{value: '5', label: 'Dept <b>students</b>'}]]);
    });

    it('keeps found cohorts in their branch and picks with the keyboard', async() => {
        const container = await render(false);
        await type(container, 'students');
        expect(bodies[bodies.length - 1].query).toBe('students');
        expect(tree(container)).toEqual(['System', '- Site students', 'Faculty', 'Department', '- Dept students']);
        // The highlighted rows follow the tree, not the order of the answer.
        await press(container, 'ArrowDown');
        await press(container, 'Enter');
        expect(picked).toEqual([[{value: '5', label: 'Dept <b>students</b>'}]]);
    });

    it('ticks several cohorts', async() => {
        const container = await render(true);
        await type(container, 'faculty');
        await press(container, 'Enter');
        expect(container.querySelector<HTMLInputElement>('[data-cohortpicker-search]')!.value).toBe('');
        await settle();
        await act(async() => {
            container.querySelector<HTMLElement>('[data-cohortpicker-row="7"]')!.click();
        });
        expect(container.querySelector('[data-cohortpicker-add]')!.textContent).toBe('Add selected (2)');
        await press(container, 'Enter');
        expect(picked).toEqual([[{value: '6', label: 'Faculty staff'}, {value: '7', label: 'Site students'}]]);
    });

    it('shows overflow and an empty result', async() => {
        const container = await render(true);
        await type(container, 's');
        expect(container.querySelector('[data-cohortpicker-overflow]')!.textContent).toBe('Too many');
        await type(container, 'nobody');
        expect(tree(container)).toEqual([]);
        expect(container.querySelector('[data-cohortpicker-empty]')!.textContent).toBe('No results');
        expect(container.querySelector<HTMLButtonElement>('[data-cohortpicker-add]')!.disabled).toBe(true);
    });
});
