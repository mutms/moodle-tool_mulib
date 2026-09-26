import DurationElement from '../../src/muform/element/duration';
import {fakeForm, form, wrap} from './fixtures';

/**
 * Duration inputs as rendered by element/duration.mustache.
 *
 * @param values input values by unit
 * @param required required group
 * @returns html
 */
function fixture(values: Record<string, string>, required = false): string {
    const inputs = ['w', 'd', 'h', 'i', 's'].map((unit) =>
        `<input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control"`
        + ` id="id_timelimit_${unit}" name="timelimit[${unit}]"`
        + ` value="${values[unit] ?? ''}" data-muform-unit="${unit}">`).join('');
    return wrap('timelimit', 'duration',
        `<div role="group" id="id_timelimit"${required ? ' data-muform-required="1"' : ''}>${inputs}</div>`);
}

/**
 * Create the element.
 *
 * @param html fixture
 * @returns element and wrapper
 */
function make(html: string): {element: DurationElement; wrapper: HTMLElement} {
    const formel = form(html);
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="timelimit"]')!;
    return {element: new DurationElement(wrapper, fakeForm(formel)), wrapper};
}

describe('tool_mulib/muform/element/duration', () => {
    it('sums the unit inputs into seconds', () => {
        expect(make(fixture({})).element.getValue()).toBe('0');
        expect(make(fixture({w: '1', d: '2', h: '3', i: '4'})).element.getValue())
            .toBe(String(604800 + 2 * 86400 + 3 * 3600 + 4 * 60));
        expect(make(fixture({i: '90'})).element.getValue()).toBe('5400');
        expect(make(fixture({h: 'abc', i: '1'})).element.getValue()).toBe('60');
        expect(make(fixture({i: '1', s: '30'})).element.getValue()).toBe('90');
    });

    it('requires a non-zero total only when the group is required', () => {
        expect(make(fixture({})).element.validate()).toEqual([]);
        expect(make(fixture({i: '0'}, true)).element.validate()).toEqual(['Required']);
        expect(make(fixture({i: '1'}, true)).element.validate()).toEqual([]);

        const {element} = make(fixture({}, true));
        element.state.hidden = true;
        expect(element.validate()).toEqual([]);
        element.state.hidden = false;
        element.state.disabled = true;
        expect(element.validate()).toEqual([]);
    });

    it('reports native constraint errors of any input', () => {
        const {element, wrapper} = make(fixture({h: '1'}, true));
        const minutes = wrapper.querySelector<HTMLInputElement>('input[data-muform-unit="i"]')!;
        minutes.value = '-5';
        expect(element.validate()).toEqual(['Error']);
        minutes.value = '1.5';
        expect(element.validate()).toEqual(['Error']);
        minutes.value = '';
        expect(element.validate()).toEqual([]);
    });

    it('disables every input and marks them invalid', () => {
        const {element, wrapper} = make(fixture({}));
        element.state.disabled = true;
        element.state.errors = ['Bad'];
        element.syncUI();
        const inputs = Array.from(wrapper.querySelectorAll<HTMLInputElement>('input'));
        expect(inputs).toHaveLength(5);
        expect(inputs.every((input) => input.disabled && input.classList.contains('is-invalid'))).toBe(true);
    });
});
