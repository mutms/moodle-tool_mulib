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

import SharedkeyElement from '../../src/muform/element/sharedkey';
import type {ChangeDetail} from '../../src/muform/types';
import {fakeForm, form, wrap} from './fixtures';

/**
 * Shared key input as rendered by element/sharedkey.mustache.
 *
 * @param current current key in the data attribute
 * @param clearable render the clear checkbox
 * @returns html
 */
function fixture(current: string, clearable: boolean): string {
    return wrap('enrolkey', 'sharedkey',
        '<div class="muform-sharedkey d-flex"><input type="text" class="form-control muform-sharedkey-masked" id="id_enrolkey"'
        + ` name="enrolkey[value]" value="" autocomplete="off" placeholder="••••••••" data-muform-sharedkey-current="${current}">`
        + '<span data-muform-sharedkey-actions></span></div>'
        + (clearable ? '<div class="form-check"><input type="checkbox" class="form-check-input" id="id_enrolkey_clear"'
            + ' name="enrolkey[clear]" value="1"></div>' : ''));
}

/**
 * Wait for queued promises.
 */
async function settle(): Promise<void> {
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

/**
 * Create the element.
 *
 * @param current current key
 * @param clearable render the clear checkbox
 * @returns element, input, toggle button, checkbox and change events
 */
async function make(current: string, clearable: boolean) {
    const formel = form(fixture(current, clearable));
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="enrolkey"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    const element = new SharedkeyElement(wrapper, fakeForm(formel));
    await settle();
    return {
        element,
        changes,
        input: wrapper.querySelector<HTMLInputElement>('input[type="text"]')!,
        button: wrapper.querySelector<HTMLButtonElement>('button')!,
        clear: wrapper.querySelector<HTMLInputElement>('input[type="checkbox"]'),
    };
}

beforeEach(() => {
    mockString('muform_show', 'tool_mulib', 'Show');
    mockString('muform_hide', 'tool_mulib', 'Hide');
    mockString('muform_typenewvalue', 'tool_mulib', 'Type new value');
});

describe('tool_mulib/muform/element/sharedkey', () => {
    it('reveals the current key when nothing was typed', async() => {
        const {element, input, button, changes} = await make('abc', false);
        expect(button.textContent).toBe('Show');
        button.click();
        expect(input.classList.contains('muform-sharedkey-masked')).toBe(false);
        expect(input.value).toBe('abc');
        expect(element.getValue()).toBe('abc');
        expect(changes.at(-1)).toEqual({name: 'enrolkey', value: 'abc'});
        button.click();
        expect(input.classList.contains('muform-sharedkey-masked')).toBe(true);
        expect(input.value).toBe('abc');
        expect(button.textContent).toBe('Show');
    });

    it('keeps typed text when showing', async() => {
        const {input, button} = await make('abc', false);
        input.value = 'typed';
        button.click();
        expect(input.value).toBe('typed');
    });

    it('does not reveal without a current key or while clearing', async() => {
        const {input, button} = await make('', false);
        button.click();
        expect(input.value).toBe('');
        expect(input.placeholder).toBe('Type new value');
        button.click();
        expect(input.placeholder).toBe('••••••••');

        const clearing = await make('abc', true);
        clearing.button.click();
        expect(clearing.input.value).toBe('abc');
        clearing.clear!.checked = true;
        clearing.clear!.dispatchEvent(new Event('change', {bubbles: true}));
        expect(clearing.input.disabled).toBe(true);
        expect(clearing.input.value).toBe('');
        expect(clearing.input.classList.contains('muform-sharedkey-masked')).toBe(true);
        expect(clearing.button.disabled).toBe(true);
        expect(clearing.button.textContent).toBe('Show');

        clearing.element.state.disabled = true;
        clearing.element.syncUI();
        clearing.element.state.disabled = false;
        clearing.element.syncUI();
        expect(clearing.input.disabled).toBe(true);
        clearing.clear!.checked = false;
        clearing.clear!.dispatchEvent(new Event('change', {bubbles: true}));
        expect(clearing.input.disabled).toBe(false);
    });
});
