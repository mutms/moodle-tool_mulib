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

import {initForm, setModuleLoader} from '../../src/muform/form';
import NativeElement from '../../src/muform/native';
import {TEXT, form} from './fixtures';

const mockChangechecker = {
    watchForm: jest.fn(),
    markFormSubmitted: jest.fn(),
    markFormAsDirty: jest.fn(),
    resetFormDirtyState: jest.fn(),
};

setModuleLoader(async() => ({'default': NativeElement}));

/**
 * Form inside the wizard layout with a stepper link.
 *
 * @param attrs extra form attributes
 * @returns the form
 */
function wizardForm(attrs: string): HTMLFormElement {
    const formel = form(TEXT, [], attrs);
    document.body.innerHTML = '<div class="muform-wizard"><nav data-muform-wizard-nav>'
        + '<a href="#stage1" id="stage1">Stage 1</a><span id="current">Stage 2</span></nav>'
        + '<div class="muform-wizard-form">' + formel.outerHTML + '</div></div>';
    return document.querySelector('form')!;
}

/**
 * Click a node with optional modifier keys.
 *
 * @param id element id
 * @param init mouse event options
 */
function click(id: string, init: MouseEventInit = {}): void {
    const link = document.getElementById(id)!;
    // Navigation is not implemented in jsdom, the default action is irrelevant here.
    link.addEventListener('click', (event) => event.preventDefault(), {once: true});
    link.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true, button: 0, ...init}));
}

beforeEach(() => {
    jest.clearAllMocks();
    mockAmdModule('core_form/changechecker', mockChangechecker);
});

describe('tool_mulib/muform/form dirty state', () => {
    it('marks forms rendered dirty and on request', async() => {
        const clean = await initForm(form(TEXT));
        expect(mockChangechecker.watchForm).toHaveBeenCalledWith(clean.form);
        expect(mockChangechecker.markFormAsDirty).not.toHaveBeenCalled();
        clean.markDirty();
        expect(mockChangechecker.markFormAsDirty).toHaveBeenCalledWith(clean.form);
        expect(clean.form.dataset.muformDirty).toBe('1');

        jest.clearAllMocks();
        const dirty = await initForm(form(TEXT, [], ' data-muform-dirty="1"'));
        expect(mockChangechecker.markFormAsDirty).toHaveBeenCalledWith(dirty.form);
    });

    it('moves between wizard stages without asking unless the current stage changed', async() => {
        const muform = await initForm(wizardForm(' data-muform-dirty="1"'));
        expect(mockChangechecker.markFormAsDirty).toHaveBeenCalledWith(muform.form);

        // Other tabs and clicks outside links keep the state.
        click('stage1', {ctrlKey: true});
        click('stage1', {button: 1});
        click('current');
        expect(mockChangechecker.resetFormDirtyState).not.toHaveBeenCalled();

        click('stage1');
        expect(mockChangechecker.resetFormDirtyState).toHaveBeenCalledWith(muform.form);

        jest.clearAllMocks();
        const changed = await initForm(wizardForm(' data-muform-dirty="1"'));
        const input = changed.form.querySelector<HTMLInputElement>('#id_name')!;
        input.value = 'John';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        click('stage1');
        expect(mockChangechecker.resetFormDirtyState).not.toHaveBeenCalled();
    });
});
