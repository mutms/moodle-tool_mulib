var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
import Pending from "@moodle/lms/core/pending";
import { requireAsync } from "@moodle/lms/core/amd";
import { redirect } from "@moodle/lms/core/location";
import { getString } from "@moodle/lms/core/stringUtils";
import { initForm } from "./form";
const RESIZE_DURATION = 100;
const HEADER = "X-Muform-Dialog";
function openFrom(trigger) {
  const data = trigger.dataset;
  return open({
    url: data.muformDialogUrl || trigger.href,
    title: data.muformDialogTitle,
    size: data.muformDialogSize,
    action: data.muformDialogAction,
    trigger
  });
}
__name(openFrom, "openFrom");
async function open(options) {
  const dialog = new MuDialog(options);
  await dialog.show();
  return dialog.closed;
}
__name(open, "open");
class MuDialog {
  static {
    __name(this, "MuDialog");
  }
  /** Options. */
  options;
  /** The dialog element. */
  dialog;
  /** Title element. */
  title;
  /** Content element. */
  body;
  /** Resolved when the dialog is closed. */
  closed;
  /** Resolver of closed. */
  resolveClosed;
  /**
   * Build the dialog markup.
   *
   * @param options dialog options
   */
  constructor(options) {
    this.options = options;
    this.closed = new Promise((resolve) => {
      this.resolveClosed = resolve;
    });
    this.dialog = document.createElement("dialog");
    this.dialog.className = `muform-dialog muform-dialog-${options.size ?? "lg"}`;
    this.dialog.innerHTML = '<div class="modal-content"><div class="modal-header"><h2 class="modal-title h5"></h2><button type="button" class="btn-close" data-muform-dialog-close></button></div><div class="modal-body"></div></div>';
    this.title = this.dialog.querySelector(".modal-title");
    this.body = this.dialog.querySelector(".modal-body");
    this.title.id = `muform-dialog-title-${Date.now()}-${Math.floor(Math.random() * 1e5)}`;
    this.dialog.setAttribute("aria-labelledby", this.title.id);
    this.title.textContent = options.title ?? "";
    const close = this.dialog.querySelector("[data-muform-dialog-close]");
    close.addEventListener("click", () => this.dialog.close());
    getString("closebuttontitle", "core").then((text) => {
      close.setAttribute("aria-label", text);
      return text;
    }).catch(() => close.setAttribute("aria-label", "Close"));
    this.dialog.addEventListener("inserted.bs.popover", (event) => {
      const id = event.target.getAttribute("aria-describedby");
      const tip = id ? document.getElementById(id) : null;
      if (tip && !this.dialog.contains(tip)) {
        this.dialog.append(tip);
      }
    });
    const yui = new MutationObserver((records) => {
      for (const record of records) {
        record.addedNodes.forEach((node) => {
          if (node instanceof HTMLElement && node.classList.contains("moodle-dialogue-base")) {
            this.dialog.append(node);
          }
        });
      }
    });
    yui.observe(document.body, { childList: true });
    this.dialog.addEventListener("close", () => {
      yui.disconnect();
      this.dialog.querySelectorAll(":scope > .moodle-dialogue-base").forEach((node) => document.body.append(node));
      this.dialog.remove();
      this.options.trigger?.focus();
      this.resolveClosed();
    });
  }
  /**
   * Show the dialog and load the form.
   */
  async show() {
    this.body.innerHTML = '<div class="d-flex justify-content-center py-4" data-muform-dialog-loading><div class="spinner-border text-secondary" role="status"><span class="visually-hidden"></span></div></div>';
    const label = this.body.querySelector(".visually-hidden");
    getString("muform_loading", "tool_mulib").then((text) => {
      label.textContent = text;
      return text;
    }).catch(() => void 0);
    document.body.append(this.dialog);
    this.dialog.showModal();
    await this.request({ method: "GET" });
  }
  /**
   * Fetch the handler and process the answer.
   *
   * @param init fetch options
   * @param url handler URL, the dialog URL by default
   */
  async request(init, url = this.options.url) {
    const pending = new Pending("tool_mulib/muform:dialog");
    try {
      const response = await fetch(url, {
        ...init,
        headers: { [HEADER]: "1", "Accept": "application/json" },
        credentials: "same-origin"
      });
      if (!response.ok) {
        throw new Error(`Dialog request failed: ${response.status}`);
      }
      await this.handle(await response.json());
    } catch (error) {
      this.dialog.close();
      try {
        const notification = await requireAsync("core/notification");
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
  async handle(answer) {
    if (answer.status === "render") {
      await this.render(answer.title, answer.html, answer.javascript);
      return;
    }
    if (answer.status === "submitted") {
      this.dialog.close();
      const action = this.options.action ?? "reload";
      if (action === "reload") {
        redirect(window.location.href);
      } else if (action === "redirect" && answer.redirecturl) {
        redirect(answer.redirecturl);
      } else {
        const target = this.options.trigger ?? document;
        target.dispatchEvent(new CustomEvent("muform:dialog-submitted", { bubbles: true, detail: { data: answer.data } }));
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
  async render(title, html, javascript) {
    if (title) {
      this.title.textContent = title;
    }
    const oldheight = this.body.childElementCount ? this.body.getBoundingClientRect().height : 0;
    this.body.innerHTML = html;
    const resized = this.animateResize(oldheight);
    if (javascript) {
      try {
        const fragment = await requireAsync("core/fragment");
        const templates = await requireAsync("core/templates");
        templates.runTemplateJS(fragment.processCollectedJavascript(javascript));
      } catch (error) {
        window.console.error(error);
      }
    }
    const form = this.body.querySelector("form[data-muform]");
    if (form) {
      const muform = await initForm(form);
      form.addEventListener("submit", (event) => this.onSubmit(event));
      this.body.dispatchEvent(new CustomEvent("core/modal:bodyRendered", { bubbles: true }));
      muform.focusFirstError();
    }
    if (!this.body.contains(document.activeElement)) {
      const focusable = this.body.querySelector('input:not([type="hidden"]), select, textarea, button');
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
  async animateResize(oldheight) {
    const behat = Boolean(window.M?.cfg?.behatsiterunning);
    const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
    if (!oldheight || behat || reduced || typeof this.body.animate !== "function") {
      return;
    }
    const newheight = this.body.getBoundingClientRect().height;
    if (Math.abs(newheight - oldheight) < 1) {
      return;
    }
    const pending = new Pending("tool_mulib/muform:dialog-resize");
    const overflow = this.body.style.overflow;
    this.body.style.overflow = "hidden";
    try {
      await this.body.animate(
        [{ height: `${oldheight}px`, opacity: 0.4 }, { height: `${newheight}px`, opacity: 1 }],
        { duration: RESIZE_DURATION, easing: "ease-out" }
      ).finished;
    } catch {
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
  onSubmit(event) {
    const submitter = event.submitter;
    if (submitter?.dataset.muformRole === "cancel") {
      event.preventDefault();
      this.dialog.close();
      return;
    }
    if (event.defaultPrevented || submitter?.dataset.muformDownload) {
      return;
    }
    event.preventDefault();
    const form = event.target;
    const data = new FormData(form);
    if (submitter?.name) {
      data.append(submitter.name, submitter.value);
    }
    this.request({ method: "POST", body: data }, form.action || this.options.url).catch(() => void 0);
  }
}
document.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-muform-dialog-url]");
  if (!trigger) {
    return;
  }
  event.preventDefault();
  openFrom(trigger).catch(() => void 0);
});
export {
  open,
  openFrom
};
//# sourceMappingURL=dialog.dev.js.map
