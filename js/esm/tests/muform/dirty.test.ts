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
import {NUMBER, TEXT, form} from './fixtures';

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

    it('submits the stage with Back or Continue when a step is clicked', async() => {
        const buttons = '<button type="submit" name="submit" value="1" data-muform-role="submit" data-muform-wizard="continue">'
            + 'Continue</button><button type="submit" name="back" value="1" data-muform-role="reload" formnovalidate'
            + ' data-muform-submit-if-changed="1" data-muform-wizard="back">Back</button>';
        const formel = form(TEXT + buttons);
        document.body.innerHTML = '<div class="muform-wizard"><nav data-muform-wizard-nav>'
            + '<a href="#s1" id="s1" data-muform-wizard-stage="one" data-muform-wizard-direction="back">One</a>'
            + '<a href="#s3" id="s3" data-muform-wizard-stage="three" data-muform-wizard-direction="forward">Three</a></nav>'
            + '<div class="muform-wizard-form">' + formel.outerHTML + '</div></div>';
        const muform = await initForm(document.querySelector('form')!);
        const submitted: Array<{submitter: string, value: string, target: string | null}> = [];
        muform.form.addEventListener('submit', (event) => {
            const submitter = event.submitter as HTMLButtonElement;
            const target = muform.form.querySelector<HTMLInputElement>('input[name="wizardstage"]');
            submitted.push({submitter: submitter.name, value: submitter.value, target: target?.value ?? null});
            event.preventDefault();
        });

        click('s3');
        expect(submitted).toEqual([{submitter: 'submit', value: '1', target: 'three'}]);
        expect(mockChangechecker.markFormSubmitted).toHaveBeenCalledWith(muform.form);
        expect(mockChangechecker.resetFormDirtyState).not.toHaveBeenCalled();
    });

    it('goes back from a step without validation unless the stage changed', async() => {
        const buttons = '<button type="submit" name="submit" value="1" data-muform-role="submit" data-muform-wizard="continue">'
            + 'Continue</button><button type="submit" name="back" value="1" data-muform-role="reload" formnovalidate'
            + ' data-muform-submit-if-changed="1" data-muform-wizard="back">Back</button>';
        const formel = form(NUMBER + buttons);
        document.body.innerHTML = '<div class="muform-wizard"><nav data-muform-wizard-nav>'
            + '<a href="#s1" id="s1" data-muform-wizard-stage="one" data-muform-wizard-direction="back">One</a></nav>'
            + '<div class="muform-wizard-form">' + formel.outerHTML + '</div></div>';
        const muform = await initForm(document.querySelector('form')!);
        const submitted: string[] = [];
        muform.form.addEventListener('submit', (event) => {
            submitted.push((event.submitter as HTMLButtonElement).value);
            event.preventDefault();
        });

        // The required number is empty, Back without changes does not validate.
        click('s1');
        expect(submitted).toEqual(['1']);
    });

    it('stays on an invalid stage and forgets the clicked step', async() => {
        const buttons = '<button type="submit" name="submit" value="1" data-muform-role="submit" data-muform-wizard="continue">'
            + 'Continue</button>';
        const formel = form(NUMBER + buttons);
        document.body.innerHTML = '<div class="muform-wizard"><nav data-muform-wizard-nav>'
            + '<a href="#s3" id="s3" data-muform-wizard-stage="three" data-muform-wizard-direction="forward">Three</a></nav>'
            + '<div class="muform-wizard-form">' + formel.outerHTML + '</div></div>';
        const muform = await initForm(document.querySelector('form')!);
        const submitted: string[] = [];
        muform.form.addEventListener('submit', (event) => {
            if (!event.defaultPrevented) {
                submitted.push('yes');
            }
            event.preventDefault();
        });

        click('s3');
        expect(submitted).toEqual([]);
        expect(muform.form.querySelector('input[name="wizardstage"]')).toBeNull();
        expect(mockChangechecker.markFormSubmitted).not.toHaveBeenCalled();
    });
});
