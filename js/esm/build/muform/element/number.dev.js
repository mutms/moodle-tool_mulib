var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the number element.
 *
 * The input is a text input with numeric keyboard hints, number inputs change values
 * on mouse wheel scrolling. The pattern attribute checks the format and decimal places
 * natively, the range comes from data attributes, the server checks everything again.
 *
 * @module     tool_mulib/muform/element/number
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import NativeElement from "../native";
class number_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /**
   * Native constraints, then the number format and the range.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    const errors = super.validate();
    if (errors.length || this.state.hidden || this.state.disabled) {
      return errors;
    }
    const input = this.wrapper.querySelector("input[inputmode]");
    const text = input?.value.trim() ?? "";
    if (!input || text === "") {
      return errors;
    }
    const normalised = text.replace(",", ".");
    const number = Number(normalised);
    const min = input.dataset.muformMin;
    const max = input.dataset.muformMax;
    if (!/^-?(\d+(\.\d*)?|\.\d+)$/.test(normalised) || !Number.isFinite(number) || min !== void 0 && number < Number(min) || max !== void 0 && number > Number(max)) {
      errors.push(this.getHint("invalid"));
    }
    return errors;
  }
}
export {
  number_default as default
};
//# sourceMappingURL=number.dev.js.map
