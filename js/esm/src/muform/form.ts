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
 * Form orchestrator: loads one ES module per element, wires the display manager,
 * validates on submit and on blur, and guards against double submission.
 *
 * The orchestrator never touches the DOM inside element wrappers, it only reads
 * values, writes element state and calls syncUI().
 *
 * @module     tool_mulib/muform/form
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {requireAsync} from '@moodle/lms/core/amd';
import DisplayManager from './display';
import type {ChangeDetail, DisplayRule, ElementConstructor, ElementLike, FormApi, FormEvent, Value} from './types';

/** Already initialised forms. */
const forms = new WeakMap<HTMLFormElement, MuForm>();

/** Input types submitting the form when Enter is pressed. */
const IMPLICIT_SUBMIT_TYPES = new Set([
    'text', 'search', 'url', 'tel', 'email', 'password', 'number', 'date', 'month', 'week', 'time', 'datetime-local',
]);

/** Loads element modules, replaceable for tests. */
let loadModule = (specifier: string): Promise<unknown> => import(specifier);

/**
 * Replace the element module loader, intended for tests.
 *
 * @param loader function resolving a module specifier to its exports
 */
export function setModuleLoader(loader: (specifier: string) => Promise<unknown>): void {
    loadModule = loader;
}

/** Subset of core_form/changechecker used here. */
interface ChangeChecker {
    watchForm(form: HTMLFormElement): void;
    markFormSubmitted(form: HTMLFormElement): void;
}

/**
 * Upgrade a server rendered muform, safe to call repeatedly.
 *
 * @param form the form element with data-muform attribute
 * @returns the orchestrator
 */
export async function initForm(form: HTMLFormElement): Promise<MuForm> {
    const existing = forms.get(form);
    if (existing) {
        return existing;
    }
    const pending = new Pending('tool_mulib/muform:init');
    try {
        const muform = new MuForm(form);
        forms.set(form, muform);
        await muform.init();
        return muform;
    } finally {
        pending.resolve();
    }
}

/**
 * Orchestrator of an already initialised form.
 *
 * @param form the form element
 * @returns the orchestrator or undefined
 */
export function getForm(form: HTMLFormElement): MuForm | undefined {
    return forms.get(form);
}

export class MuForm implements FormApi {
    /** The form element. */
    readonly form: HTMLFormElement;

    /** Elements indexed by name, in document order. */
    readonly elements = new Map<string, ElementLike>();

    /** Display manager, created in init(). */
    private display?: DisplayManager;

    /** Event handlers. */
    private readonly handlers = new Map<FormEvent, Set<(detail: unknown) => void>>();

    /** True after the first failed submission, then every element is validated on blur. */
    private submitAttempted = false;

    /** True once a submission left the browser, every later submit event is refused. */
    private submitting = false;

    /** Change checker from core, when available. */
    private changechecker?: ChangeChecker;

    /**
     * Use initForm() instead.
     *
     * @param form the form element
     */
    constructor(form: HTMLFormElement) {
        this.form = form;
    }

    /**
     * Load element modules, evaluate display rules and attach listeners.
     */
    async init(): Promise<void> {
        const wrappers = this.form.querySelectorAll<HTMLElement>('[data-muform-element]');
        for (const wrapper of wrappers) {
            const type = wrapper.dataset.muformElement;
            const component = wrapper.dataset.muformComponent;
            const name = wrapper.dataset.muformName;
            if (!type || !component || !name) {
                continue;
            }
            const specifier = `@moodle/lms/${component}/muform/element/${type}`;
            const module = await loadModule(specifier) as {default: ElementConstructor};
            this.elements.set(name, new module.default(wrapper, this));
        }

        const rules = JSON.parse(this.form.dataset.muformRules || '[]') as DisplayRule[];
        this.display = new DisplayManager(this.elements, rules);
        this.display.apply();

        this.form.addEventListener('muform:change', (event) => this.onChange((event as CustomEvent<ChangeDetail>).detail));
        this.form.addEventListener('submit', (event) => this.onSubmit(event));
        this.form.addEventListener('keydown', (event) => this.onKeyDown(event));

        if (this.form.dataset.muformHasErrors === '1') {
            this.focusFirstError();
        }

        try {
            this.changechecker = await requireAsync<ChangeChecker>('core_form/changechecker');
            this.changechecker.watchForm(this.form);
        } catch {
            // Change checker is optional.
        }

        this.emit('ready', {form: this});
    }

    /**
     * Value of an element by name.
     *
     * @param name element name
     * @returns the value or null for unknown elements
     */
    getValue(name: string): Value {
        return this.elements.get(name)?.getValue() ?? null;
    }

