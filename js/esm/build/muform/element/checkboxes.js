import i from"../native";/**
 * Browser side of the checkboxes element.
 *
 * Native controls do the rest, only the required check is on the group
 * because no single checkbox can carry the required attribute.
 *
 * @module     tool_mulib/muform/element/checkboxes
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */class a extends i{validate(){const e=super.validate();if(e.length===0&&!this.state.hidden&&!this.state.disabled){const r=this.wrapper.querySelector("[data-muform-required]"),t=this.getValue();r&&Array.isArray(t)&&t.length===0&&e.push(this.getHint("required"))}return e}}export{a as default};
