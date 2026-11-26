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
 * or {status: 'cancelled'}. Output printed by the handler, such as debugging messages, is part of
 * the html, the other answers have it in output and the dialog shows it before it goes on.
 * Importing this module installs a click handler for elements with data-muform-dialog-url attribute.
 *
 * @module     tool_mulib/muform/dialog
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import config from '@moodle/lms/core/config';
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
    size?: 'sm' | 'lg' | 'xl' | 'xxl';
    action?: SubmittedAction;
    trigger?: HTMLElement;
    /** Initial values posted with sesskey in the first request, scripts use them as defaults. */
    data?: Record<string, string>;
}

/** JSON answers of the handler. */
type Answer =
    | {status: 'render'; title: string; html: string; javascript: string}
    | {status: 'submitted'; redirecturl: string | null; data: unknown; output?: string}
    | {status: 'cancelled'; redirecturl: string | null; output?: string};

/** Answers that end the dialog. */
type FinalAnswer = Exclude<Answer, {status: 'render'}>;

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

        const more = this.createMore();

        this.dialog.addEventListener('close', () => {
            yui.disconnect();
            more.disconnect();
            // YUI reuses its dialogues, they must survive the removal of this dialog.
            this.dialog.querySelectorAll(':scope > .moodle-dialogue-base').forEach((node) => document.body.append(node));
            this.dialog.remove();
            this.options.trigger?.focus();
            this.resolveClosed();
        });
    }

    /**
     * Floating "More" pill shown while the form continues below the visible part of the dialog body.
     *
     * Hidden from assistive technologies and keyboard focus, they do not depend on the visible area.
     *
     * @returns observers to disconnect when the dialog closes
     */
    private createMore(): {disconnect: () => void} {
        const body = this.body;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-light rounded-pill muform-dialog-more';
        button.tabIndex = -1;
        button.setAttribute('aria-hidden', 'true');
        button.hidden = true;
        button.innerHTML = '<i class="icon fa fa-arrow-down fa-fw m-0"></i> <span></span>';
        const label = button.querySelector('span')!;
        getString('muform_more', 'tool_mulib').then((text) => {
            label.textContent = text;
            return text;
        }).catch(() => undefined);
        const update = (): void => {
            button.hidden = body.scrollHeight - body.scrollTop - body.clientHeight <= 2;
        };
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }
            event.preventDefault();
            const end = body.scrollHeight - body.clientHeight;
            let top = body.scrollTop + Math.round(body.clientHeight * 0.8);
            if (end - top < body.clientHeight * 0.25) {
                top = end;
            }
            body.scrollTo({top, behavior: 'smooth'});
        });
        body.addEventListener('scrollend', update);
        this.dialog.querySelector('.modal-content')!.append(button);

        const resize = new ResizeObserver(update);
        const observeContent = (): void => {
            resize.disconnect();
            resize.observe(body);
            for (const child of Array.from(body.children)) {
                resize.observe(child);
            }
            update();
        };
        const mutation = new MutationObserver(observeContent);
        mutation.observe(body, {childList: true});
        body.addEventListener('scroll', update, {passive: true});
        observeContent();

        return {
            disconnect: () => {
                resize.disconnect();
                mutation.disconnect();
            },
        };
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
        if (this.options.data) {
            const body = new FormData();
            for (const [name, value] of Object.entries(this.options.data)) {
                body.append(name, value);
            }
            body.append('sesskey', String(config.sesskey ?? ''));
            await this.request({method: 'POST', body});
        } else {
            await this.request({method: 'GET'});
        }
    }

    /**
     * Fetch the handler and process the answer.
     *
     * @param init fetch options
     * @param url handler URL, the dialog URL by default
     */
    private async request(init: RequestInit, url: string = this.options.url): Promise<void> {
        const pending = new Pending('tool_mulib/muform:dialog');
        let navigating = false;
        try {
            const response = await fetch(url, {
                ...init,
                headers: {[HEADER]: '1', 'Accept': 'application/json'},
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`Dialog request failed: ${response.status}`);
            }
            navigating = await this.handle(await response.json() as Answer);
        } catch (error) {
            this.dialog.close();
            try {
                const notification = await requireAsync<Notification>('core/notification');
                notification.exception(error);
            } catch {
                window.console.error(error);
            }
        } finally {
            if (!navigating) {
                // While the browser is leaving the page the old page must not look settled to Behat.
                pending.resolve();
            }
        }
    }

    /**
     * Apply an answer of the handler.
     *
     * @param answer parsed JSON
     * @returns true when the browser navigates away
     */
    private async handle(answer: Answer): Promise<boolean> {
        if (answer.status === 'render') {
            await this.render(answer.title, answer.html, answer.javascript);
            return false;
        }
        if (answer.output) {
            // Printed output such as debugging messages would be lost, wait for the user like Moodle redirects do.
            this.showOutput(answer.output, answer);
            return false;
        }
        return this.finish(answer);
    }

    /**
     * Close the dialog and do what the final answer of the handler asks for.
     *
     * @param answer parsed JSON
     * @returns true when the browser navigates away
     */
    private finish(answer: FinalAnswer): boolean {
        if (answer.status === 'submitted') {
            this.dialog.close();
            const action = this.options.action ?? 'reload';
            if (action === 'reload') {
                // Navigating to the current URL never repeats a POST, unlike location.reload().
                redirect(window.location.href);
                return true;
            } else if (action === 'redirect' && answer.redirecturl) {
                redirect(answer.redirecturl);
                return true;
            }
            const target = this.options.trigger ?? document;
            target.dispatchEvent(new CustomEvent('muform:dialog-submitted', {bubbles: true, detail: {data: answer.data}}));
            return false;
        }
        this.dialog.close();
        return false;
    }

    /**
     * Show output printed by the handler and go on when the user continues or closes the dialog.
     *
     * @param html printed output
     * @param answer parsed JSON
     */
    private showOutput(html: string, answer: FinalAnswer): void {
        this.body.innerHTML = `<div data-muform-dialog-output>${html}</div>`
            + '<div class="mt-3"><button type="button" class="btn btn-primary" data-muform-dialog-continue></button></div>';
        const button = this.body.querySelector<HTMLButtonElement>('[data-muform-dialog-continue]')!;
        button.textContent = 'Continue';
        getString('continue', 'core').then((text) => {
            button.textContent = text;
            return text;
        }).catch(() => undefined);

        let done = false;
        const proceed = () => {
            if (done) {
                return;
            }
            done = true;
            const pending = new Pending('tool_mulib/muform:dialog');
            if (!this.finish(answer)) {
                // While the browser is leaving the page the old page must not look settled to Behat.
                pending.resolve();
            }
        };
        button.addEventListener('click', proceed);
        this.dialog.addEventListener('close', proceed);
        button.focus();
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
            focusable?.focus({preventScroll: true});
            this.body.scrollTop = 0;
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
