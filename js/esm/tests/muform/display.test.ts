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

import DisplayManager, {matches} from '../../src/muform/display';
import NativeElement from '../../src/muform/native';
import Element from '../../src/muform/element';
import type {DisplayRule, ElementLike} from '../../src/muform/types';
import {CHECKBOX, CHECKBOXES, TEXT, button, fakeForm, form, section} from './fixtures';

describe('tool_mulib/muform/display matches()', () => {
    it.each([
        ['eq', 'a', 'a', true],
        ['eq', 'a', 'b', false],
        ['eq', ['a', 'b'], 'b', true],
        ['eq', null, '', false],
        ['neq', 'a', 'b', true],
        ['in', 'a', ['a', 'b'], true],
        ['in', 'c', ['a', 'b'], false],
        ['in', ['x', 'b'], ['a', 'b'], true],
        ['in', null, ['a'], false],
        ['notin', 'c', ['a', 'b'], true],
        ['checked', '1', null, true],
        ['checked', '0', null, false],
        ['notchecked', '0', null, true],
        ['empty', null, null, true],
        ['empty', '', null, true],
        ['empty', [], null, true],
        ['empty', '0', null, false],
        ['notempty', 'a', null, true],
    ] as const)('%s(%j, %j) is %s', (op, depvalue, value, expected) => {
        expect(matches(op, depvalue as string | string[] | null, value as string | string[] | null)).toBe(expected);
    });

    it('rejects unknown operators', () => {
        expect(() => matches('xyz', 'a', 'a')).toThrow('Invalid display rule operator: xyz');
    });
});

describe('tool_mulib/muform/display DisplayManager', () => {
    /**
     * Build elements for a form.
     *
     * @param formel the form
     * @returns elements indexed by name
     */
    function elementsOf(formel: HTMLFormElement): Map<string, ElementLike> {
        const api = fakeForm(formel);
        const map = new Map<string, ElementLike>();
        for (const wrapper of formel.querySelectorAll<HTMLElement>('[data-muform-element]')) {
            const type = wrapper.dataset.muformElement;
            const container = type === 'section' || type === 'buttons';
            const element = container ? new Element(wrapper, api) : new NativeElement(wrapper, api);
            map.set(element.name, element);
        }
        return map;
    }

    it('hides and disables targets, ORs rules and cascades through sections', () => {
        const rules: DisplayRule[] = [
            {action: 'hide', target: 'name', dep: 'enabled', op: 'notchecked', value: null},
            {action: 'hide', target: 'name', dep: 'roles', op: 'in', value: ['b']},
            {action: 'disable', target: 'choices', dep: 'enabled', op: 'checked', value: null},
        ];
        const formel = form(CHECKBOX + TEXT + section('choices', CHECKBOXES + button('submit', 'submit')), rules);
        const elements = elementsOf(formel);
        const manager = new DisplayManager(elements, rules);
        expect(manager.dependsOn('enabled')).toBe(true);
        expect(manager.dependsOn('name')).toBe(false);

        expect(manager.apply().sort()).toEqual(['name']);
        expect(elements.get('name')!.state.hidden).toBe(true);
        expect(elements.get('name')!.wrapper.hidden).toBe(true);
        expect(elements.get('choices')!.state.disabled).toBe(false);
        expect(manager.apply()).toEqual([]);

        formel.querySelector<HTMLInputElement>('#id_enabled')!.checked = true;
        expect(manager.apply().sort()).toEqual(['choices', 'name', 'roles', 'submit']);
        expect(elements.get('name')!.state.hidden).toBe(false);
        expect(elements.get('choices')!.state.disabled).toBe(true);
        expect(elements.get('roles')!.state.disabled).toBe(true);
        expect(formel.querySelector<HTMLInputElement>('#id_roles_0')!.disabled).toBe(true);
        expect(formel.querySelector<HTMLButtonElement>('#id_submit')!.disabled).toBe(true);

        formel.querySelector<HTMLInputElement>('#id_roles_1')!.checked = true;
        expect(manager.apply()).toEqual(['name']);
        expect(elements.get('name')!.state.hidden).toBe(true);
    });

    it('ignores rules with unknown elements', () => {
        const rules: DisplayRule[] = [{action: 'hide', target: 'xyz', dep: 'name', op: 'notempty', value: null}];
        const formel = form(TEXT, rules);
        const manager = new DisplayManager(elementsOf(formel), rules);
        expect(manager.apply()).toEqual([]);
    });
});
