<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

namespace tool_mulib\muform\element;

use core\output\core_renderer;
use core\param;
use tool_mulib\muform\element;
use tool_mulib\muform\util\calendar;

/**
 * Date and time range element for sessions and events, the value is an array
 * with start and end timestamps or null. Ranges are at most 24 hours long,
 * multi-day events are not supported: longer typed texts are invalid,
 * longer values from current data or submitted timestamps are trimmed with a debugging message.
 *
 * Users see and type one text such as "2026-11-06 11:30 – 12:30" in their timezone,
 * the end is a time after the start, "01:30 +1" for the next day, or a full date.
 * Text with start only gets the default duration of the element. With JavaScript
 * the element submits name[start] and name[end] timestamps, or name[text] until the server
 * normalised the text, without JavaScript the text is parsed on the server.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class datetimerange extends element {
    /** @var int minute step of the picker, typed values are not rounded */
    private const int PICKER_STEP = 5;

    /** @var int[] durations offered by the picker in seconds, the default duration is added */
    private const array PICKER_DURATIONS = [1800, 3600, 5400, 7200, 10800, 14400, 28800];

    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'placeholder' => param::TEXT,
        'width' => param::ALPHA,
    ];

    /** @var string text that could not be parsed, shown again so the user can fix it */
    private string $invalidtext = '';
    /** @var int hour of dates typed or picked without time */
    private int $defaulthour = 0;
    /** @var int minute of dates typed or picked without time */
    private int $defaultminute = 0;
    /** @var int|null seconds added to start typed without end */
    private ?int $defaultduration = null;
    /** @var int|null maximum duration in seconds */
    private ?int $maxduration = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array $attributes
     */
    public function __construct(string $name, string $label, array $attributes = []) {
        parent::__construct($name);
        $this->label = $label;
        $this->set_invalid_hint(get_string('muform_datetimerangeinvalid', 'tool_mulib'));
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
    }

    /**
     * Set time of day used when users enter start date without time.
     *
     * @param string $time H:i such as '09:00', default is '00:00'
     * @return static
     */
    public function set_default_time(string $time): static {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/D', $time, $matches) || (int)$matches[1] > 23 || (int)$matches[2] > 59) {
            throw new \core\exception\coding_exception('Invalid default time, H:i expected: ' . $time);
        }
        $this->defaulthour = (int)$matches[1];
        $this->defaultminute = (int)$matches[2];
        return $this;
    }

    /**
     * Set duration of ranges typed or picked without end.
     *
     * @param int|null $seconds positive number of seconds, longer than a day is trimmed, null means the end must be entered
     * @return static
     */
    public function set_default_duration(?int $seconds): static {
        if ($seconds !== null && $seconds <= 0) {
            throw new \core\exception\coding_exception('Default duration must be positive');
        }
        if ($seconds !== null && $seconds > DAYSECS) {
            debugging('Default duration of datetimerange element is trimmed to one day', DEBUG_DEVELOPER);
            $seconds = DAYSECS;
        }
        $this->defaultduration = $seconds;
        return $this;
    }

    /**
     * Set maximum duration shorter than the 24 hours every range is limited to.
     *
     * @param int|null $seconds positive number of seconds, null means 24 hours
     * @return static
     */
    public function set_max_duration(?int $seconds): static {
        if ($seconds !== null && $seconds <= 0) {
            throw new \core\exception\coding_exception('Maximum duration must be positive');
        }
        $this->maxduration = $seconds;
        return $this;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        $value = $this->value;
        if (is_object($value)) {
            $value = (array)$value;
        }
        if ($value === null || $value === '' || is_bool($value)) {
            $this->value = null;
            return;
        }
        if (is_array($value) && isset($value['text']) && is_string($value['text']) && trim($value['text']) !== '') {
            // JavaScript submits text the server did not normalise yet.
            $value = $value['text'];
        }
        if (is_array($value)) {
            $start = $value['start'] ?? null;
            $end = $value['end'] ?? null;
            if (empty($start) && empty($end)) {
                // Legacy Moodle convention for missing dates is 0.
                $this->value = null;
                return;
            }
            if (!is_numeric($start) || !is_numeric($end) || (int)$end <= (int)$start) {
                $this->value = null;
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $start = (int)$start;
            $end = (int)$end;
            $limit = calendar::get_range_limit($start, $this->get_timezone());
            if ($end > $limit) {
                // Values from code, the element supports ranges up to 24 hours only.
                debugging('Range of datetimerange element "' . $this->get_name() . '" is trimmed to 24 hours', DEBUG_DEVELOPER);
                $end = $limit;
            }
            $this->value = ['start' => $start, 'end' => $end];
        } else if (is_string($value) || is_int($value) || is_float($value)) {
            try {
                $this->value = calendar::parse_range(
                    (string)$value,
                    $this->get_timezone(),
                    current_language(),
                    $this->defaulthour,
                    $this->defaultminute,
                    $this->defaultduration
                );
            } catch (\InvalidArgumentException $e) {
                $this->invalidtext = (string)$value;
                $this->value = null;
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
        } else {
            $this->value = null;
            return;
        }

        if ($this->value !== null && $this->maxduration !== null && !$this->is_frozen()) {
            if ($this->value['end'] - $this->value['start'] > $this->maxduration) {
                $this->errors[] = get_string(
                    'muform_datetimerangetoolong',
                    'tool_mulib',
                    calendar::get_duration_text($this->maxduration)
                );
            }
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return $this->value !== null;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $tz = $this->get_timezone();
        $lang = current_language();
        $described = calendar::describe_range($this->value, $tz, $lang);

        $context['value'] = ($this->invalidtext !== '') ? $this->invalidtext : $described['text'];
        $context['valuehtml'] = calendar::format_range_html($this->value, $tz, $lang);
        $context['start'] = $described['start'] === null ? '' : (string)$described['start'];
        $context['end'] = $described['end'] === null ? '' : (string)$described['end'];
        $context['componentsjson'] = $described['startcomponents'] ? json_encode($described['startcomponents']) : '';
        $context['endcomponentsjson'] = $described['endcomponents'] ? json_encode($described['endcomponents']) : '';
        $context['hint'] = ($this->invalidtext !== '') ? '' : clean_string($described['hint']);
        $context['timezone'] = $tz->getName();
        if (\core_date::get_user_timezone() === \core_date::get_server_timezone()) {
            // Users in site timezone do not need to be reminded on every date field.
            $context['timezonelabel'] = '';
        } else {
            $tzname = calendar::get_timezone_name($tz->getName());
            $context['timezonelabel'] = clean_string(get_string('muform_timezone', 'tool_mulib', $tzname));
        }
        $context['lang'] = $lang;
        $context['pickerstep'] = self::PICKER_STEP;
        $context['defaulthour'] = $this->defaulthour;
        $context['defaultminute'] = $this->defaultminute;
        $context['defaultduration'] = $this->defaultduration ?? '';
        $context['durationsjson'] = json_encode($this->get_picker_durations());
        $context['hour12'] = calendar::uses_12_hour_clock($lang);
        $context['firstday'] = \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();
        return $context;
    }

    /**
     * Durations offered by the picker.
     *
     * @return array list of seconds and label
     */
    private function get_picker_durations(): array {
        $durations = self::PICKER_DURATIONS;
        if ($this->defaultduration !== null) {
            $durations[] = $this->defaultduration;
        }
        if ($this->maxduration !== null) {
            $durations = array_filter($durations, fn(int $seconds): bool => $seconds <= $this->maxduration);
        }
        $durations = array_unique($durations);
        sort($durations);
        $result = [];
        foreach ($durations as $seconds) {
            $result[] = ['seconds' => $seconds, 'label' => calendar::get_duration_text($seconds)];
        }
        return $result;
    }

    /**
     * Timezone used for text conversion.
     *
     * @return \DateTimeZone
     */
    private function get_timezone(): \DateTimeZone {
        return \core_date::get_user_timezone_object();
    }
}
