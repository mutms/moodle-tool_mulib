var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Display manager: hides and disables elements according to rules from the server.
 *
 * The logic mirrors tool_mulib\muform\util\display_manager exactly. Rules depend on
 * element values only, they are evaluated in a single pass in rule order, results of
 * rules for the same target are ORed, and the state of a section or button row cascades
 * to the elements inside it. Hiding and disabling are cosmetic, the server ignores them.
 *
 * @module     tool_mulib/muform/display
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function matches(op, depvalue, value) {
  switch (op) {
    case "eq":
      if (Array.isArray(depvalue)) {
        return depvalue.includes(String(value));
      }
      return depvalue === String(value);
    case "neq":
      return !matches("eq", depvalue, value);
    case "in": {
      const list = (Array.isArray(value) ? value : [value]).map(String);
      if (Array.isArray(depvalue)) {
        return depvalue.some((item) => list.includes(item));
      }
      return depvalue !== null && list.includes(depvalue);
    }
    case "notin":
      return !matches("in", depvalue, value);
    case "checked":
      return depvalue === "1";
    case "notchecked":
      return depvalue !== "1";
    case "empty":
      return depvalue === null || depvalue === "" || Array.isArray(depvalue) && depvalue.length === 0;
    case "notempty":
      return !matches("empty", depvalue, null);
    default:
      throw new Error(`Invalid display rule operator: ${op}`);
  }
}
__name(matches, "matches");
class DisplayManager {
  static {
    __name(this, "DisplayManager");
  }
  /** Elements indexed by name. */
  elements;
  /** Rules from the server. */
  rules;
  /** Names of elements other elements depend on. */
  dependencies;
  /**
   * Prepare the manager, call apply() to evaluate.
   *
   * @param elements elements indexed by name
   * @param rules rules from data-muform-rules
   */
  constructor(elements, rules) {
    this.elements = elements;
    this.rules = rules;
    this.dependencies = new Set(rules.map((rule) => rule.dep));
  }
  /**
   * Does a change of the given element affect any rule?
   *
   * @param name element name
   * @returns true when apply() should run
   */
  dependsOn(name) {
    return this.dependencies.has(name);
  }
  /**
   * Evaluate all rules and update element states, returns names of elements whose state changed.
   *
   * @returns changed element names
   */
  apply() {
    const hidden = /* @__PURE__ */ new Set();
    const disabled = /* @__PURE__ */ new Set();
    for (const rule of this.rules) {
      const dependency = this.elements.get(rule.dep);
      if (!dependency || !this.elements.has(rule.target)) {
        continue;
      }
      if (matches(rule.op, dependency.getValue(), rule.value)) {
        (rule.action === "hide" ? hidden : disabled).add(rule.target);
      }
    }
    const changed = [];
    for (const [name, element] of this.elements) {
      let isHidden = false;
      let isDisabled = false;
      let current = name;
      while (current !== void 0) {
        isHidden = isHidden || hidden.has(current);
        isDisabled = isDisabled || disabled.has(current);
        current = this.parentOf(current);
      }
      if (element.state.hidden !== isHidden || element.state.disabled !== isDisabled) {
        element.state.hidden = isHidden;
        element.state.disabled = isDisabled;
        element.syncUI();
        changed.push(name);
      }
    }
    return changed;
  }
  /**
   * Name of the closest enclosing element, sections and button rows contain other elements.
   *
   * @param name element name
   * @returns parent name or undefined for top level elements
   */
  parentOf(name) {
    const element = this.elements.get(name);
    const parent = element?.wrapper.parentElement?.closest("[data-muform-element]");
    return parent?.dataset.muformName;
  }
}
export {
  DisplayManager as default,
  matches
};
//# sourceMappingURL=display.dev.js.map
