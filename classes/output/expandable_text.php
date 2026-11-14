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

namespace tool_mulib\output;

/**
 * Text collapsed to one line that expands to the full text, native details/summary without JavaScript.
 *
 * The short text is always cut to one line by the browser. Without a short text the class decides:
 * short full texts without tags are printed whole as plain text - one paragraph of plain text from
 * the editor included, anything else is collapsed to its beginning as plain text. Full text without any text and media, such as
 * empty paragraphs left by editors, is rendered as a non-breaking space to keep the height of rows in lists of details.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expandable_text implements \core\output\named_templatable, \core\output\renderable {
    /** @var bool print the full text without cleaning, see allow_unsafe_rawhtml() */
    protected bool $unsafe = false;

    /** @var int full texts without tags shorter than this are not collapsed when no short text is given */
    public const MIN_LENGTH = 100;

    /**
     * Constructor.
     *
     * @param string|null $full html of the full text, usually the result of format_text(),
     *      it is cleaned again with clean_text() to prevent accidents unless allow_unsafe_rawhtml() is called
     * @param string|null $short plain text shown on one line when collapsed - never html, do not escape it,
     *      it is cleaned with clean_string(), so tags are shown as text and text looking like an html entity
     *      is shown as its character; NULL or blank means use the beginning of the full text
     *      if collapsing is needed at all
     */
    public function __construct(
        /** @var string|null html of the full text */
        protected ?string $full,
        /** @var string|null plain text shown when collapsed, never html */
        protected ?string $short = null
    ) {
    }

    /**
     * Print the full text without any cleaning - for output of templates and widgets only,
     * never for anything submitted by users.
     *
     * @return static
     */
    public function allow_unsafe_rawhtml(): static {
        $this->unsafe = true;
        return $this;
    }

    /**
     * Full text as one line of text without tags, safe to print.
     *
     * Entities are never decoded, so encoded markup of the full text cannot become real markup.
     *
     * @param string $html
     * @return string text with html entities, see clean_string()
     */
    protected static function to_plain(string $html): string {
        // Keep words of adjacent blocks and lines separated.
        $text = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/tr|/td|/th)\b[^>]*>~i', ' $0', $html);
        $text = preg_replace('~&(nbsp|#160|#x0*a0);~i', ' ', strip_tags($text));
        $text = trim(preg_replace('~[\s\x{00a0}]+~u', ' ', $text));
        return clean_string($text);
    }

    /**
     * Is there nothing to show? Editors leave empty paragraphs, line breaks and non-breaking spaces behind.
     *
     * @param string|null $html
     * @return bool true if there is no text and no media (images, videos, embedded content)
     */
    public static function is_empty(?string $html): bool {
        $html = (string)$html;
        if (preg_match('~<(img|video|audio|iframe|object|embed|svg)\b~i', $html)) {
            return false;
        }
        return self::to_plain($html) === '';
    }

    /**
     * Is the full text so short and plain that collapsing is not needed?
     *
     * Any tags mean formatted text, the only exception is one paragraph of plain text from the editor.
     *
     * @param string $html sanitised html of the full text
     * @return bool true for text without tags, optionally in one paragraph, shorter than MIN_LENGTH characters
     */
    public static function is_short_plain(string $html): bool {
        if (!preg_match('~^(<p(\s[^<>]*)?>)?[^<>]*(</p>)?$~i', trim($html))) {
            return false;
        }
        $text = html_entity_decode(self::to_plain($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return \core_text::strlen($text) < self::MIN_LENGTH;
    }

    /**
     * Export data for template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $full = (string)$this->full;
        if (!$this->unsafe) {
            // Callers are expected to pass sanitised html, clean it again to prevent accidents.
            $full = clean_text($full, FORMAT_HTML);
        }
        $data = ['full' => $full, 'expandable' => false, 'short' => '', 'guessed' => false];
        if (self::is_empty($full)) {
            // Keep the height of rows in lists of details.
            $data['full'] = '&nbsp;';
            return $data;
        }
        $short = trim((string)$this->short);
        if ($short === '') {
            $short = self::to_plain($full);
            if ($short === '') {
                // Images and other media without text cannot be described on one line.
                return $data;
            }
            if (self::is_short_plain($full)) {
                // Plain text matching the rest of the page.
                $data['full'] = $short;
                return $data;
            }
            $data['guessed'] = true;
        } else {
            $short = clean_string($short);
        }
        $data['expandable'] = true;
        // Always the result of clean_string(), safe no matter how the template or other code uses it.
        $data['short'] = $short;
        return $data;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param \renderer_base $renderer The renderer requesting the template name
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'tool_mulib/expandable_text';
    }
}
