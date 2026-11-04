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
 * Date and time element, the value is a Unix timestamp or null.
 *
 * Users see and type text in their timezone using the display format
 * from the muform_datetimeformat lang string of their language. With JavaScript the element
 * submits the timestamp, without it the text is parsed on the server.
 * Empty text and legacy 0 timestamps mean null.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class datetime extends element {
    /** @var int minute step of the picker, typed values are not rounded */
    private const int PICKER_STEP = 5;

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
        $this->set_invalid_hint(get_string('muform_datetimeinvalid', 'tool_mulib'));
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
    }

    /**
     * Set time of day used when users enter date without time.
     *
     * For example 23:59 for end dates or 15:00 for events that usually start in the afternoon.
     *
     * @param string $time H:i such as '23:59', default is '00:00'
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

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value === null || $this->value === '' || is_array($this->value) || is_bool($this->value)) {
            $this->value = null;
            return;
        }
        if (is_int($this->value) || is_float($this->value)) {
            $this->value = (int)$this->value;
        } else {
            try {
                $this->value = calendar::parse(
                    (string)$this->value,
                    $this->get_timezone(),
                    current_language(),
                    $this->defaulthour,
                    $this->defaultminute
                );
            } catch (\InvalidArgumentException $e) {
                $this->invalidtext = (string)$this->value;
                $this->value = null;
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
        }
        if ($this->value === 0) {
            // Legacy Moodle convention for missing dates.
            $this->value = null;
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
        $described = calendar::describe($this->value, $tz, current_language());

        $context['value'] = ($this->invalidtext !== '') ? $this->invalidtext : $described['text'];
        $context['timestamp'] = $described['timestamp'] === null ? '' : (string)$described['timestamp'];
        $context['componentsjson'] = $described['components'] ? json_encode($described['components']) : '';
        $context['timezone'] = $tz->getName();
        if (\core_date::get_user_timezone() === \core_date::get_server_timezone()) {
            // Users in site timezone do not need to be reminded on every date field.
            $context['timezonelabel'] = '';
        } else {
            $tzname = calendar::get_timezone_name($tz->getName());
            $context['timezonelabel'] = clean_string(get_string('muform_timezone', 'tool_mulib', $tzname));
        }
        $context['lang'] = current_language();
        $context['pickerstep'] = self::PICKER_STEP;
        $context['defaulthour'] = $this->defaulthour;
        $context['defaultminute'] = $this->defaultminute;
        $context['firstday'] = \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();
        return $context;
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
