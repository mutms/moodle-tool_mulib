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
 * Native dialog showing a muform served by a handler URL.
 *
 * The handler answers requests carrying the X-Muform-Dialog header with JSON:
 * {status: 'render', title, html, javascript}, {status: 'submitted', redirecturl, data}
 * or {status: 'cancelled'}. Importing this module installs a click handler for
 * elements with data-muform-dialog-url attribute.
 *
 * @module     tool_mulib/muform/dialog
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {requireAsync} from '@moodle/lms/core/amd';
import {redirect} from '@moodle/lms/core/location';
import {getString} from '@moodle/lms/core/stringUtils';
import {initForm} from './form';

/** Duration of the height transition after re-rendering, a bit quicker than the 150ms of Moodle modals. */
const RESIZE_DURATION = 100;

/** Request header marking dialog requests, must match tool_mulib\muform\dialog::HEADER. */
const HEADER = 'X-Muform-Dialog';

/** What to do after successful submission. */
export type SubmittedAction = 'reload' | 'redirect' | 'nothing';

/** Dialog options, usually read from trigger data attributes. */
export interface DialogOptions {
    url: string;
    title?: string;
    size?: 'sm' | 'lg' | 'xl';
    action?: SubmittedAction;
    trigger?: HTMLElement;
}

/** JSON answers of the handler. */
type Answer =
    | {status: 'render'; title: string; html: string; javascript: string}
    | {status: 'submitted'; redirecturl: string | null; data: unknown}
    | {status: 'cancelled'; redirecturl: string | null};

/** Subset of core/fragment. */
interface Fragment {
    processCollectedJavascript(js: string): string;
}

/** Subset of core/templates. */
interface Templates {
    runTemplateJS(js: string): void;
}

/** Subset of core/notification. */
interface Notification {
    exception(error: unknown): void;
}

/**
 * Open dialog for a trigger element with data-muform-dialog-* attributes.
 *
 * @param trigger the clicked element
 * @returns promise resolved when the dialog is closed
 */
export function openFrom(trigger: HTMLElement): Promise<void> {
    const data = trigger.dataset;
    return open({
        url: data.muformDialogUrl || (trigger as HTMLAnchorElement).href,
        title: data.muformDialogTitle,
        size: data.muformDialogSize as DialogOptions['size'],
        action: data.muformDialogAction as SubmittedAction,
        trigger,
    });
}

/**
 * Open dialog and load the form from the handler URL.
 *
 * @param options dialog options
 * @returns promise resolved when the dialog is closed
 */
export async function open(options: DialogOptions): Promise<void> {
    const dialog = new MuDialog(options);
    await dialog.show();
    return dialog.closed;
}

class MuDialog {
    /** Options. */
    private readonly options: DialogOptions;

    /** The dialog element. */
    private readonly dialog: HTMLDialogElement;

    /** Title element. */
    private readonly title: HTMLElement;

    /** Content element. */
    private readonly body: HTMLElement;

    /** Resolved when the dialog is closed. */
    readonly closed: Promise<void>;

    /** Resolver of closed. */
    private resolveClosed!: () => void;

