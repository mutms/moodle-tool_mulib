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

/**
 * HTML fixtures mirroring the muform mustache templates.
 *
 * @module     tool_mulib/muform/tests/fixtures
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {FormApi, Value} from '../../src/muform/types';

// The jsdom environment has no scrolling, pickers scroll the active option into view.
if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = (): void => undefined;
}

/**
 * Standard wrapper around a control, as rendered by element/wrapper.mustache.
 *
 * @param name element name
 * @param type element type
 * @param control control html
 * @param attrs extra wrapper attributes
 * @returns html
 */
export function wrap(name: string, type: string, control: string, attrs = ''): string {
    return `<div id="fitem_id_${name}" class="mb-3 row fitem muform-element" data-muform-element="${type}"`
        + ` data-muform-component="tool_mulib" data-muform-name="${name}"${attrs}>`
        + `<div class="col-md-3 col-form-label"><label id="id_${name}_label" for="id_${name}">${name}</label></div>`
        + `<div class="col-md-9 felement" data-muform-required-hint="Required" data-muform-invalid-hint="Error">`
        + control
        + `<div class="form-control-feedback invalid-feedback" id="id_error_${name}"></div></div></div>`;
}

/** Text input. */
export const TEXT = wrap('name', 'text', '<input type="text" class="form-control" id="id_name" name="name" value="Jane">');

/** Required number input. */
export const NUMBER = wrap('count', 'number',
    '<input type="text" inputmode="numeric" pattern="[0-9]+" class="form-control" id="id_count" name="count" value=""'
    + ' data-muform-min="1" data-muform-max="5" required>');

/** Checkbox with hidden carrier. */
export const CHECKBOX = wrap('enabled', 'checkbox',
    '<input type="hidden" name="enabled" value="0"><div class="form-check">'
    + '<input type="checkbox" class="form-check-input" id="id_enabled" name="enabled" value="1"></div>');

/** Radios. */
export const RADIOS = wrap('color', 'radios',
    '<div role="radiogroup"><input type="radio" id="id_color_0" name="color" value="red">'
    + '<input type="radio" id="id_color_1" name="color" value="green" checked></div>');

/** Checkboxes with hidden carrier. */
export const CHECKBOXES = wrap('roles', 'checkboxes',
    '<input type="hidden" name="roles[]" value=""><div role="group">'
    + '<input type="checkbox" id="id_roles_0" name="roles[]" value="a" checked>'
    + '<input type="checkbox" id="id_roles_1" name="roles[]" value="b">'
    + '<input type="checkbox" id="id_roles_2" name="roles[]" value="c" checked></div>');

/** Single select. */
export const SELECT = wrap('country', 'select',
    '<select class="form-select" id="id_country" name="country"><option value="">Choose</option>'
    + '<option value="cz" selected>Czechia</option></select>');

/** Multiple select with hidden carrier. */
export const MULTISELECT = wrap('langs', 'multiselect',
    '<input type="hidden" name="langs[]" value=""><select multiple id="id_langs" name="langs[]">'
    + '<option value="en" selected>English</option><option value="cs">Czech</option><option value="de" selected>German</option>'
    + '</select>');

/** Section with children, as rendered by element/section.mustache. */
export function section(name: string, children: string): string {
    return `<fieldset id="fitem_id_${name}" data-muform-element="section" data-muform-component="tool_mulib"`
        + ` data-muform-name="${name}"><legend>${name}</legend>${children}</fieldset>`;
}

/** Button, as rendered by element/button.mustache. */
export function button(name: string, role: string): string {
    return `<button type="submit" id="id_${name}" name="${name}" value="1" data-muform-element="${role}"`
        + ` data-muform-component="tool_mulib" data-muform-name="${name}" data-muform-role="${role}"`
        + `${role === 'submit' ? '' : ' formnovalidate'}>${name}</button>`;
}

/**
 * Whole form.
 *
 * @param elements element html
 * @param rules display rules
 * @param attrs extra form attributes
 * @returns the form element attached to document body
 */
export function form(elements: string, rules: object[] = [], attrs = ''): HTMLFormElement {
    document.body.innerHTML = `<form id="f1" method="post" action="/x" class="muform mform" novalidate data-muform="f1"`
        + ` data-muform-rules='${JSON.stringify(rules)}'${attrs}>`
        + '<input type="hidden" name="sesskey" value="s"><input type="hidden" name="__formid" value="f1">'
        + elements + '</form>';
    return document.querySelector('form') as HTMLFormElement;
}

/**
 * Minimal form API for element tests.
 *
 * @param formel the form element
 * @returns stub API recording touched names
 */
export function fakeForm(formel: HTMLFormElement): FormApi & {touchedNames: string[]} {
    const touchedNames: string[] = [];
    return {
        form: formel,
        touchedNames,
        getValue: (): Value => null,
        submit: () => undefined,
        reload: () => undefined,
        cancel: () => undefined,
        touched: (name: string) => {
            touchedNames.push(name);
        },
        on: () => () => undefined,
    };
}
