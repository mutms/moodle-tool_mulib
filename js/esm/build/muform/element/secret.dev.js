var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the secret element: a show/hide toggle for the text being typed
 * and the clear checkbox disabling the input. The current value never exists in the page.
 *
 * Intentionally separate from the sharedkey element.
 *
 * @module     tool_mulib/muform/element/secret
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { getString } from "@moodle/lms/core/stringUtils";
import NativeElement from "../native";
class secret_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** Masked text input, null when frozen. */
  input;
  /** Optional clear checkbox. */
  clear;
  /** Show/hide button, created once strings are loaded. */
  button = null;
  /** Placeholder rendered by the server, dots when a value exists. */
  placeholder;
  /** Placeholder while unmasked, loaded with the strings. */
  typenew = "";
  /**
   * Wire the toggle and the clear checkbox.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    this.input = wrapper.querySelector("input.muform-secret-masked");
    this.clear = wrapper.querySelector('input[type="checkbox"]');
    this.placeholder = this.input?.placeholder ?? "";
    if (!this.input) {
      return;
    }
    const { input, clear } = this;
    if (clear) {
      clear.addEventListener("change", () => {
        if (clear.checked) {
          input.value = "";
          this.setMasked(true);
        }
        this.syncUI();
      });
    }
    void this.addToggle(wrapper, input);
  }
  /**
   * Clearing disables the input and the toggle, the placeholder follows the masked state.
   */
  syncUI() {
    super.syncUI();
    if (!this.input || this.state.disabled) {
      return;
    }
    const clearing = this.clear?.checked ?? false;
    this.input.disabled = clearing;
    if (this.button) {
      this.button.disabled = clearing;
    }
    const masked = this.input.classList.contains("muform-secret-masked");
    if (clearing) {
      this.input.placeholder = "";
    } else {
      this.input.placeholder = masked ? this.placeholder : this.typenew;
    }
  }
  /**
   * Mask or unmask the typed text.
   *
   * @param masked true to mask
   */
  setMasked(masked) {
    if (!this.input) {
      return;
    }
    this.input.classList.toggle("muform-secret-masked", masked);
    if (this.button) {
      this.button.textContent = masked ? this.button.dataset.show ?? "" : this.button.dataset.hide ?? "";
    }
  }
  /**
   * Add the show/hide button after the input.
   *
   * @param wrapper outer element
   * @param input the masked input
   */
  async addToggle(wrapper, input) {
    const actions = wrapper.querySelector("[data-muform-secret-actions]");
    if (!actions) {
      return;
    }
    const [show, hide, typenew] = await Promise.all([
      getString("muform_show", "tool_mulib"),
      getString("muform_hide", "tool_mulib"),
      getString("muform_typenewvalue", "tool_mulib")
    ]);
    this.typenew = typenew;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-outline-secondary";
    button.dataset.show = show;
    button.dataset.hide = hide;
    button.setAttribute("aria-controls", input.id);
    button.setAttribute("aria-describedby", `${input.id}_label`);
    button.addEventListener("click", () => {
      const masked = !input.classList.contains("muform-secret-masked");
      this.setMasked(masked);
      this.syncUI();
    });
    this.button = button;
    actions.replaceChildren(button);
    this.setMasked(input.classList.contains("muform-secret-masked"));
    this.syncUI();
  }
}
export {
  secret_default as default
};
//# sourceMappingURL=secret.dev.js.map
