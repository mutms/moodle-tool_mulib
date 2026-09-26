import{requireAsync as n}from"@moodle/lms/core/amd";import i from"../native";/**
 * Browser side of the editor element.
 *
 * The site editor (TinyMCE) attaches itself to the textarea from the collected page
 * JavaScript. Tiny only writes back to the textarea on blur and jQuery submit, so this
 * module saves the editor content before values are read, validated or submitted.
 *
 * @module     tool_mulib/muform/element/editor
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */class s extends i{textarea;tiny=null;constructor(e,t){super(e,t),this.textarea=e.querySelector("textarea"),this.textarea&&(t.on("submit",()=>this.saveEditor()),this.loadTiny())}controls(){const e=[];this.textarea&&e.push(this.textarea);const t=this.wrapper.querySelector("select");return t&&e.push(t),e}getValue(){return this.saveEditor(),this.textarea?this.textarea.value:null}validate(){return this.saveEditor(),super.validate()}syncUI(){super.syncUI(),this.getInstance()?.mode.set(this.state.disabled?"readonly":"design")}saveEditor(){this.getInstance()?.save()}getInstance(){return!this.tiny||!this.textarea?null:this.tiny.getInstanceForElement(this.textarea)}async loadTiny(){try{this.tiny=await n("editor_tiny/editor")}catch{this.tiny=null}this.syncUI()}}export{s as default};
