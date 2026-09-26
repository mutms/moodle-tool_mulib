var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Element contract of muform.
 *
 * An element owns one wrapper element and a small cosmetic state. The orchestrator and
 * the display manager never touch the DOM inside the wrapper: they read values through
 * getValue(), write the state and call syncUI(), which projects the state onto the DOM.
 * Anything may be rendered inside the wrapper, custom elements override what they need.
 *
 * @module     tool_mulib/muform/element
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const FOCUSABLE = 'input:not([type="hidden"]), select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])';
class Element {
  static {
    __name(this, "Element");
  }
  /** Element name, the same as in the PHP definition. */
  name;
  /** Outer element with data-muform-* attributes. */
  wrapper;
  /** Cosmetic state, written by the orchestrator, projected by syncUI(). */
  state;
  /** The form the element belongs to. */
  form;
  /**
   * Read the initial state from the server rendered markup.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    this.wrapper = wrapper;
    this.form = form;
    this.name = wrapper.dataset.muformName ?? "";
    this.state = {
      hidden: wrapper.dataset.muformHidden === "1",
      disabled: wrapper.dataset.muformDisabled === "1",
      // Server side errors survive the first syncUI() of elements that render during init.
      errors: Element.readErrors(wrapper),
      touched: false
    };
    wrapper.addEventListener("focusout", (event) => {
      if (!this.state.touched) {
        this.state.touched = true;
      }
      const next = event.relatedTarget;
      if (next instanceof HTMLButtonElement && next.form === wrapper.closest("form")) {
        return;
      }
      this.form.touched(this.name);
    });
  }
  /**
   * Errors rendered by the server in the error area of the standard wrapper.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @returns error messages
   */
  static readErrors(wrapper) {
    const areas = Array.from(wrapper.querySelectorAll(".invalid-feedback"));
    const area = areas.find((candidate) => candidate.closest("[data-muform-name]") === wrapper);
    if (!area) {
      return [];
    }
    return Array.from(area.children).map((child) => (child.textContent ?? "").trim()).filter((text) => text !== "");
  }
  /**
   * Current value normalised for display rules and validators.
   *
   * @returns null when the element has no value
   */
  getValue() {
    return null;
  }
  /**
   * Client side validation, called by the orchestrator only.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    return [];
  }
  /**
   * Project the state onto the DOM, must be idempotent.
   * The base writes only the wrapper attributes.
   */
  syncUI() {
    const { wrapper, state } = this;
    if (state.hidden) {
      wrapper.hidden = true;
      wrapper.dataset.muformHidden = "1";
    } else {
      wrapper.hidden = false;
      delete wrapper.dataset.muformHidden;
    }
    if (state.disabled) {
      wrapper.dataset.muformDisabled = "1";
    } else {
      delete wrapper.dataset.muformDisabled;
    }
  }
  /**
   * Move keyboard focus into the element.
   */
  focus() {
    const target = this.wrapper.querySelector(FOCUSABLE);
    target?.focus();
  }
  /**
   * Release resources, called when the form goes away.
   */
  destroy() {
  }
  /**
   * Tell the form that the value changed.
   */
  emitChange() {
    const detail = { name: this.name, value: this.getValue() };
    this.wrapper.dispatchEvent(new CustomEvent("muform:change", { bubbles: true, detail }));
  }
}
export {
  Element as default
};
//# sourceMappingURL=element.dev.js.map
