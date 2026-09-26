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

import SecretElement from '../../src/muform/element/secret';
import {fakeForm, form, wrap} from './fixtures';

/**
 * Secret input as rendered by element/secret.mustache.
 *
 * @param clearable render the clear checkbox
 * @returns html
 */
function fixture(clearable: boolean): string {
    return wrap('dbsecret', 'secret',
        '<div class="muform-secret d-flex"><input type="text" class="form-control muform-secret-masked" id="id_dbsecret"'
        + ' name="dbsecret[value]" value="" autocomplete="off" placeholder="••••••••">'
        + '<span data-muform-secret-actions></span></div>'
        + (clearable ? '<div class="form-check"><input type="checkbox" class="form-check-input" id="id_dbsecret_clear"'
            + ' name="dbsecret[clear]" value="1"></div>' : ''));
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
 * @param clearable render the clear checkbox
 * @returns element, wrapper, input, toggle button and checkbox
 */
async function make(clearable: boolean) {
    const formel = form(fixture(clearable));
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="dbsecret"]')!;
    const element = new SecretElement(wrapper, fakeForm(formel));
    await settle();
    return {
        element,
        wrapper,
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

describe('tool_mulib/muform/element/secret', () => {
    it('toggles masking of the typed text', async() => {
        const {element, input, button} = await make(false);
        expect(button.textContent).toBe('Show');
        expect(button.getAttribute('aria-controls')).toBe('id_dbsecret');
        expect(input.placeholder).toBe('••••••••');
        button.click();
        expect(input.classList.contains('muform-secret-masked')).toBe(false);
        expect(button.textContent).toBe('Hide');
        expect(button.getAttribute('aria-describedby')).toBe('id_dbsecret_label');
        expect(input.placeholder).toBe('Type new value');
        input.value = 'typed';
        button.click();
        expect(input.classList.contains('muform-secret-masked')).toBe(true);
        expect(button.textContent).toBe('Show');
        expect(input.placeholder).toBe('••••••••');
        expect(input.value).toBe('typed');
        expect(element.getValue()).toBe('typed');
    });

    it('disables the input while clearing and keeps that through syncUI', async() => {
        const {element, input, clear, button} = await make(true);
        input.value = 'typed';
        button.click();
        expect(input.classList.contains('muform-secret-masked')).toBe(false);
        clear!.checked = true;
        clear!.dispatchEvent(new Event('change', {bubbles: true}));
        expect(input.disabled).toBe(true);
        expect(input.value).toBe('');
        expect(input.placeholder).toBe('');
        expect(input.classList.contains('muform-secret-masked')).toBe(true);
        expect(button.textContent).toBe('Show');
        expect(button.disabled).toBe(true);

        element.state.errors = ['x'];
        element.syncUI();
        expect(input.disabled).toBe(true);

        element.state.disabled = true;
        element.syncUI();
        expect(input.disabled).toBe(true);
        expect(clear!.disabled).toBe(true);
        element.state.disabled = false;
        element.syncUI();
        expect(clear!.disabled).toBe(false);
        expect(input.disabled).toBe(true);

        clear!.checked = false;
        clear!.dispatchEvent(new Event('change', {bubbles: true}));
        expect(input.disabled).toBe(false);
        expect(button.disabled).toBe(false);
        expect(input.placeholder).toBe('••••••••');
    });

    it('does nothing for frozen elements', async() => {
        const formel = form(wrap('dbsecret', 'secret', '<div class="form-control-plaintext">••••••••</div>'));
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="dbsecret"]')!;
        const element = new SecretElement(wrapper, fakeForm(formel));
        await settle();
        expect(element.getValue()).toBeNull();
        expect(wrapper.querySelector('button')).toBeNull();
    });
});
