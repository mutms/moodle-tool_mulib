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
import ManyElement from '../../src/muform/element/autocompletemany';
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

/** Recorded endpoint bodies. */
let bodies: object[] = [];

/** Elements to destroy after each test, pending searches must not leak into the next test. */
let mounted: {destroy(): void}[] = [];

/**
 * Markup as rendered by element/autocompletemany.mustache.
 *
 * @param selected selected JSON
 * @param keys comma separated values
 * @param required required input
 * @returns html
 */
function fixture(selected: string, keys: string, required = false): string {
    return wrap('members', 'autocompletemany',
        '<div class="muform-autocompletemany w-100" data-muform-autocompletemany data-muform-autocomplete-url="/api/many"'
        + ` data-muform-autocomplete-source='{"class":"tool_mulib\\\\muform\\\\autocompletemany\\\\site_users","args":[1]}'`
        + ` data-muform-autocomplete-selected='${selected}'>`
        + `<input type="text" id="id_members" name="members" value="${keys}" placeholder="Find users"`
        + `${required ? ' required' : ''}>`
        + '<ul data-muform-autocomplete-labels><li>Anna</li></ul></div>');
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
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="members"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    let element!: ManyElement;
    await act(async() => {
        element = new ManyElement(wrapper, fakeForm(formel));
    });
    await settle();
    mounted.push(element);
    return {element, wrapper, changes};
}

afterEach(async() => {
    await act(async() => {
        mounted.forEach((element) => element.destroy());
    });
    mounted = [];
});