    /**
     * Submit the form as if the first submit button was pressed.
     */
    submit(): void {
        this.press('submit');
    }

    /**
     * Resubmit without validation so that the server can rebuild the definition.
     */
    reload(): void {
        this.press('reload');
    }

    /**
     * Cancel editing.
     */
    cancel(): void {
        this.press('cancel');
    }

    /**
     * Element lost focus, validate it when it was already touched or a submission failed.
     *
     * @param name element name
     */
    touched(name: string): void {
        const element = this.elements.get(name);
        if (!element || !(element.state.touched || this.submitAttempted)) {
            return;
        }
        const errors = element.validate();
        if (errors.join('\n') !== element.state.errors.join('\n')) {
            element.state.errors = errors;
            element.syncUI();
        }
    }

    /**
     * Subscribe to form events.
     *
     * @param event event name
     * @param handler callback
     * @returns function that removes the handler
     */
    on(event: FormEvent, handler: (detail: unknown) => void): () => void {
        if (!this.handlers.has(event)) {
            this.handlers.set(event, new Set());
        }
        this.handlers.get(event)!.add(handler);
        return () => this.handlers.get(event)?.delete(handler);
    }

    /**
     * Validate all visible and enabled elements and show their errors.
     *
     * @returns true when everything is valid
     */
    validateAll(): boolean {
        let valid = true;
        for (const element of this.elements.values()) {
            const errors = element.validate();
            if (errors.length) {
                valid = false;
            }
            if (errors.join('\n') !== element.state.errors.join('\n')) {
                element.state.errors = errors;
                element.syncUI();
            }
        }
        return valid;
    }

    /**
     * Focus the first element that has errors.
     */
    focusFirstError(): void {
        for (const element of this.elements.values()) {
            const area = element.wrapper.querySelector('.invalid-feedback, .alert-danger');
            if (element.state.errors.length || (area && area.textContent?.trim())) {
                element.focus();
                return;
            }
        }
    }

    /**
     * Click the first button with the given role.
     *
     * @param role submit, reload or cancel
     */
    private press(role: string): void {
        const button = this.form.querySelector<HTMLButtonElement>(`button[data-muform-role="${role}"]`);
        if (!button) {
            throw new Error(`muform has no ${role} button`);
        }
        button.click();
    }

    /**
     * Re-evaluate display rules when a dependency changed.
     *
     * @param detail change detail
     */
    private onChange(detail: ChangeDetail): void {
        this.emit('change', detail);
        if (this.display?.dependsOn(detail.name)) {
            const changed = this.display.apply();
            if (changed.length) {
                this.emit('display', {changed});
            }
        }
    }

    /**
     * Enter in a single line input submits through the submit button, browsers would use
     * the first submit button in the form, which may be a reload button such as "Delete row".
     *
     * @param event the keydown event
     */
    private onKeyDown(event: KeyboardEvent): void {
        if (event.key !== 'Enter' || event.defaultPrevented || event.isComposing) {
            return;
        }
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !IMPLICIT_SUBMIT_TYPES.has(target.type)) {
            return;
        }
        const first = this.form.querySelector<HTMLButtonElement>('button[type="submit"]');
        const submit = this.form.querySelector<HTMLButtonElement>('button[data-muform-role="submit"]');
        if (!submit || first === submit) {
            return;
        }
        event.preventDefault();
        submit.click();
    }

    /**
     * Validate before native submission unless the submitter skips validation.
     *
     * @param event the submit event
     */
    private onSubmit(event: SubmitEvent): void {
        if (this.submitting) {
            // Double click or Enter pressed twice, the first submission is already on its way.
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        const submitter = event.submitter as HTMLButtonElement | null;
        const role = submitter?.dataset.muformRole ?? 'submit';
        if (role === 'submit') {
            this.submitAttempted = true;
            if (!this.validateAll()) {
                event.preventDefault();
                this.emit('invalid', {form: this});
                this.focusFirstError();
                return;
            }
        }
        if (submitter?.dataset.muformDownload) {
            // The file is downloaded in a new window, this page stays as it is and must remain usable.
            return;
        }
        this.submitting = true;
        this.emit('submit', {form: this, role});
        this.changechecker?.markFormSubmitted(this.form);
        // Double submit protection, the browser keeps the submitter value when disabled after this event.
        window.setTimeout(() => {
            for (const button of this.form.querySelectorAll<HTMLButtonElement>('button[type="submit"]')) {
                button.disabled = true;
            }
        }, 0);
    }

    /**
     * Notify handlers.
     *
     * @param event event name
     * @param detail event detail
     */
    private emit(event: FormEvent, detail: unknown): void {
        for (const handler of this.handlers.get(event) ?? []) {
            handler(detail);
        }
    }
}
