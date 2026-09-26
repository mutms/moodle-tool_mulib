import d from"../native";/**
 * Browser side of the number element.
 *
 * The input is a text input with numeric keyboard hints, number inputs change values
 * on mouse wheel scrolling. The pattern attribute checks the format and decimal places
 * natively, the range comes from data attributes, the server checks everything again.
 *
 * @module     tool_mulib/muform/element/number
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */class m extends d{validate(){const e=super.validate();if(e.length||this.state.hidden||this.state.disabled)return e;const t=this.wrapper.querySelector("input[inputmode]"),i=t?.value.trim()??"";if(!t||i==="")return e;const r=i.replace(",","."),n=Number(r),s=t.dataset.muformMin,a=t.dataset.muformMax;return(!/^-?(\d+(\.\d*)?|\.\d+)$/.test(r)||!Number.isFinite(n)||s!==void 0&&n<Number(s)||a!==void 0&&n>Number(a))&&e.push(this.getHint("invalid")),e}}export{m as default};
