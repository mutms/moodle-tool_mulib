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

import EditorElement from '../../src/muform/element/editor';
import {fakeForm, form, wrap} from './fixtures';

/** Fake Tiny instance. */
const instance = {
    content: '<p>from tiny</p>',
    modes: [] as string[],
    save: jest.fn(),
    mode: {set: jest.fn((mode: string) => instance.modes.push(mode))},
};

/** Editor markup as rendered by element/editor.mustache. */
const EDITOR = wrap('description', 'editor',
    '<div class="muform-editor"><textarea id="id_description" name="description[text]" required>old</textarea>'
    + '<div class="tox"><button type="button">Bold</button><input type="text"></div>'
    + '<input type="hidden" name="description[format]" value="1">'
    + '<input type="hidden" name="description[itemid]" value="55"></div>');

/**
 * Wait for queued promises.
 */
async function settle(): Promise<void> {
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

/**
 * Make the fake instance write into the textarea of the wrapper.
 *
 * @param wrapper element wrapper
 */
function bind(wrapper: HTMLElement): void {
    mockAmdModule('editor_tiny/editor', {
        getInstanceForElement: (element: Element) => (element.tagName === 'TEXTAREA' ? instance : null),
    });
    instance.modes = [];
    instance.save.mockImplementation(() => {
        wrapper.querySelector<HTMLTextAreaElement>('textarea')!.value = instance.content;
    });
}

describe('tool_mulib/muform/element/editor', () => {
    it('saves the editor before reading, validating and submitting', async() => {
        const formel = form(EDITOR);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="description"]')!;
        const api = fakeForm(formel);
        const handlers: (() => void)[] = [];
        api.on = jest.fn((event: string, handler: (detail: unknown) => void) => {
            if (event === 'submit') {
                handlers.push(handler as () => void);
            }
            return () => undefined;
        });
        bind(wrapper);
        const element = new EditorElement(wrapper, api);
        await settle();

        expect(element.getValue()).toBe('<p>from tiny</p>');
        expect(instance.save).toHaveBeenCalled();
        expect(element.validate()).toEqual([]);
        instance.content = '';
        expect(element.validate()).toEqual(['Required']);

        handlers.forEach((handler) => handler());
        expect(instance.save.mock.calls.length).toBeGreaterThanOrEqual(4);
    });

    it('keeps Tiny controls out of the element and mirrors disabled state', async() => {
        const formel = form(EDITOR);
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="description"]')!;
        bind(wrapper);
        const element = new EditorElement(wrapper, fakeForm(formel));
        await settle();
        expect(element.controls().length).toBe(1);

        element.state.disabled = true;
        element.state.errors = ['x'];
        element.syncUI();
        expect(wrapper.querySelector<HTMLTextAreaElement>('textarea')!.disabled).toBe(true);
        expect(wrapper.querySelector<HTMLButtonElement>('.tox button')!.disabled).toBe(false);
        expect(wrapper.querySelector<HTMLInputElement>('.tox input')!.classList.contains('is-invalid')).toBe(false);
        expect(instance.modes.at(-1)).toBe('readonly');
        element.state.disabled = false;
        element.syncUI();
        expect(instance.modes.at(-1)).toBe('design');
    });
});
