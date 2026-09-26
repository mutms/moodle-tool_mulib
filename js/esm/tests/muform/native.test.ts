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

import NativeElement from '../../src/muform/native';
import type {ChangeDetail} from '../../src/muform/types';
import {CHECKBOX, CHECKBOXES, MULTISELECT, NUMBER, RADIOS, SELECT, TEXT, fakeForm, form} from './fixtures';

/**
 * Create element for a fixture.
 *
 * @param html fixture
 * @param name element name
 * @returns element and its wrapper
 */
function make(html: string, name: string): {element: NativeElement; wrapper: HTMLElement} {
    const formel = form(html);
    const wrapper = formel.querySelector<HTMLElement>(`[data-muform-name="${name}"]`)!;
    return {element: new NativeElement(wrapper, fakeForm(formel)), wrapper};
}

describe('tool_mulib/muform/native', () => {
    it('reads values of all native control kinds', () => {
        expect(make(TEXT, 'name').element.getValue()).toBe('Jane');
        expect(make(NUMBER, 'count').element.getValue()).toBe('');
        expect(make(CHECKBOX, 'enabled').element.getValue()).toBe('0');
        expect(make(RADIOS, 'color').element.getValue()).toBe('green');
        expect(make(CHECKBOXES, 'roles').element.getValue()).toEqual(['a', 'c']);
        expect(make(SELECT, 'country').element.getValue()).toBe('cz');
        expect(make(MULTISELECT, 'langs').element.getValue()).toEqual(['en', 'de']);

        const {element, wrapper} = make(CHECKBOX, 'enabled');
        wrapper.querySelector<HTMLInputElement>('input[type="checkbox"]')!.checked = true;
        expect(element.getValue()).toBe('1');
    });

    it('validates native constraints with hints', () => {
        const {element, wrapper} = make(NUMBER, 'count');
        expect(element.validate()).toEqual(['Required']);
        const input = wrapper.querySelector<HTMLInputElement>('input')!;
        input.value = 'x9';
        expect(element.validate()).toEqual(['Error']);
        input.value = '3';
        expect(element.validate()).toEqual([]);

        input.value = '';
        element.state.hidden = true;
        expect(element.validate()).toEqual([]);
        element.state.hidden = false;
        element.state.disabled = true;
        expect(element.validate()).toEqual([]);
    });

    it('projects disabled state and errors', () => {
        const {element, wrapper} = make(CHECKBOXES, 'roles');
        element.state.disabled = true;
        element.state.errors = ['One', 'Two'];
        element.syncUI();
        const inputs = wrapper.querySelectorAll<HTMLInputElement>('input');
        expect(Array.from(inputs).every((input) => input.disabled)).toBe(true);
        const checkbox = wrapper.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
        expect(checkbox.classList.contains('is-invalid')).toBe(true);
        expect(checkbox.getAttribute('aria-invalid')).toBe('true');
        const area = wrapper.querySelector<HTMLElement>('.invalid-feedback')!;
        expect(area.innerHTML).toBe('<div>One</div><div>Two</div>');
        expect(area.style.display).toBe('block');

        element.state.disabled = false;
        element.state.errors = [];
        element.syncUI();
        expect(Array.from(inputs).some((input) => input.disabled)).toBe(false);
        expect(checkbox.classList.contains('is-invalid')).toBe(false);
        expect(checkbox.hasAttribute('aria-invalid')).toBe(false);
        expect(area.innerHTML).toBe('');
        expect(area.style.display).toBe('');
    });

    it('emits change and clears errors on input', () => {
        const {element, wrapper} = make(TEXT, 'name');
        const details: ChangeDetail[] = [];
        wrapper.parentElement!.addEventListener('muform:change', (event) => {
            details.push((event as CustomEvent<ChangeDetail>).detail);
        });
        element.state.errors = ['Bad'];
        element.syncUI();
        const input = wrapper.querySelector<HTMLInputElement>('input')!;
        input.value = 'Joe';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        expect(details).toEqual([{name: 'name', value: 'Joe'}]);
        expect(element.state.errors).toEqual([]);
        expect(input.classList.contains('is-invalid')).toBe(false);
    });

    it('focuses the checked radio', () => {
        const {element, wrapper} = make(RADIOS, 'color');
        element.focus();
        expect(document.activeElement).toBe(wrapper.querySelector('#id_color_1'));
    });
});
