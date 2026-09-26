var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the editor element.
 *
 * The site editor (TinyMCE) attaches itself to the textarea from the collected page
 * JavaScript. Tiny only writes back to the textarea on blur and jQuery submit, so this
 * module saves the editor content before values are read, validated or submitted.
 *
 * @module     tool_mulib/muform/element/editor
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { requireAsync } from "@moodle/lms/core/amd";
import NativeElement from "../native";
class editor_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** The textarea, null when frozen. */
  textarea;
  /** Tiny module once loaded, null when Tiny is not on the page. */
  tiny = null;
  /**
   * Save editor content before the form submits.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    this.textarea = wrapper.querySelector("textarea");
    if (!this.textarea) {
      return;
    }
    form.on("submit", () => this.saveEditor());
    void this.loadTiny();
  }
  /**
   * The textarea and the format select, never the DOM Tiny injects.
   *
   * @returns controls
   */
  controls() {
    const result = [];
    if (this.textarea) {
      result.push(this.textarea);
    }
    const select = this.wrapper.querySelector("select");
    if (select) {
      result.push(select);
    }
    return result;
  }
  /**
   * Current text after saving the editor.
   *
   * @returns the text or null when frozen
   */
  getValue() {
    this.saveEditor();
    return this.textarea ? this.textarea.value : null;
  }
  /**
   * Native validation of the textarea after saving the editor.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    this.saveEditor();
    return super.validate();
  }
  /**
   * Disable the editor together with the textarea.
   */
  syncUI() {
    super.syncUI();
    const instance = this.getInstance();
    instance?.mode.set(this.state.disabled ? "readonly" : "design");
  }
  /**
   * Copy the editor content into the textarea.
   */
  saveEditor() {
    this.getInstance()?.save();
  }
  /**
   * Tiny instance attached to the textarea, if any.
   *
   * @returns the instance or null
   */
  getInstance() {
    if (!this.tiny || !this.textarea) {
      return null;
    }
    return this.tiny.getInstanceForElement(this.textarea);
  }
  /**
   * Load the Tiny module when Tiny is used on this page.
   */
  async loadTiny() {
    try {
      this.tiny = await requireAsync("editor_tiny/editor");
    } catch {
      this.tiny = null;
    }
    this.syncUI();
  }
}
export {
  editor_default as default
};
//# sourceMappingURL=editor.dev.js.map
