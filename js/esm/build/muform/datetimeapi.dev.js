var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Client of the datetime normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language and default time it was rendered with
 * as data attributes, the endpoint answers with the three representations of the date.
 *
 * @module     tool_mulib/muform/datetimeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
import Pending from "@moodle/lms/core/pending";
async function normaliseText(text, settings) {
  const pending = new Pending("tool_mulib/muform:datetime");
  try {
    const body = {
      text,
      timezone: settings.timezone,
      lang: settings.lang,
      defaulthour: settings.defaultHour,
      defaultminute: settings.defaultMinute
    };
    const response = await Fetch.performPost("tool_mulib", "muform/datetime", { body });
    return await response.json();
  } finally {
    pending.resolve();
  }
}
__name(normaliseText, "normaliseText");
async function normalise(input, text) {
  const answer = await normaliseText(text, {
    timezone: input.dataset.muformDatetimeTimezone ?? "",
    lang: input.dataset.muformDatetimeLang ?? "",
    defaultHour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
    defaultMinute: Number(input.dataset.muformDatetimeDefaultminute) || 0
  });
  if (answer.valid) {
    input.dataset.muformDatetimeTimestamp = answer.timestamp === null ? "" : String(answer.timestamp);
    input.dataset.muformDatetimeComponents = answer.components ? JSON.stringify(answer.components) : "";
  }
  return answer;
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
function formatDate(timestamp, timezone, lang, format) {
  const date = new Date(timestamp * 1e3);
  let parts;
  try {
    parts = new Intl.DateTimeFormat("en-GB", { timeZone: timezone, year: "numeric", month: "numeric", day: "numeric" }).formatToParts(date);
  } catch {
    parts = new Intl.DateTimeFormat("en-GB", { year: "numeric", month: "numeric", day: "numeric" }).formatToParts(date);
  }
  const part = /* @__PURE__ */ __name((type) => Number(parts.find((item) => item.type === type)?.value), "part");
  const year = part("year");
  const month = part("month");
  const day = part("day");
  const monthName = /* @__PURE__ */ __name((style) => {
    try {
      return new Intl.DateTimeFormat(lang, { month: style, timeZone: "UTC" }).format(new Date(Date.UTC(2026, month - 1, 15)));
    } catch {
      return String(month);
    }
  }, "monthName");
  const pad = /* @__PURE__ */ __name((value) => String(value).padStart(2, "0"), "pad");
  let result = "";
  for (let i = 0; i < format.length; i++) {
    const char = format[i];
    switch (char) {
      case "Y":
        result += String(year);
        break;
      case "y":
        result += pad(year % 100);
        break;
      case "m":
        result += pad(month);
        break;
      case "n":
        result += String(month);
        break;
      case "d":
        result += pad(day);
        break;
      case "j":
        result += String(day);
        break;
      case "M":
        result += monthName("short");
        break;
      case "F":
        result += monthName("long");
        break;
      case "\\":
        i++;
        result += format[i] ?? "";
        break;
      default:
        result += char;
    }
  }
  return result;
}
__name(formatDate, "formatDate");
export {
  formatComponents,
  formatDate,
  normalise,
  normaliseText,
  readComponents
};
//# sourceMappingURL=datetimeapi.dev.js.map
