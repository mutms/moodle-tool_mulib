var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the autocompletemany element: replaces the fallback text input
 * with the token field island; the island renders the hidden input the form posts.
 *
 * @module     tool_mulib/muform/element/autocompletemany
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import { getString } from "@moodle/lms/core/stringUtils";
import ManyPicker from "../autocompletemanypicker";
import NativeElement from "../native";
class autocompletemany_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** Island container, null when frozen. */
  container;
  /** Field name posted. */
  fieldname;
  /** Field id for the label. */
  fieldid;
  /** Required flag of the server rendered input. */
  required;
  /** Placeholder of the server rendered input. */
  placeholder;
  /** Current selection, kept across remounts. */
  selected = [];
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
    this.container = wrapper.querySelector("[data-muform-autocompletemany]");
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
      this.selected = JSON.parse(this.container.dataset.muformAutocompleteSelected ?? "[]");
    } catch {
      this.selected = [];
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
   * Selected values.
   *
   * @returns list of values, empty when nothing is selected
   */
  getValue() {
    if (!this.container) {
      return null;
    }
    return this.selected.map((item) => item.value);
  }
  /**
   * Required check, the server validates the values themselves.
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
    let source = { "class": "", args: [] };
    try {
      source = JSON.parse(container.dataset.muformAutocompleteSource ?? "{}");
    } catch {
    }
    this.unmountPicker?.();
    this.pickerDisabled = this.state.disabled;
    this.unmountPicker = mountReactApp(container, ManyPicker, {
      url: container.dataset.muformAutocompleteUrl ?? "",
      source,
      initial: this.selected,
      name: this.fieldname,
      id: this.fieldid,
      placeholder: this.placeholder,
      disabled: this.state.disabled,
      strings: this.strings,
      onChange: /* @__PURE__ */ __name((values) => {
        this.selected = values.map((value) => {
          const known = this.selected.find((item) => item.value === value);
          return known ?? { value, label: value, error: null };
        });
        if (this.state.errors.length) {
          this.state.errors = [];
          this.syncUI();
        }
        this.emitChange();
      }, "onChange")
    }, { id: `muform-autocompletemany-${this.name}` });
  }
}
export {
  autocompletemany_default as default
};
//# sourceMappingURL=autocompletemany.dev.js.map
