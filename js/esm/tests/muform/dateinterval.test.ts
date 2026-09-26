import DateintervalElement from '../../src/muform/element/dateinterval';
import {fakeForm, form, wrap} from './fixtures';

/**
 * Date interval inputs as rendered by element/dateinterval.mustache.
 *
 * @param values input values by unit
 * @param required required group
 * @returns html
 */
function fixture(values: Record<string, string>, required = false): string {
    const inputs = ['y', 'm', 'w', 'd', 'h', 'i', 's'].map((unit) =>
        `<input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control"`
        + ` id="id_validity_${unit}" name="validity[${unit}]"`
        + ` value="${values[unit] ?? ''}" data-muform-unit="${unit}">`).join('');
    return wrap('validity', 'dateinterval',
        `<div role="group" id="id_validity"${required ? ' data-muform-required="1"' : ''}>${inputs}</div>`);
}

/**
 * Create the element.
 *
 * @param html fixture
 * @returns element and wrapper
 */
function make(html: string): {element: DateintervalElement; wrapper: HTMLElement} {
    const formel = form(html);
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="validity"]')!;
    return {element: new DateintervalElement(wrapper, fakeForm(formel)), wrapper};
}

describe('tool_mulib/muform/element/dateinterval', () => {
    it('builds the canonical ISO 8601 duration', () => {
        expect(make(fixture({})).element.getValue()).toBeNull();
        expect(make(fixture({y: '0', d: '0'})).element.getValue()).toBeNull();
        expect(make(fixture({y: '1', m: '2', w: '1', d: '3', h: '1', i: '2', s: '3'})).element.getValue())
            .toBe('P1Y2M1W3DT1H2M3S');
        expect(make(fixture({m: '6'})).element.getValue()).toBe('P6M');
        expect(make(fixture({h: '36'})).element.getValue()).toBe('PT36H');
        expect(make(fixture({w: 'abc', d: '2', i: '1.5'})).element.getValue()).toBe('P2D');
    });

    it('requires a value only when the group is required', () => {
        expect(make(fixture({})).element.validate()).toEqual([]);
        expect(make(fixture({s: '0'}, true)).element.validate()).toEqual(['Required']);
        expect(make(fixture({d: '1'}, true)).element.validate()).toEqual([]);

        const {element} = make(fixture({}, true));
        element.state.hidden = true;
        expect(element.validate()).toEqual([]);
        element.state.hidden = false;
        element.state.disabled = true;
        expect(element.validate()).toEqual([]);
    });

    it('reports native constraint errors of any input', () => {
        const {element, wrapper} = make(fixture({y: '1'}, true));
        const months = wrapper.querySelector<HTMLInputElement>('input[data-muform-unit="m"]')!;
        months.value = '-1';
        expect(element.validate()).toEqual(['Error']);
        months.value = '2.5';
        expect(element.validate()).toEqual(['Error']);
        months.value = '';
        expect(element.validate()).toEqual([]);
    });
});
