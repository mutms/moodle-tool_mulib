var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the filemanager element.
 *
 * Core's file manager widget owns everything inside the wrapper and boots itself
 * from the collected page JavaScript, so this module only reports the draft item id
 * and keeps the orchestrator away from the widget's controls.
 *
 * @module     tool_mulib/muform/element/filemanager
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import NativeElement from "../native";
class filemanager_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /**
   * The widget's inputs and buttons are not form controls of this element.
   *
   * @returns nothing
   */
  controls() {
    return [];
  }
  /**
   * Draft item id from the hidden input.
   *
   * @returns the id or null when frozen
   */
  getValue() {
    const hidden = this.wrapper.querySelector('input[type="hidden"][name]');
    return hidden ? hidden.value : null;
  }
  /**
   * Validation happens on the server only.
   *
   * @returns no errors
   */
  validate() {
    return [];
  }
  /**
   * Lock the widget visually while disabled, the hidden id is then not posted.
   */
  syncUI() {
    super.syncUI();
    const widget = this.wrapper.querySelector("[data-muform-filemanager]");
    widget?.classList.toggle("muform-filemanager-disabled", this.state.disabled);
    widget?.setAttribute("aria-disabled", this.state.disabled ? "true" : "false");
  }
}
export {
  filemanager_default as default
};
//# sourceMappingURL=filemanager.dev.js.map