    /**
     * Build the dialog markup.
     *
     * @param options dialog options
     */
    constructor(options: DialogOptions) {
        this.options = options;
        this.closed = new Promise((resolve) => {
            this.resolveClosed = resolve;
        });

        this.dialog = document.createElement('dialog');
        this.dialog.className = `muform-dialog muform-dialog-${options.size ?? 'lg'}`;
        this.dialog.innerHTML = '<div class="modal-content"><div class="modal-header">'
            + '<h2 class="modal-title h5"></h2>'
            + '<button type="button" class="btn-close" data-muform-dialog-close></button>'
            + '</div><div class="modal-body"></div></div>';
        this.title = this.dialog.querySelector('.modal-title')!;
        this.body = this.dialog.querySelector('.modal-body')!;
        this.title.id = `muform-dialog-title-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
        this.dialog.setAttribute('aria-labelledby', this.title.id);
        this.title.textContent = options.title ?? '';

        const close = this.dialog.querySelector<HTMLButtonElement>('[data-muform-dialog-close]')!;
        close.addEventListener('click', () => this.dialog.close());
        getString('closebuttontitle', 'core').then((text) => {
            close.setAttribute('aria-label', text);
            return text;
        }).catch(() => close.setAttribute('aria-label', 'Close'));

        // Theme popovers such as help icons are appended to the body, which is inert while the modal
        // dialog is open, so keyboard focus could not enter or leave them. Keep them inside the dialog.
        this.dialog.addEventListener('inserted.bs.popover', (event) => {
            const id = (event.target as HTMLElement).getAttribute('aria-describedby');
            const tip = id ? document.getElementById(id) : null;
            if (tip && !this.dialog.contains(tip)) {
                this.dialog.append(tip);
            }
        });

        // Legacy YUI dialogues such as the file picker are appended to the body too, below the modal
        // dialog in the top layer, so they could not be used at all. Keep them inside while open.
        const yui = new MutationObserver((records) => {
            for (const record of records) {
                record.addedNodes.forEach((node) => {
                    if (node instanceof HTMLElement && node.classList.contains('moodle-dialogue-base')) {
                        this.dialog.append(node);
                    }
                });
            }
        });
        yui.observe(document.body, {childList: true});

        this.dialog.addEventListener('close', () => {
            yui.disconnect();
            // YUI reuses its dialogues, they must survive the removal of this dialog.
            this.dialog.querySelectorAll(':scope > .moodle-dialogue-base').forEach((node) => document.body.append(node));
            this.dialog.remove();
            this.options.trigger?.focus();
            this.resolveClosed();
        });
    }

    /**
     * Show the dialog and load the form.
     */
    async show(): Promise<void> {
        // A spinner holds the space while loading, the form then grows out of it like in Moodle modals.
        this.body.innerHTML = '<div class="d-flex justify-content-center py-4" data-muform-dialog-loading>'
            + '<div class="spinner-border text-secondary" role="status"><span class="visually-hidden"></span></div></div>';
        const label = this.body.querySelector('.visually-hidden')!;
        getString('muform_loading', 'tool_mulib').then((text) => {
            label.textContent = text;
            return text;
        }).catch(() => undefined);
        document.body.append(this.dialog);
        this.dialog.showModal();
        await this.request({method: 'GET'});
    }

    /**
     * Fetch the handler and process the answer.
     *
     * @param init fetch options
     * @param url handler URL, the dialog URL by default
     */
    private async request(init: RequestInit, url: string = this.options.url): Promise<void> {
        const pending = new Pending('tool_mulib/muform:dialog');
        try {
            const response = await fetch(url, {
                ...init,
                headers: {[HEADER]: '1', 'Accept': 'application/json'},
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`Dialog request failed: ${response.status}`);
            }
            await this.handle(await response.json() as Answer);
        } catch (error) {
            this.dialog.close();
            try {
                const notification = await requireAsync<Notification>('core/notification');
                notification.exception(error);
            } catch {
                window.console.error(error);
            }
        } finally {
            pending.resolve();
        }
    }

    /**
     * Apply an answer of the handler.
     *
     * @param answer parsed JSON
     */
    private async handle(answer: Answer): Promise<void> {
        if (answer.status === 'render') {
            await this.render(answer.title, answer.html, answer.javascript);
            return;
        }
        if (answer.status === 'submitted') {
            this.dialog.close();
            const action = this.options.action ?? 'reload';
            if (action === 'reload') {
                // Navigating to the current URL never repeats a POST, unlike location.reload().
                redirect(window.location.href);
            } else if (action === 'redirect' && answer.redirecturl) {
                redirect(answer.redirecturl);
            } else {
                const target = this.options.trigger ?? document;
                target.dispatchEvent(new CustomEvent('muform:dialog-submitted', {bubbles: true, detail: {data: answer.data}}));
            }
            return;
        }
        this.dialog.close();
    }

    /**
     * Replace dialog content with a form and wire it up.
     *
     * @param title dialog title, empty keeps the current one
     * @param html form html
     * @param javascript collected JavaScript requirements
     */
    private async render(title: string, html: string, javascript: string): Promise<void> {
        if (title) {
            this.title.textContent = title;
        }
        const oldheight = this.body.childElementCount ? this.body.getBoundingClientRect().height : 0;
        this.body.innerHTML = html;
        // Start before anything is awaited, otherwise the browser paints the final height first.
        const resized = this.animateResize(oldheight);

        if (javascript) {
            try {
                const fragment = await requireAsync<Fragment>('core/fragment');
                const templates = await requireAsync<Templates>('core/templates');
                templates.runTemplateJS(fragment.processCollectedJavascript(javascript));
            } catch (error) {
                window.console.error(error);
            }
        }

        const form = this.body.querySelector<HTMLFormElement>('form[data-muform]');
        if (form) {
            const muform = await initForm(form);
            form.addEventListener('submit', (event) => this.onSubmit(event));
            this.body.dispatchEvent(new CustomEvent('core/modal:bodyRendered', {bubbles: true}));
            muform.focusFirstError();
        }
        if (!this.body.contains(document.activeElement)) {
            let focusable = this.body.querySelector<HTMLElement>('input:not([type="hidden"]), select, textarea, button');
            if (focusable instanceof HTMLInputElement && focusable.type === 'radio' && !focusable.checked) {
                // Focus the chosen answer, a focus ring on another radio looks like a different choice.
                const name = focusable.name;
                const group = Array.from(this.body.querySelectorAll<HTMLInputElement>('input[type="radio"]'))
                    .filter((radio) => radio.name === name);
                focusable = group.find((radio) => radio.checked) ?? focusable;
            }
            focusable?.focus();
        }
        await resized;
    }

    /**
     * Ease the height change of re-rendered content, like Moodle modals but quicker.
     *
     * Also used when the first form replaces the loading spinner. Skipped in Behat runs and when
     * users prefer reduced motion.
     *
     * @param oldheight body height before the content was replaced, 0 for the first render
     */
    private async animateResize(oldheight: number): Promise<void> {
        const behat = Boolean((window as unknown as {M?: {cfg?: {behatsiterunning?: boolean}}}).M?.cfg?.behatsiterunning);
        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        if (!oldheight || behat || reduced || typeof this.body.animate !== 'function') {
            return;
        }
        // Reading the height forces layout of the new content synchronously, before the next paint.
        const newheight = this.body.getBoundingClientRect().height;
        if (Math.abs(newheight - oldheight) < 1) {
            return;
        }
        const pending = new Pending('tool_mulib/muform:dialog-resize');
        const overflow = this.body.style.overflow;
        this.body.style.overflow = 'hidden';
        try {
            await this.body.animate(
                [{height: `${oldheight}px`, opacity: 0.4}, {height: `${newheight}px`, opacity: 1}],
                {duration: RESIZE_DURATION, easing: 'ease-out'}
            ).finished;
        } catch {
            // Cancelled animations are fine, the final layout is already in place.
        } finally {
            this.body.style.overflow = overflow;
            pending.resolve();
        }
    }

    /**
     * Submit the form via fetch instead of navigating.
     *
     * @param event the submit event, already validated by the form orchestrator
     */
    private onSubmit(event: SubmitEvent): void {
        const submitter = event.submitter as HTMLButtonElement | null;
        if (submitter?.dataset.muformRole === 'cancel') {
            event.preventDefault();
            this.dialog.close();
            return;
        }
        if (event.defaultPrevented || submitter?.dataset.muformDownload) {
            // Downloads are submitted natively to a new window.
            return;
        }
        event.preventDefault();
        const form = event.target as HTMLFormElement;
        const data = new FormData(form);
        if (submitter?.name) {
            data.append(submitter.name, submitter.value);
        }
        // The form decides where it is submitted, multi-step handlers move to another URL.
        this.request({method: 'POST', body: data}, form.action || this.options.url).catch(() => undefined);
    }
}

document.addEventListener('click', (event) => {
    const trigger = (event.target as HTMLElement).closest<HTMLElement>('[data-muform-dialog-url]');
    if (!trigger) {
        return;
    }
    event.preventDefault();
    openFrom(trigger).catch(() => undefined);
});
