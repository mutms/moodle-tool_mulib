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

import FilemanagerElement from '../../src/muform/element/filemanager';
import {fakeForm, form, wrap} from './fixtures';

/** Widget markup as rendered by element/filemanager.mustache, reduced to what matters. */
const FILEMANAGER = wrap('attachments', 'filemanager',
    '<div class="muform-filemanager w-100" data-muform-filemanager><div class="filemanager">'
    + '<input type="checkbox" class="fm-select"><button type="button" class="fp-btn">Add</button></div></div>'
    + '<input type="hidden" id="id_attachments" name="attachments" value="123">');

describe('tool_mulib/muform/element/filemanager', () => {
    it('reports the draft id and leaves the widget controls alone', () => {
        const formel = form(FILEMANAGER);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="attachments"]')!;
        const element = new FilemanagerElement(wrapper, fakeForm(formel));
        expect(element.getValue()).toBe('123');
        expect(element.validate()).toEqual([]);

        element.state.errors = ['Too many'];
        element.state.disabled = true;
        element.syncUI();
        const widget = wrapper.querySelector<HTMLElement>('[data-muform-filemanager]')!;
        expect(widget.classList.contains('muform-filemanager-disabled')).toBe(true);
        expect(widget.getAttribute('aria-disabled')).toBe('true');
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!.disabled).toBe(true);
        expect(wrapper.querySelector<HTMLInputElement>('.fm-select')!.disabled).toBe(false);
        expect(wrapper.querySelector('.fp-btn')!.classList.contains('is-invalid')).toBe(false);
        expect(wrapper.querySelector<HTMLElement>('.invalid-feedback')!.textContent).toContain('Too many');

        element.state.disabled = false;
        element.syncUI();
        expect(widget.classList.contains('muform-filemanager-disabled')).toBe(false);
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!.disabled).toBe(false);
    });

    it('has no value when frozen', () => {
        const formel = form(wrap('attachments', 'filemanager', '<ul><li><a href="#">a.txt</a></li></ul>'));
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="attachments"]')!;
        expect(new FilemanagerElement(wrapper, fakeForm(formel)).getValue()).toBeNull();
    });
});
