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

import {getForm, initForm, setModuleLoader} from '../../src/muform/form';
import Element from '../../src/muform/element';
import NativeElement from '../../src/muform/native';
import {CHECKBOX, NUMBER, TEXT, button, form, section} from './fixtures';

setModuleLoader(async(specifier: string) => {
    const type = specifier.split('/').pop();
    return {'default': (type === 'section' || type === 'buttons') ? Element : NativeElement};
});

/**
 * Submit the form as if a button was pressed.
 *
 * @param formel the form
 * @param buttonId id of the submitter
 * @returns true when the default action was prevented
 */
function press(formel: HTMLFormElement, buttonId: string): boolean {
    const submitter = document.getElementById(buttonId) as HTMLButtonElement;
    const event = new SubmitEvent('submit', {bubbles: true, cancelable: true, submitter});
    return !formel.dispatchEvent(event);
}

/** All elements and buttons used by the tests. */
const ELEMENTS = CHECKBOX + TEXT + NUMBER
    + section('buttons', button('submit', 'submit') + button('refresh', 'reload') + button('cancel', 'cancel'));

describe('tool_mulib/muform/form', () => {
    it('loads element modules, applies rules and resolves pending', async() => {
        const rules = [{action: 'hide', target: 'name', dep: 'enabled', op: 'notchecked', value: null}];
        const formel = form(ELEMENTS, rules);
        const muform = await initForm(formel);
        expect(await initForm(formel)).toBe(muform);
        expect(getForm(formel)).toBe(muform);
        expect(Array.from(muform.elements.keys())).toEqual(['enabled', 'name', 'count', 'buttons', 'submit', 'refresh', 'cancel']);
        expect(muform.getValue('name')).toBe('Jane');
        expect(muform.getValue('xyz')).toBeNull();
        expect(pendingStack).toContain('tool_mulib/muform:init');
        expect(completeStack).toContain('tool_mulib/muform:init');
        expect(formel.querySelector<HTMLElement>('[data-muform-name="name"]')!.hidden).toBe(true);

        const displayed: unknown[] = [];
        muform.on('display', (detail) => displayed.push(detail));
        const enabled = formel.querySelector<HTMLInputElement>('#id_enabled')!;
        enabled.checked = true;
        enabled.dispatchEvent(new Event('change', {bubbles: true}));
        expect(formel.querySelector<HTMLElement>('[data-muform-name="name"]')!.hidden).toBe(false);
        expect(displayed).toEqual([{changed: ['name']}]);
    });

    it('blocks invalid submission, focuses the error and validates on blur afterwards', async() => {
        const formel = form(ELEMENTS);
        const muform = await initForm(formel);
        const invalid: unknown[] = [];
        muform.on('invalid', (detail) => invalid.push(detail));

        expect(press(formel, 'id_submit')).toBe(true);
        expect(invalid).toHaveLength(1);
        const count = formel.querySelector<HTMLInputElement>('#id_count')!;
        expect(count.classList.contains('is-invalid')).toBe(true);
        expect(formel.querySelector('#id_error_count')!.textContent).toBe('Required');
        expect(document.activeElement).toBe(count);
        expect(formel.querySelector<HTMLButtonElement>('#id_submit')!.disabled).toBe(false);

        count.value = '3';
        count.dispatchEvent(new Event('input', {bubbles: true}));
        expect(count.classList.contains('is-invalid')).toBe(false);
        count.value = '';
        count.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));
        expect(formel.querySelector('#id_error_count')!.textContent).toBe('Required');
    });

    it('lets valid submissions through once and skips validation for reload and cancel', async() => {
        jest.useFakeTimers();
        for (const role of ['refresh', 'cancel']) {
            const formel = form(ELEMENTS);
            const muform = await initForm(formel);
            const submitted: unknown[] = [];
            muform.on('submit', (detail) => submitted.push(detail));
            expect(press(formel, `id_${role}`)).toBe(false);
            expect(submitted).toHaveLength(1);
            expect(formel.querySelector('#id_error_count')!.textContent).toBe('');
        }

        const formel = form(ELEMENTS);
        const muform = await initForm(formel);
        const submitted: unknown[] = [];
        muform.on('submit', (detail) => submitted.push(detail));
        formel.querySelector<HTMLInputElement>('#id_count')!.value = '2';
        expect(press(formel, 'id_submit')).toBe(false);
        expect(submitted).toHaveLength(1);
        // Double click before the buttons get disabled is refused synchronously.
        expect(press(formel, 'id_submit')).toBe(true);
        expect(press(formel, 'id_refresh')).toBe(true);
        expect(submitted).toHaveLength(1);
        jest.runAllTimers();
        expect(formel.querySelector<HTMLButtonElement>('#id_submit')!.disabled).toBe(true);
        expect(formel.querySelector<HTMLButtonElement>('#id_cancel')!.disabled).toBe(true);
        jest.useRealTimers();
    });

    it('validates downloads but keeps the form usable', async() => {
        jest.useFakeTimers();
        const download = button('download', 'submit').replace('>', ' formtarget="_blank" data-muform-download="1">');
        const formel = form(CHECKBOX + TEXT + NUMBER + section('buttons', download + button('cancel', 'cancel')));
        const muform = await initForm(formel);
        const submitted: unknown[] = [];
        muform.on('submit', (detail) => submitted.push(detail));
        expect(press(formel, 'id_download')).toBe(true);
        expect(formel.querySelector('#id_error_count')!.textContent).toBe('Required');

        formel.querySelector<HTMLInputElement>('#id_count')!.value = '2';
        expect(press(formel, 'id_download')).toBe(false);
        expect(press(formel, 'id_download')).toBe(false);
        jest.runAllTimers();
        expect(submitted).toHaveLength(0);
        expect(formel.querySelector<HTMLButtonElement>('#id_download')!.disabled).toBe(false);
        expect(formel.querySelector<HTMLButtonElement>('#id_cancel')!.disabled).toBe(false);
        jest.useRealTimers();
    });

    it('focuses the first server side error on init', async() => {
        const formel = form(ELEMENTS, [], ' data-muform-has-errors="1"');
        formel.querySelector('#id_error_name')!.textContent = 'Taken';
        await initForm(formel);
        expect(document.activeElement).toBe(formel.querySelector('#id_name'));
    });

    it('submits through the submit button when Enter is pressed in an input', async() => {
        // A reload button before the submit button would be the browser default for Enter.
        const formel = form(section('rows', button('delete', 'reload')) + ELEMENTS);
        await initForm(formel);
        const clicked: string[] = [];
        for (const btn of formel.querySelectorAll<HTMLButtonElement>('button')) {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                clicked.push(btn.name);
            });
        }
        const input = formel.querySelector<HTMLInputElement>('#id_name')!;
        const enter = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
        input.dispatchEvent(enter);
        expect(enter.defaultPrevented).toBe(true);
        expect(clicked).toEqual(['submit']);

        // Handled Enter keys and other keys are left alone.
        const handled = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
        handled.preventDefault();
        input.dispatchEvent(handled);
        input.dispatchEvent(new KeyboardEvent('keydown', {key: 'a', bubbles: true, cancelable: true}));
        expect(clicked).toEqual(['submit']);
    });

    it('leaves Enter to the browser when the submit button comes first', async() => {
        const formel = form(ELEMENTS);
        await initForm(formel);
        const enter = new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true});
        formel.querySelector<HTMLInputElement>('#id_name')!.dispatchEvent(enter);
        expect(enter.defaultPrevented).toBe(false);
    });

    it('presses buttons by role', async() => {
        const formel = form(ELEMENTS);
        const muform = await initForm(formel);
        const clicked: string[] = [];
        for (const btn of formel.querySelectorAll<HTMLButtonElement>('button')) {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                clicked.push(btn.name);
            });
        }
        muform.submit();
        muform.reload();
        muform.cancel();
        expect(clicked).toEqual(['submit', 'refresh', 'cancel']);
    });
});
