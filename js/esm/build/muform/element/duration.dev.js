var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the duration element.
 *
 * Native number inputs for the configured units, the value is their sum in seconds. The server
 * marks a required group with data-muform-required instead of marking every input.
 *
 * @module     tool_mulib/muform/element/duration
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import NativeElement from "../native";
const SECONDS = { w: 604800, d: 86400, h: 3600, i: 60, s: 1 };
class duration_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /**
   * Sum of the unit inputs in seconds, '0' when everything is empty.
   *
   * @returns seconds as text
   */
  getValue() {
    let total = 0;
    for (const input of this.wrapper.querySelectorAll("input[data-muform-unit]")) {
      const count = Number(input.value);
      if (input.value === "" || !Number.isFinite(count)) {
        continue;
      }
      total += count * (SECONDS[input.dataset.muformUnit ?? ""] ?? 0);
    }
    return String(total);
  }
  /**
   * Native constraints of the inputs, then the required check of the whole group.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    const errors = super.validate();
    if (errors.length === 0 && !this.state.hidden && !this.state.disabled) {
      const required = this.wrapper.querySelector("[data-muform-required]");
      if (required && this.getValue() === "0") {
        errors.push(this.getHint("required"));
      }
    }
    return errors;
  }
}
export {
  duration_default as default
};
//# sourceMappingURL=duration.dev.js.map
