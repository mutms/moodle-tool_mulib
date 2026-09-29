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

import {open, openFrom} from '../../src/muform/dialog';
import {setModuleLoader} from '../../src/muform/form';
import NativeElement from '../../src/muform/native';
import {NUMBER, TEXT, button, section} from './fixtures';

setModuleLoader(async() => ({'default': NativeElement}));

/** Form html served by the fake handler. */
const FORM = '<form id="f1" method="post" action="/x" class="muform" novalidate data-muform="f1" data-muform-rules="[]">'
    + TEXT + NUMBER + section('buttons', button('submit', 'submit') + button('cancel', 'cancel')) + '</form>';

/** Recorded fetch calls. */
let calls: {url: string; init: RequestInit}[] = [];

/** Queued answers. */
let answers: object[] = [];

beforeEach(() => {
    mockString('closebuttontitle', 'core', 'Close');
    calls = [];
    answers = [];
    document.body.innerHTML = '<button id="t" data-muform-dialog-url="/handler" data-muform-dialog-title="Edit"'
        + ' data-muform-dialog-action="nothing" data-muform-dialog-size="sm">Open</button>';
    // The jsdom library does not implement dialogs, emulate the two methods used.
    HTMLDialogElement.prototype.showModal = jest.fn(function(this: HTMLDialogElement) {
        const dialog = this; // eslint-disable-line no-invalid-this
        dialog.setAttribute('open', '');
    });
    HTMLDialogElement.prototype.close = jest.fn(function(this: HTMLDialogElement) {
        const dialog = this; // eslint-disable-line no-invalid-this
        dialog.removeAttribute('open');
        dialog.dispatchEvent(new Event('close'));
    });
    global.fetch = jest.fn(async(url: string, init: RequestInit) => {
        calls.push({url, init});
        const answer = answers.shift() ?? {status: 'cancelled'};
        return {ok: true, json: async() => answer} as Response;
    }) as unknown as typeof fetch;
});

/**
 * Wait for queued promises and timers.
 */
