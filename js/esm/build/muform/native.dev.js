var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Element with native form controls inside the standard wrapper.
 *
 * Values, constraint validation, disabling and error display work for any
 * combination of inputs, selects, textareas and buttons, so the tool_mulib
 * element modules do not override anything.
 *
 * @module     tool_mulib/muform/native
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Element from "./element";
class NativeElement extends Element {
  static {
    __name(this, "NativeElement");
  }
  /**
   * Listen for value changes of native controls.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    wrapper.addEventListener("input", () => this.onInput());
    wrapper.addEventListener("change", () => this.onInput());
  }
  /**
   * Native controls inside the wrapper, without the hidden carriers of empty values.
   *
   * @returns controls in document order
   */
  controls() {
    const selector = "input, select, textarea, button";
    const all = Array.from(this.wrapper.querySelectorAll(selector));
    if (this.wrapper.matches(selector)) {
      all.unshift(this.wrapper);
    }
    return all.filter((control) => control.type !== "hidden");
  }
  /**
   * Value derived from the control types: checkbox groups and multiple selects give lists,
   * a single checkbox gives '1' or '0', radios give the checked value or null.
   *
   * @returns the normalised value
   */
  getValue() {
    const controls = this.controls().filter((control) => control.type !== "submit" && control.type !== "button");
    if (controls.length === 0) {
      return null;
    }
    const first = controls[0];
    if (first instanceof HTMLSelectElement) {
      if (first.multiple) {
        return Array.from(first.selectedOptions).map((option) => option.value);
      }
      return first.value;
    }
    if (first.type === "radio") {
      const checked = controls.find((control) => control.checked);
      return checked ? checked.value : null;
    }
    if (first.type === "checkbox") {
      if (controls.length === 1 && !first.name.endsWith("[]")) {
        return first.checked ? "1" : "0";
      }
      return controls.filter((control) => control.checked).map((control) => control.value);
    }
    return first.value;
  }
  /**
   * Native constraint validation of every control, hidden and disabled elements are always valid.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    if (this.state.hidden || this.state.disabled) {
      return [];
    }
    const errors = [];
    for (const control of this.controls()) {
      if (control.type === "submit" || control.type === "button" || control.checkValidity()) {
        continue;
      }
      const message = control.validity.valueMissing ? this.getHint("required") : this.getHint("invalid");
      if (!errors.includes(message)) {
        errors.push(message);
      }
    }
    return errors;
  }
  /**
   * Disable controls and show errors in the error area of the standard wrapper.
   */
  syncUI() {
    super.syncUI();
    const { state } = this;
    const invalid = state.errors.length > 0;
    for (const control of this.controls()) {
      control.disabled = state.disabled;
      if (control.type === "submit" || control.type === "button") {
        continue;
      }
      control.classList.toggle("is-invalid", invalid);
      if (invalid) {
        control.setAttribute("aria-invalid", "true");
      } else {
        control.removeAttribute("aria-invalid");
      }
    }
    for (const carrier of this.wrapper.querySelectorAll('input[type="hidden"]')) {
      carrier.disabled = state.disabled;
    }
    const area = this.wrapper.querySelector(".invalid-feedback");
    if (area) {
      area.replaceChildren(...state.errors.map((error) => {
        const div = document.createElement("div");
        div.textContent = error;
        return div;
      }));
      area.style.display = invalid ? "block" : "";
    }
  }
  /**
   * Focus the first enabled control, for radios the checked one.
   */
  focus() {
    const controls = this.controls().filter((control) => !control.disabled);
    const checked = controls.find((control) => control.checked);
    (checked ?? controls[0])?.focus();
  }
  /**
   * Text shown for missing or invalid values, rendered by the wrapper template.
   *
   * @param kind required or invalid
   * @returns the hint
   */
  getHint(kind) {
    const holder = this.wrapper.querySelector("[data-muform-required-hint]") ?? this.wrapper;
    const hint = kind === "required" ? holder.dataset.muformRequiredHint : holder.dataset.muformInvalidHint;
    return hint || (kind === "required" ? "Required" : "Error");
  }
  /**
   * Any change clears client side errors and notifies the form.
   */
  onInput() {
    if (this.state.errors.length) {
      this.state.errors = [];
      this.syncUI();
    }
    this.emitChange();
  }
}
export {
  NativeElement as default
};
//# sourceMappingURL=native.dev.js.map
