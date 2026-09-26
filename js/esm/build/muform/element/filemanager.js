import t from"../native";/**
 * Browser side of the filemanager element.
 *
 * Core's file manager widget owns everything inside the wrapper and boots itself
 * from the collected page JavaScript, so this module only reports the draft item id
 * and keeps the orchestrator away from the widget's controls.
 *
 * @module     tool_mulib/muform/element/filemanager
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */class a extends t{controls(){return[]}getValue(){const e=this.wrapper.querySelector('input[type="hidden"][name]');return e?e.value:null}validate(){return[]}syncUI(){super.syncUI();const e=this.wrapper.querySelector("[data-muform-filemanager]");e?.classList.toggle("muform-filemanager-disabled",this.state.disabled),e?.setAttribute("aria-disabled",this.state.disabled?"true":"false")}}export{a as default};
