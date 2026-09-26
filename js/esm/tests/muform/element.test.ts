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
});
