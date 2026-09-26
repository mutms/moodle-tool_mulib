import o from"../native";/**
 * Browser side of the dateinterval element.
 *
 * Native number inputs for the configured units, the value is the ISO 8601 duration they describe.
 * The server marks a required group with data-muform-required instead of marking every input.
 *
 * @module     tool_mulib/muform/element/dateinterval
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s={y:"Y",m:"M",w:"W",d:"D"},u={h:"H",i:"M",s:"S"};class l extends o{getValue(){let e="",t="";for(const n of this.wrapper.querySelectorAll("input[data-muform-unit]")){const r=n.dataset.muformUnit??"",i=Number(n.value);n.value===""||!Number.isInteger(i)||i<=0||(r in s?e+=`${i}${s[r]}`:r in u&&(t+=`${i}${u[r]}`))}return e===""&&t===""?null:`P${e}${t===""?"":`T${t}`}`}validate(){const e=super.validate();return e.length===0&&!this.state.hidden&&!this.state.disabled&&this.wrapper.querySelector("[data-muform-required]")&&this.getValue()===null&&e.push(this.getHint("required")),e}}export{l as default};
