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
import Fetch from '@moodle/lms/core/fetch';
import TagsElement from '../../src/muform/element/tags';
import {normaliseTag} from '../../src/muform/tagspicker';
import type {ChangeDetail} from '../../src/muform/types';
import {fakeForm, form, wrap} from './fixtures';

jest.mock('@moodle/lms/core/fetch', () => ({
    __esModule: true,
    'default': {performPost: jest.fn()},
}));

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const performPost = Fetch.performPost as jest.Mock;

/** Queued endpoint answers. */
let answers: object[] = [];

/** Elements to destroy after each test. */
let mounted: {destroy(): void}[] = [];

/**
 * Markup as rendered by element/tags.mustache.
 *
 * @param selected selected JSON
 * @param names comma separated names
 * @param standardonly only standard tags allowed
 * @returns html
 */
function fixture(selected: string, names: string, standardonly = false): string {
    return wrap('topics', 'tags',
        '<div class="muform-tags w-100" data-muform-tags data-muform-tags-url="/api/tags"'
        + ` data-muform-tags-area='{"class":"tool_mulib\\\\muform\\\\tagarea\\\\course","args":[2]}'`
        + ` data-muform-tags-selected='${selected}' data-muform-tags-suggest="1"`
        + `${standardonly ? ' data-muform-tags-standardonly="1"' : ''}>`
        + `<input type="text" id="id_topics" name="topics" value="${names}" placeholder="Enter tags..."></div>`);
}

/**
 * Type into a React controlled input.
 *
 * @param input the input
 * @param text new value
 */
function type(input: HTMLInputElement, text: string): void {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
    setter.call(input, text);
    input.dispatchEvent(new Event('input', {bubbles: true}));
}

/**
 * Wait for queued promises and timers.
 *
 * @param ms extra time to let the debounce fire
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
 * Create the element.
 *
 * @param html fixture
 * @returns element, wrapper and change events
 */
async function make(html: string) {
    const formel = form(html);
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="topics"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    let element!: TagsElement;
    await act(async() => {
        element = new TagsElement(wrapper, fakeForm(formel));
    });
    await settle();
    mounted.push(element);
    return {element, wrapper, changes};
}

/**
 * Press a key in the combobox.
 *
 * @param input the combobox
 * @param key key name
 */
async function press(input: HTMLInputElement, key: string): Promise<void> {
    await act(async() => {
        input.dispatchEvent(new KeyboardEvent('keydown', {key, bubbles: true}));
    });
}

afterEach(async() => {
    await act(async() => {
        mounted.forEach((element) => element.destroy());
    });
    mounted = [];
});

beforeEach(() => {
    answers = [];
    performPost.mockImplementation(async(component: string, action: string) => {
        expect(component).toBe('tool_mulib');
        expect(action).toBe('muform/tags');
        const answer = answers.shift() ?? {list: [], overflow: false, maxitems: 50};
        return {json: async() => answer};
    });
    for (const [key, text] of Object.entries({
        'muform_noresults': 'No results', 'muform_searching': 'Searching', 'muform_toomanyresults': 'Too many',
        'muform_remove': 'Remove {$a}',
    })) {
        mockString(key, 'tool_mulib', text);
    }
});

describe('tool_mulib/muform/tagspicker', () => {
    it('normalises tag names like core', () => {
        expect(normaliseTag('  Quantum \t  physics ')).toBe('Quantum physics');
        expect(normaliseTag('   ')).toBe('');
    });
});

describe('tool_mulib/muform/element/tags', () => {
    it('mounts pills and the hidden input from the server state', async() => {
        const selected = '[{"name":"Physics","error":null},{"name":"Bad","error":"Error"}]';
        const {element, wrapper} = await make(fixture(selected, 'Physics,Bad'));
        expect(wrapper.querySelector('input[type="text"][name]')).toBeNull();
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="topics"]')!.value).toBe('Physics,Bad');
        expect(element.getValue()).toEqual(['Physics', 'Bad']);
        const pills = wrapper.querySelectorAll('[data-muform-tags-pill]');
        expect(pills.length).toBe(2);
        expect(pills[1].classList.contains('text-bg-danger')).toBe(true);
    });

    it('adds typed tags on comma and Enter, ignores duplicates and removes with Backspace', async() => {
        const {element, wrapper, changes} = await make(fixture('[]', ''));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        await act(async() => type(input, 'Quantum  physics'));
        await press(input, ',');
        await act(async() => type(input, 'quantum physics'));
        await press(input, 'Enter');
        await act(async() => type(input, 'Maths'));
        await press(input, 'Enter');
        expect(element.getValue()).toEqual(['Quantum physics', 'Maths']);
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="topics"]')!.value).toBe('Quantum physics,Maths');
        await press(input, 'Backspace');
        expect(element.getValue()).toEqual(['Quantum physics']);
        expect(changes[changes.length - 1]).toEqual({name: 'topics', value: ['Quantum physics']});
    });

    it('accepts only suggestions when only standard tags may be used', async() => {
        const {element, wrapper} = await make(fixture('[]', '', true));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        answers.push({list: ['Physics'], overflow: false, maxitems: 50});
        await act(async() => type(input, 'Biology'));
        await settle(300);
        await press(input, 'Enter');
        // The active suggestion is picked, the typed text is never a tag.
        expect(element.getValue()).toEqual(['Physics']);
    });

    it('closes the suggestions on Tab and with the close button', async() => {
        const {wrapper} = await make(fixture('[]', ''));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        answers.push({list: ['Physics', 'Chemistry'], overflow: false, maxitems: 50});
        await act(async() => type(input, 'i'));
        await settle(300);
        expect(wrapper.ownerDocument.querySelectorAll('[data-muform-tags-option]').length).toBe(2);
        await press(input, 'Tab');
        expect(wrapper.ownerDocument.querySelectorAll('[data-muform-tags-option]').length).toBe(0);

        // The close button is shown only while the list is open.
        expect(wrapper.querySelector('[data-muform-tags-close]')).toBeNull();
        answers.push({list: ['Physics'], overflow: false, maxitems: 50});
        await act(async() => type(input, 'ph'));
        await settle(300);
        expect(wrapper.ownerDocument.querySelectorAll('[data-muform-tags-option]').length).toBe(1);
        await act(async() => {
            wrapper.querySelector<HTMLButtonElement>('[data-muform-tags-close]')!.click();
        });
        expect(wrapper.ownerDocument.querySelectorAll('[data-muform-tags-option]').length).toBe(0);
        // The close button discards the typed text.
        expect(input.value).toBe('');
    });

    it('discards typed text on Tab when only standard tags may be used', async() => {
        const {element, wrapper} = await make(fixture('[]', '', true));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        answers.push({list: ['Physics'], overflow: false, maxitems: 50});
        await act(async() => type(input, 'Biology'));
        await settle(300);
        await press(input, 'Tab');
        expect(input.value).toBe('');
        expect(element.getValue()).toEqual([]);
    });
});
