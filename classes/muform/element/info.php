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

use core\exception\coding_exception;
use core\output\core_renderer;
use tool_mulib\muform\element;

/**
 * Text display, no value is returned.
 *
 * Text comes from current data or default. How it is shown is decided by the
 * constructor, the element constants say which core function does the work:
 * STRING (default) uses format_string() (names, titles, most multilang values),
 * PLAIN escapes (idnumbers, URLs, codes),
 * HTML and MARKDOWN use format_text() with that format, TEXTFORMAT uses format_text()
 * with the format stored in current data "{name}format", FORMAT_HTML when
 * there is none. This is the one place in muform where text is formatted,
 * because displayed data in different formats is far too common for handlers to
 * format it themselves.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class info extends element {
    /** @var string escaped with s(), line breaks kept */
    public const string PLAIN = 'plain';
    /** @var string format_string() for names and titles */
    public const string STRING = 'string';
    /** @var string format_text() with HTML format */
    public const string HTML = 'html';
    /** @var string format_text() with Markdown format */
    public const string MARKDOWN = 'markdown';
    /** @var string format_text() with the format from current data "{name}format", FORMAT_HTML without it */
    public const string TEXTFORMAT = 'textformat';

    /** @var string one of the format constants */
    private string $format;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param string|null $default text used when current data does not contain the element name
     * @param string $format one of the format constants of this class
     */
    public function __construct(string $name, string $label, ?string $default = null, string $format = self::STRING) {
        parent::__construct($name);
        if (!in_array($format, [self::PLAIN, self::STRING, self::HTML, self::MARKDOWN, self::TEXTFORMAT], true)) {
            throw new coding_exception('Invalid info format: ' . $format);
        }
        $this->label = $label;
        $this->format = $format;
        $this->set_default($default);
        // Displayed text never comes from submitted data.
        $this->set_frozen(true);
    }

    #[\Override]
    public function returns_data(): bool {
        return false;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value === null || is_array($this->value) || is_bool($this->value)) {
            $this->value = '';
        }
        $this->value = (string)$this->value;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        global $PAGE;
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;

        $options = ['context' => $PAGE->context];
        $textformat = FORMAT_HTML;
        if ($this->format === self::TEXTFORMAT) {
            $stored = $this->get_form()->get_current_data()[$this->get_name() . 'format'] ?? null;
            if (is_numeric($stored)) {
                $textformat = (int)$stored;
            }
        }
        $context['contenthtml'] = match ($this->format) {
            self::PLAIN => nl2br(s($this->value), false),
            self::STRING => format_string($this->value, true, $options),
            self::HTML => format_text($this->value, FORMAT_HTML, $options),
            self::MARKDOWN => format_text($this->value, FORMAT_MARKDOWN, $options),
            self::TEXTFORMAT => format_text($this->value, $textformat, $options),
        };
        return $context;
    }
}