async function settle(): Promise<void> {
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

describe('tool_mulib/muform/dialog', () => {
    it('opens, loads the form and closes with a cancel button', async() => {
        answers.push({status: 'render', title: 'Edit item', html: FORM, javascript: ''});
        const trigger = document.getElementById('t')!;
        const closed = openFrom(trigger);
        await settle();

        const dialog = document.querySelector<HTMLDialogElement>('dialog.muform-dialog')!;
        expect(dialog.classList.contains('muform-dialog-sm')).toBe(true);
        expect(dialog.hasAttribute('open')).toBe(true);
        expect(dialog.querySelector('.modal-title')!.textContent).toBe('Edit item');
        expect(calls[0].url).toBe('/handler');
        expect((calls[0].init.headers as Record<string, string>)['X-Muform-Dialog']).toBe('1');
        expect(dialog.querySelector('form[data-muform]')).not.toBeNull();
        expect(document.activeElement).toBe(dialog.querySelector('#id_name'));

        const cancel = dialog.querySelector<HTMLButtonElement>('#id_cancel')!;
        dialog.querySelector('form')!.dispatchEvent(
            new SubmitEvent('submit', {bubbles: true, cancelable: true, submitter: cancel})
        );
        await closed;
        expect(document.querySelector('dialog')).toBeNull();
        expect(calls).toHaveLength(1);
        expect(document.activeElement).toBe(trigger);
    });

    it('focuses the checked radio of the first element', async() => {
        const radios = '<div data-muform-element="yesno" data-muform-component="tool_mulib" data-muform-name="active">'
            + '<input type="radio" id="id_active_yes" name="active" value="1">'
            + '<input type="radio" id="id_active_no" name="active" value="0" checked></div>';
        answers.push({status: 'render', title: '', html: FORM.replace('novalidate data-muform="f1" data-muform-rules="[]">',
            'novalidate data-muform="f1" data-muform-rules="[]">' + radios), javascript: ''});
        openFrom(document.getElementById('t')!);
        await settle();
        expect(document.activeElement).toBe(document.getElementById('id_active_no'));
        document.querySelector<HTMLDialogElement>('dialog')!.close();
    });

    it('posts valid submissions and dispatches the submitted event for the nothing action', async() => {
        answers.push({status: 'render', title: '', html: FORM, javascript: ''});
        const rerendered = FORM.replace('value=""', 'value="2"').replace('action="/x"', 'action="/x?step=2"');
        answers.push({status: 'render', title: '', html: rerendered, javascript: ''});
        answers.push({status: 'submitted', redirecturl: null, data: {id: 7}});
        const trigger = document.getElementById('t')!;
        const submitted: unknown[] = [];
        trigger.addEventListener('muform:dialog-submitted', (event) => submitted.push((event as CustomEvent).detail));
        const closed = openFrom(trigger);
        await settle();

        const dialog = document.querySelector<HTMLDialogElement>('dialog')!;
        expect(dialog.querySelector('.modal-title')!.textContent).toBe('Edit');
        const submit = () => dialog.querySelector('form')!.dispatchEvent(new SubmitEvent('submit', {
            bubbles: true, cancelable: true, submitter: dialog.querySelector<HTMLButtonElement>('#id_submit'),
        }));

        // Invalid: required number is empty, the orchestrator blocks it, nothing is posted.
        submit();
        await settle();
        expect(calls).toHaveLength(1);
        expect(dialog.querySelector('#id_error_count')!.textContent).toBe('Required');

        dialog.querySelector<HTMLInputElement>('#id_count')!.value = '2';
        submit();
        await settle();
        expect(calls).toHaveLength(2);
        expect(calls[1].init.method).toBe('POST');
        // Submissions go to the form action, multi-step handlers change it between steps.
        expect(calls[1].url).toBe('http://localhost/x');
        const body = calls[1].init.body as FormData;
        expect(body.get('name')).toBe('Jane');
        expect(body.get('count')).toBe('2');
        expect(body.get('submit')).toBe('1');
        // Server re-rendered the form, then a second submit succeeds.
        expect(dialog.querySelector<HTMLInputElement>('#id_count')!.value).toBe('2');
        submit();
        await settle();
        await closed;
        expect(calls).toHaveLength(3);
        expect(calls[2].url).toMatch(/\/x\?step=2$/);
        expect(submitted).toEqual([{data: {id: 7}}]);
        expect(document.querySelector('dialog')).toBeNull();
    });

    it('leaves download submissions to the browser', async() => {
        const download = button('download', 'submit').replace('>', ' formtarget="_blank" data-muform-download="1">');
        answers.push({status: 'render', title: '', html: FORM.replace('</form>', download + '</form>'), javascript: ''});
        openFrom(document.getElementById('t')!);
        await settle();

        const dialog = document.querySelector<HTMLDialogElement>('dialog')!;
        dialog.querySelector<HTMLInputElement>('#id_count')!.value = '2';
        const event = new SubmitEvent('submit', {
            bubbles: true, cancelable: true, submitter: dialog.querySelector<HTMLButtonElement>('#id_download'),
        });
        expect(dialog.querySelector('form')!.dispatchEvent(event)).toBe(true);
        await settle();
        expect(calls).toHaveLength(1);
        expect(dialog.hasAttribute('open')).toBe(true);
        dialog.close();
    });

    it('opens from a click on a trigger and reloads after submission', async() => {
        answers.push({status: 'render', title: '', html: FORM, javascript: ''});
        answers.push({status: 'submitted', redirecturl: '/done', data: []});
        expectRedirect({url: window.location.href});
        const trigger = document.getElementById('t')!;
        trigger.dataset.muformDialogAction = 'reload';
        trigger.click();
        await settle();
        const dialog = document.querySelector<HTMLDialogElement>('dialog')!;
        dialog.querySelector<HTMLInputElement>('#id_count')!.value = '1';
        dialog.querySelector('form')!.dispatchEvent(new SubmitEvent('submit', {
            bubbles: true, cancelable: true, submitter: dialog.querySelector<HTMLButtonElement>('#id_submit'),
        }));
        await settle();
        expect(document.querySelector('dialog')).toBeNull();
    });

    it('closes and reports errors on failed requests', async() => {
        global.fetch = jest.fn(async() => ({ok: false, status: 500} as Response)) as unknown as typeof fetch;
        const spy = jest.spyOn(window.console, 'error').mockImplementation(() => undefined);
        await open({url: '/handler', action: 'nothing'});
        expect(document.querySelector('dialog')).toBeNull();
        expect(spy).toHaveBeenCalled();
        spy.mockRestore();
    });
});