beforeEach(() => {
    answers = [];
    bodies = [];
    performPost.mockImplementation(async(component: string, action: string, {body}: {body: object}) => {
        expect(component).toBe('tool_mulib');
        expect(action).toBe('muform/autocompletemany');
        bodies.push(body);
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

describe('tool_mulib/muform/element/autocompletemany', () => {
    it('mounts pills and the hidden input from the server state', async() => {
        const selected = '[{"value":"3","label":"<b>Anna</b>","error":null},{"value":"5","label":"Bert","error":"Suspended user"}]';
        const {element, wrapper} = await make(fixture(selected, '3,5'));
        expect(wrapper.querySelector('input[type="text"][name]')).toBeNull();
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="members"]')!.value).toBe('3,5');
        expect(element.getValue()).toEqual(['3', '5']);
        const pills = wrapper.querySelectorAll('[data-muform-autocomplete-pill]');
        expect(pills.length).toBe(2);
        expect(pills[0].innerHTML).toContain('<b>Anna</b>');
        expect(pills[1].classList.contains('text-bg-danger')).toBe(true);
        expect(pills[1].getAttribute('title')).toBe('Suspended user');
        expect(wrapper.querySelector('button[aria-label="Remove Anna"]')).not.toBeNull();
        expect(element.validate()).toEqual([]);
    });

    it('searches, picks, excludes and removes', async() => {
        const {element, wrapper, changes} = await make(fixture('[]', '', true));
        expect(element.validate()).toEqual(['Required']);
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;

        answers.push({list: [{value: '7', label: '<i>Cara</i>'}, {value: '8', label: 'Dan'}], overflow: false, maxitems: 50});
        await act(async() => {
            input.focus();
        });
        await settle(300);
        expect(bodies.at(-1)).toEqual({
            source: 'tool_mulib\\muform\\autocompletemany\\site_users', args: [1], query: '', exclude: [],
        });
        const options = wrapper.querySelectorAll('[data-muform-autocomplete-option]');
        expect(options.length).toBe(2);

        answers.push({list: [{value: '8', label: 'Dan'}], overflow: false, maxitems: 50});
        await act(async() => {
            (options[0] as HTMLElement).click();
        });
        await settle(300);
        expect(element.getValue()).toEqual(['7']);
        expect(changes.at(-1)).toEqual({name: 'members', value: ['7']});
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="members"]')!.value).toBe('7');
        // The list closes after a pick, the next search excludes the picked value.
        expect(wrapper.querySelectorAll('[data-muform-autocomplete-option]').length).toBe(0);
        await act(async() => type(input, 'D'));
        await settle(300);
        expect(bodies.at(-1)).toMatchObject({query: 'D', exclude: ['7']});
        expect(element.validate()).toEqual([]);

        await act(async() => {
            wrapper.querySelector<HTMLButtonElement>('button[aria-label="Remove Cara"]')!.click();
        });
        await settle();
        expect(element.getValue()).toEqual([]);
        expect(changes.at(-1)).toEqual({name: 'members', value: []});
    });

    it('shows overflow and no results', async() => {
        const {wrapper} = await make(fixture('[]', ''));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        answers.push({list: null, overflow: true, maxitems: 50});
        await act(async() => {
            type(input, 'over');
        });
        await settle(300);
        expect(wrapper.querySelector('[role="listbox"]')!.textContent).toContain('Too many');

        answers.push({list: [], overflow: false, maxitems: 50});
        await act(async() => {
            type(input, 'zz');
        });
        await settle(300);
        expect(bodies.at(-1)).toMatchObject({query: 'zz'});
        expect(wrapper.querySelector('[role="listbox"]')!.textContent).toContain('No results');
    });

    it('discards typed text on close and Tab', async() => {
        const {element, wrapper} = await make(fixture('[]', ''));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        answers.push({list: [{value: '8', label: 'Dan'}], overflow: false, maxitems: 50});
        await act(async() => type(input, 'Da'));
        await settle(300);
        expect(wrapper.querySelector('[role="listbox"]')).not.toBeNull();
        await act(async() => {
            wrapper.querySelector<HTMLButtonElement>('[data-muform-autocomplete-close]')!.click();
        });
        await settle();
        expect(input.value).toBe('');
        expect(wrapper.querySelector('[role="listbox"]')).toBeNull();

        await act(async() => type(input, 'Da'));
        await settle(300);
        await act(async() => {
            input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Tab', bubbles: true}));
        });
        await settle();
        expect(input.value).toBe('');
        expect(wrapper.querySelector('[role="listbox"]')).toBeNull();

        await act(async() => type(input, 'Da'));
        await settle(300);
        await act(async() => {
            document.body.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
            document.body.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
            document.body.dispatchEvent(new MouseEvent('click', {bubbles: true}));
        });
        await settle();
        expect(input.value).toBe('');
        expect(wrapper.querySelector('[role="listbox"]')).toBeNull();
        expect(element.getValue()).toEqual([]);
    });

    it('focuses the search only when the last pill is removed', async() => {
        const selected = '[{"value":"3","label":"Anna","error":null},{"value":"5","label":"Bert","error":null}]';
        const {element, wrapper} = await make(fixture(selected, '3,5'));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        await act(async() => {
            wrapper.querySelector<HTMLButtonElement>('button[aria-label="Remove Anna"]')!.click();
        });
        await settle();
        expect(element.getValue()).toEqual(['5']);
        expect(document.activeElement).toBe(wrapper.querySelector('button[aria-label="Remove Bert"]'));
        expect(wrapper.querySelector('[role="listbox"]')).toBeNull();

        await act(async() => {
            wrapper.querySelector<HTMLButtonElement>('button[aria-label="Remove Bert"]')!.click();
        });
        await settle();
        expect(element.getValue()).toEqual([]);
        expect(document.activeElement).toBe(input);
    });

    it('remounts disabled and unmounts', async() => {
        const {element, wrapper} = await make(fixture('[{"value":"3","label":"Anna","error":null}]', '3'));
        await act(async() => {
            element.state.disabled = true;
            element.syncUI();
        });
        await settle();
        expect(wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!.disabled).toBe(true);
        expect(wrapper.querySelector('button[aria-label="Remove Anna"]')).toBeNull();
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="members"]')!.disabled).toBe(true);
        await act(async() => {
            element.destroy();
        });
        await settle();
        expect(wrapper.querySelector('input[role="combobox"]')).toBeNull();
    });
});
