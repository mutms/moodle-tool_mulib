import i from"../native";/**
 * Browser side of the duration element.
 *
 * Native number inputs for the configured units, the value is their sum in seconds. The server
 * marks a required group with data-muform-required instead of marking every input.
 *
 * @module     tool_mulib/muform/element/duration
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u={w:604800,d:86400,h:3600,i:60,s:1};class s extends i{getValue(){let e=0;for(const t of this.wrapper.querySelectorAll("input[data-muform-unit]")){const r=Number(t.value);t.value===""||!Number.isFinite(r)||(e+=r*(u[t.dataset.muformUnit??""]??0))}return String(e)}validate(){const e=super.validate();return e.length===0&&!this.state.hidden&&!this.state.disabled&&this.wrapper.querySelector("[data-muform-required]")&&this.getValue()==="0"&&e.push(this.getHint("required")),e}}export{s as default};
