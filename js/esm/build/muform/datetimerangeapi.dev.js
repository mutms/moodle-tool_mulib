var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Client of the datetime range normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language, default time and default duration it was rendered
 * with as data attributes, the endpoint answers with both timestamps, the text and a hint.
 *
 * @module     tool_mulib/muform/datetimerangeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
import Pending from "@moodle/lms/core/pending";
async function normaliseRange(input, text) {
  const pending = new Pending("tool_mulib/muform:datetimerange");
  try {
    const body = {
      text,
      timezone: input.dataset.muformDatetimeTimezone ?? "",
      lang: input.dataset.muformDatetimeLang ?? "",
      defaulthour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
      defaultminute: Number(input.dataset.muformDatetimeDefaultminute) || 0
    };
    const duration = Number(input.dataset.muformDatetimerangeDefaultduration);
    if (duration > 0) {
      body.defaultduration = duration;
    }
    const response = await Fetch.performPost("tool_mulib", "muform/datetimerange", { body });
    const answer = await response.json();
    if (answer.valid) {
      input.dataset.muformDatetimerangeStart = answer.start === null ? "" : String(answer.start);
      input.dataset.muformDatetimerangeEnd = answer.end === null ? "" : String(answer.end);
      input.dataset.muformDatetimeComponents = answer.startcomponents ? JSON.stringify(answer.startcomponents) : "";
      input.dataset.muformDatetimerangeEndcomponents = answer.endcomponents ? JSON.stringify(answer.endcomponents) : "";
    }
    return answer;
  } finally {
    pending.resolve();
  }
}
__name(normaliseRange, "normaliseRange");
function readEndComponents(input) {
  const json = input.dataset.muformDatetimerangeEndcomponents;
  if (!json) {
    return null;
  }
  try {
    return JSON.parse(json);
  } catch {
    return null;
  }
}
__name(readEndComponents, "readEndComponents");
export {
  normaliseRange,
  readEndComponents
};
//# sourceMappingURL=datetimerangeapi.dev.js.map
