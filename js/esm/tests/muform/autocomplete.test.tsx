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
import SingleElement from '../../src/muform/element/autocomplete';
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
 * Markup as rendered by element/autocomplete.mustache.
 *
 * @param selected selected JSON
 * @param key value
 * @param required required input
 * @returns html
 */
function fixture(selected: string, key: string, required = false): string {
    return wrap('owner', 'autocomplete',
        '<div class="muform-autocomplete w-100" data-muform-autocomplete data-muform-autocomplete-url="/api/one"'
        + ` data-muform-autocomplete-source='{"class":"tool_mulib\\\\muform\\\\autocomplete\\\\site_user","args":[1]}'`
        + ` data-muform-autocomplete-selected='${selected}'>`
        + `<input type="text" id="id_owner" name="owner" value="${key}" placeholder="Find a user"${required ? ' required' : ''}>`
        + '</div>');
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
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="owner"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    let element!: SingleElement;
    await act(async() => {
        element = new SingleElement(wrapper, fakeForm(formel));
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
        expect(action).toBe('muform/autocomplete');
        bodies.push(body);
        const answer = answers.shift() ?? {list: [], overflow: false, maxitems: 50};
        return {json: async() => answer};
    });
    for (const [key, text] of Object.entries({
        'muform_noresults': 'No results', 'muform_searching': 'Searching', 'muform_toomanyresults': 'Too many',
        'muform_clearselection': 'Clear',
    })) {
        mockString(key, 'tool_mulib', text);
    }
});

describe('tool_mulib/muform/element/autocomplete', () => {
    it('shows the selected label and a refusal', async() => {
        const {element, wrapper} = await make(fixture('[{"value":"3","label":"<b>Anna</b>","error":"Suspended user"}]', '3'));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        expect(input.classList.contains('d-none')).toBe(true);
        const shown = wrapper.querySelector<HTMLElement>('[data-muform-autocomplete-chosen]')!;
        expect(shown.innerHTML).toBe('<b>Anna</b>');
        expect(shown.classList.contains('is-invalid')).toBe(true);
        expect(shown.classList.contains('muform-width-medium')).toBe(false);
        expect(wrapper.querySelector('.text-danger')!.textContent).toBe('Suspended user');
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="owner"]')!.value).toBe('3');
        expect(element.getValue()).toBe('3');
        expect(wrapper.querySelector('button[aria-label="Clear"]')).not.toBeNull();
    });

    it('searches, picks and clears', async() => {
        const {element, wrapper, changes} = await make(fixture('[]', '', true));
        expect(element.validate()).toEqual(['Required']);
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;

        answers.push({list: [{value: '7', label: '<i>Cara</i>'}], overflow: false, maxitems: 50});
        await act(async() => {
            input.focus();
            type(input, 'ca');
        });
        await settle(300);
        expect(bodies.at(-1)).toEqual({source: 'tool_mulib\\muform\\autocomplete\\site_user', args: [1], query: 'ca'});
        const option = wrapper.querySelector<HTMLElement>('[data-muform-autocomplete-option="7"]')!;
        await act(async() => {
            option.click();
        });
        await settle();
        expect(element.getValue()).toBe('7');
        expect(input.classList.contains('d-none')).toBe(true);
        expect(wrapper.querySelector<HTMLElement>('[data-muform-autocomplete-chosen]')!.innerHTML).toBe('<i>Cara</i>');
        expect(changes.at(-1)).toEqual({name: 'owner', value: '7'});
        expect(element.validate()).toEqual([]);
        expect(wrapper.querySelector('[role="listbox"]')).toBeNull();

        await act(async() => {
            wrapper.querySelector<HTMLButtonElement>('button[aria-label="Clear"]')!.click();
        });
        await settle();
        expect(element.getValue()).toBeNull();
        expect(changes.at(-1)).toEqual({name: 'owner', value: null});
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="owner"]')!.value).toBe('');
        expect(wrapper.querySelector('[data-muform-autocomplete-chosen]')).toBeNull();
        expect(input.classList.contains('d-none')).toBe(false);
    });

    it('shows overflow', async() => {
        const {wrapper} = await make(fixture('[]', ''));
        const input = wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!;
        answers.push({list: null, overflow: true, maxitems: 50});
        await act(async() => {
            type(input, 'over');
        });
        await settle(300);
        expect(wrapper.querySelector('[role="listbox"]')!.textContent).toContain('Too many');
    });

    it('remounts disabled', async() => {
        const {element, wrapper} = await make(fixture('[{"value":"3","label":"Anna","error":null}]', '3'));
        await act(async() => {
            element.state.disabled = true;
            element.syncUI();
        });
        await settle();
        expect(wrapper.querySelector<HTMLInputElement>('input[role="combobox"]')!.disabled).toBe(true);
        expect(wrapper.querySelector('button[aria-label="Clear"]')).toBeNull();
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"][name="owner"]')!.disabled).toBe(true);
    });
});
