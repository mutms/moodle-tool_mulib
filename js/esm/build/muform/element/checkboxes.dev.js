var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the checkboxes element.
 *
 * Native controls do the rest, only the required check is on the group
 * because no single checkbox can carry the required attribute.
 *
 * @module     tool_mulib/muform/element/checkboxes
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import NativeElement from "../native";
class checkboxes_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /**
   * Native constraints, then the required check of the whole group.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    const errors = super.validate();
    if (errors.length === 0 && !this.state.hidden && !this.state.disabled) {
      const required = this.wrapper.querySelector("[data-muform-required]");
      const value = this.getValue();
      if (required && Array.isArray(value) && value.length === 0) {
        errors.push(this.getHint("required"));
      }
    }
    return errors;
  }
}
export {
  checkboxes_default as default
};
//# sourceMappingURL=checkboxes.dev.js.map
