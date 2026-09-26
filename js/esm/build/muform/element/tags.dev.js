var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the tags element: replaces the fallback text input with the
 * tag field island; the island renders the hidden input the form posts.
 *
 * @module     tool_mulib/muform/element/tags
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import { getString } from "@moodle/lms/core/stringUtils";
import TagsPicker from "../tagspicker";
import NativeElement from "../native";
class tags_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** Island container, null when frozen or tagging is disabled. */
  container;
  /** Field name posted. */
  fieldname;
  /** Field id for the label. */
  fieldid;
  /** Required flag of the server rendered input. */
  required;
  /** Placeholder of the server rendered input. */
  placeholder;
  /** Current tags, kept across remounts. */
  tags = [];
  /** Unmount function of the island. */
  unmountPicker = null;
  /** Disabled state the island was mounted with. */
  pickerDisabled = null;
  /** Localised strings, loaded once. */
  strings = null;
  /**
   * Read the server rendered state and mount the island.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    this.container = wrapper.querySelector("[data-muform-tags]");
    const input = this.container?.querySelector('input[type="text"]') ?? null;
    this.fieldname = input?.name ?? "";
    this.fieldid = input?.id ?? "";
    this.required = input?.required ?? false;
    this.placeholder = input?.placeholder ?? "";
    if (!this.container || !input) {
      this.container = null;
      return;
    }
    try {
      this.tags = JSON.parse(this.container.dataset.muformTagsSelected ?? "[]");
    } catch {
      this.tags = [];
    }
    void this.mountPicker();
  }
  /**
   * The island owns its inputs.
   *
   * @returns nothing
   */
  controls() {
    return [];
  }
  /**
   * Entered tag names.
   *
   * @returns list of names, empty when there are none
   */
  getValue() {
    if (!this.container) {
      return null;
    }
    return this.tags.map((tag) => tag.name);
  }
  /**
   * Required check, the server validates the names themselves.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    if (this.state.hidden || this.state.disabled || !this.required) {
      return [];
    }
    return this.getValue().length ? [] : [this.getHint("required")];
  }
  /**
   * Remount the island when the disabled state changes.
   */
  syncUI() {
    super.syncUI();
    if (this.strings && this.pickerDisabled !== this.state.disabled) {
      this.renderPicker();
    }
  }
  /**
   * Unmount the island.
   */
  destroy() {
    this.unmountPicker?.();
    this.unmountPicker = null;
  }
  /**
   * Focus the combobox.
   */
  focus() {
    this.container?.querySelector('input[role="combobox"]')?.focus();
  }
  /**
   * Load strings, drop the fallback markup and mount.
   */
  async mountPicker() {
    const [noresults, searching, toomanyresults, remove, close] = await Promise.all([
      getString("muform_noresults", "tool_mulib"),
      getString("muform_searching", "tool_mulib"),
      getString("muform_toomanyresults", "tool_mulib"),
      getString("muform_remove", "tool_mulib"),
      getString("closebuttontitle", "core")
    ]);
    this.strings = { noresults, searching, toomanyresults, remove, close };
    this.container?.replaceChildren();
    this.renderPicker();
  }
  /**
   * Mount or remount the island with the current state.
   */
  renderPicker() {
    const container = this.container;
    if (!container || !this.strings) {
      return;
    }
    let area = { "class": "", args: [] };
    try {
      area = JSON.parse(container.dataset.muformTagsArea ?? "{}");
    } catch {
    }
    this.unmountPicker?.();
    this.pickerDisabled = this.state.disabled;
    this.unmountPicker = mountReactApp(container, TagsPicker, {
      area,
      initial: this.tags,
      suggest: container.dataset.muformTagsSuggest === "1",
      standardonly: container.dataset.muformTagsStandardonly === "1",
      name: this.fieldname,
      id: this.fieldid,
      placeholder: this.placeholder,
      disabled: this.state.disabled,
      strings: this.strings,
      onChange: /* @__PURE__ */ __name((names) => {
        this.tags = names.map((name) => this.tags.find((tag) => tag.name === name) ?? { name, error: null });
        if (this.state.errors.length) {
          this.state.errors = [];
          this.syncUI();
        }
        this.emitChange();
      }, "onChange")
    }, { id: `muform-tags-${this.name}` });
  }
}
export {
  tags_default as default
};
//# sourceMappingURL=tags.dev.js.map
