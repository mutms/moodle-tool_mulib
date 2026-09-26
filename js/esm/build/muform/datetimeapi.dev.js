var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Client of the datetime normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language and display format it was rendered with
 * as data attributes, the endpoint answers with the three representations of the date.
 *
 * @module     tool_mulib/muform/datetimeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
import Pending from "@moodle/lms/core/pending";
async function normalise(input, text) {
  const pending = new Pending("tool_mulib/muform:datetime");
  try {
    const body = {
      text,
      timezone: input.dataset.muformDatetimeTimezone ?? "",
      lang: input.dataset.muformDatetimeLang ?? "",
      format: input.dataset.muformDatetimeFormat ?? ""
    };
    const response = await Fetch.performPost("tool_mulib", "muform/datetime", { body });
    const answer = await response.json();
    if (answer.valid) {
      input.dataset.muformDatetimeTimestamp = answer.timestamp === null ? "" : String(answer.timestamp);
      input.dataset.muformDatetimeComponents = answer.components ? JSON.stringify(answer.components) : "";
    }
    return answer;
  } finally {
    pending.resolve();
  }
}
__name(normalise, "normalise");
function readComponents(input) {
  const json = input.dataset.muformDatetimeComponents;
  if (!json) {
    return null;
  }
  try {
    return JSON.parse(json);
  } catch {
    return null;
  }
}
__name(readComponents, "readComponents");
function formatComponents(parts) {
  const pad = /* @__PURE__ */ __name((value) => String(value).padStart(2, "0"), "pad");
  return `${parts.year}-${pad(parts.month)}-${pad(parts.day)} ${pad(parts.hour)}:${pad(parts.minute)}:${pad(parts.second)}`;
}
__name(formatComponents, "formatComponents");
export {
  formatComponents,
  normalise,
  readComponents
};
//# sourceMappingURL=datetimeapi.dev.js.map
