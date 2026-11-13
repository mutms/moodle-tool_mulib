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

import Element from '../../src/muform/element';
import {fakeForm, form, wrap} from './fixtures';

describe('tool_mulib/muform/element', () => {
    it('reads initial state from wrapper attributes', () => {
        const formel = form(wrap('a', 'custom', '<span>x</span>', ' hidden data-muform-hidden="1" data-muform-disabled="1"'));
        const element = new Element(formel.querySelector('[data-muform-name="a"]')!, fakeForm(formel));
        expect(element.name).toBe('a');
        expect(element.state).toEqual({hidden: true, disabled: true, errors: [], touched: false});
        expect(element.getValue()).toBeNull();
        expect(element.validate()).toEqual([]);
    });

    it('reads server side errors of its own error area only', () => {
        const inner = wrap('b', 'custom', '<span>y</span>').replace('></div></div></div>', '><div>Inner</div></div></div></div>');
        const html = wrap('a', 'custom', inner).replace(
            'id="id_error_a"></div>',
            'id="id_error_a" style="display: block;"><div>Taken</div><div> Too long </div></div>'
        );
        const formel = form(html);
        const outer = new Element(formel.querySelector('[data-muform-name="a"]')!, fakeForm(formel));
        expect(outer.state.errors).toEqual(['Taken', 'Too long']);
        const element = new Element(formel.querySelector('[data-muform-name="b"]')!, fakeForm(formel));
        expect(element.state.errors).toEqual(['Inner']);

        const single = form(wrap('c', 'custom', '<span>z</span>').replace(
            'id="id_error_c"></div>',
            'id="id_error_c"><div>Taken</div><div> Too long </div></div>'
        ));
        expect(new Element(single.querySelector('[data-muform-name="c"]')!, fakeForm(single)).state.errors)
            .toEqual(['Taken', 'Too long']);
    });

    it('syncUI writes only wrapper attributes', () => {
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a">'));
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        const element = new Element(wrapper, fakeForm(formel));

        element.state.hidden = true;
        element.state.disabled = true;
        element.state.errors = ['Bad'];
        element.syncUI();
        expect(wrapper.hidden).toBe(true);
        expect(wrapper.dataset.muformHidden).toBe('1');
        expect(wrapper.dataset.muformDisabled).toBe('1');
        expect(wrapper.querySelector('input')!.disabled).toBe(false);
        expect(wrapper.querySelector('.invalid-feedback')!.textContent).toBe('');

        element.state.hidden = false;
        element.state.disabled = false;
        element.syncUI();
        element.syncUI();
        expect(wrapper.hidden).toBe(false);
        expect(wrapper.dataset.muformHidden).toBeUndefined();
        expect(wrapper.dataset.muformDisabled).toBeUndefined();
    });

    it('focuses the first focusable descendant and reports focusout', () => {
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a"><button type="button">b</button>'));
        const api = fakeForm(formel);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        const element = new Element(wrapper, api);
        element.focus();
        expect(document.activeElement).toBe(wrapper.querySelector('input'));
        wrapper.querySelector('input')!.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));
        expect(element.state.touched).toBe(true);
        expect(api.touchedNames).toEqual(['a']);
    });

    it('validates elements left with a click after the mouse is released', () => {
        // Forget presses of other tests.
        document.dispatchEvent(new KeyboardEvent('keydown', {bubbles: true, key: 'Shift'}));
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a">'));
        const api = fakeForm(formel);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        const element = new Element(wrapper, api);
        const input = wrapper.querySelector('input')!;

        // Also the element focused automatically when the form opened, an error now would move the click target.
        document.body.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
        input.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));
        expect(element.state.touched).toBe(true);
        expect(api.touchedNames).toEqual([]);
        jest.useFakeTimers();
        document.body.dispatchEvent(new MouseEvent('pointerup', {bubbles: true}));
        expect(api.touchedNames).toEqual([]);
        document.body.dispatchEvent(new MouseEvent('click', {bubbles: true}));
        jest.runAllTimers();
        jest.useRealTimers();
        expect(api.touchedNames).toEqual(['a']);

        // Keyboard users get errors at once.
        document.dispatchEvent(new KeyboardEvent('keydown', {bubbles: true, key: 'Tab'}));
        input.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));
        expect(api.touchedNames).toEqual(['a', 'a']);
    });

    it('validates elements left with a press that ends without a click', () => {
        document.dispatchEvent(new KeyboardEvent('keydown', {bubbles: true, key: 'Shift'}));
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a">'));
        const api = fakeForm(formel);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        new Element(wrapper, api);
        jest.useFakeTimers();
        document.body.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
        wrapper.querySelector('input')!.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));
        document.body.dispatchEvent(new MouseEvent('pointerup', {bubbles: true}));
        jest.advanceTimersByTime(499);
        expect(api.touchedNames).toEqual([]);
        jest.advanceTimersByTime(1);
        jest.useRealTimers();
        expect(api.touchedNames).toEqual(['a']);
    });

    it('does not report focusout caused by pressing a form button', () => {
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a">') + '<button type="submit"><i>b</i></button>');
        const api = fakeForm(formel);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        const element = new Element(wrapper, api);
        const input = wrapper.querySelector('input')!;
        const button = formel.querySelector('button')!;
        const icon = button.querySelector('i')!;
        const blur = (relatedTarget: EventTarget | null = null) =>
            input.dispatchEvent(new FocusEvent('focusout', {bubbles: true, relatedTarget}));

        // Chrome focuses the pressed button.
        blur(button);
        expect(element.state.touched).toBe(true);
        expect(api.touchedNames).toEqual([]);

        // Safari keeps buttons unfocused, the press is the only hint.
        icon.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
        blur(formel);
        expect(api.touchedNames).toEqual([]);

        // Mobile Safari moves focus after the touch ended, before the click.
        icon.dispatchEvent(new MouseEvent('pointerup', {bubbles: true}));
        blur();
        expect(api.touchedNames).toEqual([]);

        // The press is forgotten after the click.
        formel.addEventListener('submit', (event) => event.preventDefault());
        jest.useFakeTimers();
        icon.dispatchEvent(new MouseEvent('click', {bubbles: true}));
        jest.runAllTimers();
        jest.useRealTimers();
        blur();
        expect(api.touchedNames).toEqual(['a']);
    });

    it('forgets presses that do not end with a click', () => {
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a">') + '<button type="submit">b</button>');
        const api = fakeForm(formel);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        new Element(wrapper, api);
        const input = wrapper.querySelector('input')!;
        const button = formel.querySelector('button')!;
        const blur = () => input.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));

        // Dragged off the button.
        button.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
        document.body.dispatchEvent(new MouseEvent('pointerup', {bubbles: true}));
        blur();
        expect(api.touchedNames).toEqual(['a']);

        // Cancelled gesture, keyboard navigation and leaving the window.
        for (const end of [
            () => document.dispatchEvent(new Event('pointercancel', {bubbles: true})),
            () => document.dispatchEvent(new KeyboardEvent('keydown', {bubbles: true, key: 'Tab'})),
            () => window.dispatchEvent(new Event('blur')),
        ]) {
            button.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
            end();
            blur();
        }
        expect(api.touchedNames).toEqual(['a', 'a', 'a', 'a']);
    });

    it('ignores a pressed button of another form when focus moves to a button of this form', () => {
        const formel = form(wrap('a', 'custom', '<input id="id_a" name="a">') + '<button type="submit">b</button>');
        const other = document.createElement('form');
        other.innerHTML = '<button type="button">x</button>';
        document.body.appendChild(other);
        const api = fakeForm(formel);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="a"]')!;
        new Element(wrapper, api);
        const input = wrapper.querySelector('input')!;

        other.querySelector('button')!.dispatchEvent(new MouseEvent('pointerdown', {bubbles: true}));
        input.dispatchEvent(new FocusEvent('focusout', {bubbles: true, relatedTarget: formel.querySelector('button')}));
        expect(api.touchedNames).toEqual([]);
        document.dispatchEvent(new Event('pointercancel', {bubbles: true}));
        other.remove();
    });

});
