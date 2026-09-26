var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
import Pending from "@moodle/lms/core/pending";
import { requireAsync } from "@moodle/lms/core/amd";
import DisplayManager from "./display";
const forms = /* @__PURE__ */ new WeakMap();
const IMPLICIT_SUBMIT_TYPES = /* @__PURE__ */ new Set([
  "text",
  "search",
  "url",
  "tel",
  "email",
  "password",
  "number",
  "date",
  "month",
  "week",
  "time",
  "datetime-local"
]);
let loadModule = /* @__PURE__ */ __name((specifier) => import(specifier), "loadModule");
function setModuleLoader(loader) {
  loadModule = loader;
}
__name(setModuleLoader, "setModuleLoader");
async function initForm(form) {
  const existing = forms.get(form);
  if (existing) {
    return existing;
  }
  const pending = new Pending("tool_mulib/muform:init");
  try {
    const muform = new MuForm(form);
    forms.set(form, muform);
    await muform.init();
    return muform;
  } finally {
    pending.resolve();
  }
}
__name(initForm, "initForm");
function getForm(form) {
  return forms.get(form);
}
__name(getForm, "getForm");
class MuForm {
  static {
    __name(this, "MuForm");
  }
  /** The form element. */
  form;
  /** Elements indexed by name, in document order. */
  elements = /* @__PURE__ */ new Map();
  /** Display manager, created in init(). */
  display;
  /** Event handlers. */
  handlers = /* @__PURE__ */ new Map();
  /** True after the first failed submission, then every element is validated on blur. */
  submitAttempted = false;
  /** True once a submission left the browser, every later submit event is refused. */
  submitting = false;
  /** Change checker from core, when available. */
  changechecker;
  /**
   * Use initForm() instead.
   *
   * @param form the form element
   */
  constructor(form) {
    this.form = form;
  }
  /**
   * Load element modules, evaluate display rules and attach listeners.
   */
  async init() {
    const wrappers = this.form.querySelectorAll("[data-muform-element]");
    for (const wrapper of wrappers) {
      const type = wrapper.dataset.muformElement;
      const component = wrapper.dataset.muformComponent;
      const name = wrapper.dataset.muformName;
      if (!type || !component || !name) {
        continue;
      }
      const specifier = `@moodle/lms/${component}/muform/element/${type}`;
      const module = await loadModule(specifier);
      this.elements.set(name, new module.default(wrapper, this));
    }
    const rules = JSON.parse(this.form.dataset.muformRules || "[]");
    this.display = new DisplayManager(this.elements, rules);
    this.display.apply();
    this.form.addEventListener("muform:change", (event) => this.onChange(event.detail));
    this.form.addEventListener("submit", (event) => this.onSubmit(event));
    this.form.addEventListener("keydown", (event) => this.onKeyDown(event));
    if (this.form.dataset.muformHasErrors === "1") {
      this.focusFirstError();
    }
    try {
      this.changechecker = await requireAsync("core_form/changechecker");
      this.changechecker.watchForm(this.form);
    } catch {
    }
    this.emit("ready", { form: this });
  }
  /**
   * Value of an element by name.
   *
   * @param name element name
   * @returns the value or null for unknown elements
   */
  getValue(name) {
    return this.elements.get(name)?.getValue() ?? null;
  }
  /**
   * Submit the form as if the first submit button was pressed.
   */
  submit() {
    this.press("submit");
  }
  /**
   * Resubmit without validation so that the server can rebuild the definition.
   */
  reload() {
    this.press("reload");
  }
  /**
   * Cancel editing.
   */
  cancel() {
    this.press("cancel");
  }
  /**
   * Element lost focus, validate it when it was already touched or a submission failed.
   *
   * @param name element name
   */
  touched(name) {
    const element = this.elements.get(name);
    if (!element || !(element.state.touched || this.submitAttempted)) {
      return;
    }
    const errors = element.validate();
    if (errors.join("\n") !== element.state.errors.join("\n")) {
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
  on(event, handler) {
    if (!this.handlers.has(event)) {
      this.handlers.set(event, /* @__PURE__ */ new Set());
    }
    this.handlers.get(event).add(handler);
    return () => this.handlers.get(event)?.delete(handler);
  }
  /**
   * Validate all visible and enabled elements and show their errors.
   *
   * @returns true when everything is valid
   */
  validateAll() {
    let valid = true;
    for (const element of this.elements.values()) {
      const errors = element.validate();
      if (errors.length) {
        valid = false;
      }
      if (errors.join("\n") !== element.state.errors.join("\n")) {
        element.state.errors = errors;
        element.syncUI();
      }
    }
    return valid;
  }
  /**
   * Focus the first element that has errors.
   */
  focusFirstError() {
    for (const element of this.elements.values()) {
      const area = element.wrapper.querySelector(".invalid-feedback, .alert-danger");
      if (element.state.errors.length || area && area.textContent?.trim()) {
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
  press(role) {
    const button = this.form.querySelector(`button[data-muform-role="${role}"]`);
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
  onChange(detail) {
    this.emit("change", detail);
    if (this.display?.dependsOn(detail.name)) {
      const changed = this.display.apply();
      if (changed.length) {
        this.emit("display", { changed });
      }
    }
  }
  /**
   * Enter in a single line input submits through the submit button, browsers would use
   * the first submit button in the form, which may be a reload button such as "Delete row".
   *
   * @param event the keydown event
   */
  onKeyDown(event) {
    if (event.key !== "Enter" || event.defaultPrevented || event.isComposing) {
      return;
    }
    const target = event.target;
    if (!(target instanceof HTMLInputElement) || !IMPLICIT_SUBMIT_TYPES.has(target.type)) {
      return;
    }
    const first = this.form.querySelector('button[type="submit"]');
    const submit = this.form.querySelector('button[data-muform-role="submit"]');
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
  onSubmit(event) {
    if (this.submitting) {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }
    const submitter = event.submitter;
    const role = submitter?.dataset.muformRole ?? "submit";
    if (role === "submit") {
      this.submitAttempted = true;
      if (!this.validateAll()) {
        event.preventDefault();
        this.emit("invalid", { form: this });
        this.focusFirstError();
        return;
      }
    }
    if (submitter?.dataset.muformDownload) {
      return;
    }
    this.submitting = true;
    this.emit("submit", { form: this, role });
    this.changechecker?.markFormSubmitted(this.form);
    window.setTimeout(() => {
      for (const button of this.form.querySelectorAll('button[type="submit"]')) {
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
  emit(event, detail) {
    for (const handler of this.handlers.get(event) ?? []) {
      handler(detail);
    }
  }
}
export {
  MuForm,
  getForm,
  initForm,
  setModuleLoader
};
//# sourceMappingURL=form.dev.js.map
