var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the collapsible section element, a details element with children of their own.
 *
 * Expanded is the default, a state collapsed by the user is kept in session storage under the state key
 * of the section. Sections rendered with errors stay expanded, the form expands sections with errors
 * after a failed submission.
 *
 * @module     tool_mulib/muform/element/collapsible
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Element from "../element";
const STORAGE_PREFIX = "tool_mulib-collapsed-";
class collapsible_default extends Element {
  static {
    __name(this, "default");
  }
  /**
   * Apply the remembered state and remember states changed by the user.
   *
   * @param wrapper the details element
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    if (!(wrapper instanceof HTMLDetailsElement)) {
      return;
    }
    if (wrapper.dataset.muformCollapsibleErrors !== "1" && this.isStoredCollapsed()) {
      wrapper.open = false;
    }
    wrapper.querySelector(":scope > summary")?.addEventListener("click", (event) => {
      if (!event.defaultPrevented) {
        this.store(wrapper.open);
      }
    });
    wrapper.addEventListener("muform:collapsible", (event) => {
      const open = event.detail.open;
      wrapper.open = open;
      this.store(!open);
    });
  }
  /**
   * Was the section collapsed by the user? Storage may be unavailable, everything is expanded then.
   *
   * @returns true when collapsed
   */
  isStoredCollapsed() {
    const key = this.wrapper.dataset.muformStatekey;
    if (!key) {
      return false;
    }
    try {
      return window.sessionStorage.getItem(STORAGE_PREFIX + key) === "1";
    } catch {
      return false;
    }
  }
  /**
   * Remember the state, expanded is the default and is not stored.
   *
   * @param collapsed new state
   */
  store(collapsed) {
    const key = this.wrapper.dataset.muformStatekey;
    if (!key) {
      return;
    }
    try {
      if (collapsed) {
        window.sessionStorage.setItem(STORAGE_PREFIX + key, "1");
      } else {
        window.sessionStorage.removeItem(STORAGE_PREFIX + key);
      }
    } catch {
    }
  }
}
export {
  collapsible_default as default
};
//# sourceMappingURL=collapsible.dev.js.map
