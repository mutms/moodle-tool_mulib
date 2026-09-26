import CheckboxesElement from '../../src/muform/element/checkboxes';
import {fakeForm, form, wrap} from './fixtures';

/**
 * Checkbox group as rendered by element/checkboxes.mustache.
 *
 * @param checked keys that are ticked
 * @param required required group
 * @returns html
 */
function fixture(checked: string[], required = false): string {
    const boxes = ['a', 'b'].map((key) => {
        const attrs = checked.includes(key) ? ' checked' : '';
        return `<input type="checkbox" id="id_roles_${key}" name="roles[]" value="${key}"${attrs}>`;
    }).join('');
    const group = required ? ' data-muform-required="1"' : '';
    return wrap('roles', 'checkboxes',
        `<input type="hidden" name="roles[]" value=""><div role="group" id="id_roles"${group}>${boxes}</div>`);
}

/**
 * Create the element.
 *
 * @param html fixture
 * @returns the element
 */
function make(html: string): CheckboxesElement {
    const formel = form(html);
    return new CheckboxesElement(formel.querySelector<HTMLElement>('[data-muform-name="roles"]')!, fakeForm(formel));
}

describe('tool_mulib/muform/element/checkboxes', () => {
    it('requires a ticked box only when the group is required', () => {
        expect(make(fixture([])).validate()).toEqual([]);
        expect(make(fixture([], true)).validate()).toEqual(['Required']);
        expect(make(fixture(['b'], true)).validate()).toEqual([]);

        const element = make(fixture([], true));
        element.state.hidden = true;
        expect(element.validate()).toEqual([]);
        element.state.hidden = false;
        element.state.disabled = true;
        expect(element.validate()).toEqual([]);
    });
});
