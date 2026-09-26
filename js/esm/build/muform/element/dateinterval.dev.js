var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the dateinterval element.
 *
 * Native number inputs for the configured units, the value is the ISO 8601 duration they describe.
 * The server marks a required group with data-muform-required instead of marking every input.
 *
 * @module     tool_mulib/muform/element/dateinterval
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import NativeElement from "../native";
const DATE = { y: "Y", m: "M", w: "W", d: "D" };
const TIME = { h: "H", i: "M", s: "S" };
class dateinterval_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /**
   * Canonical ISO 8601 duration of the unit inputs, null when everything is empty or zero.
   *
   * @returns the interval string
   */
  getValue() {
    let date = "";
    let time = "";
    for (const input of this.wrapper.querySelectorAll("input[data-muform-unit]")) {
      const unit = input.dataset.muformUnit ?? "";
      const count = Number(input.value);
      if (input.value === "" || !Number.isInteger(count) || count <= 0) {
        continue;
      }
      if (unit in DATE) {
        date += `${count}${DATE[unit]}`;
      } else if (unit in TIME) {
        time += `${count}${TIME[unit]}`;
      }
    }
    if (date === "" && time === "") {
      return null;
    }
    return `P${date}${time === "" ? "" : `T${time}`}`;
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
      if (required && this.getValue() === null) {
        errors.push(this.getHint("required"));
      }
    }
    return errors;
  }
}
export {
  dateinterval_default as default
};
//# sourceMappingURL=dateinterval.dev.js.map
