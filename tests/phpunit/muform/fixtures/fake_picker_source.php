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

namespace tool_mulib\phpunit\muform\fixtures;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fake_picker_family.php');

/**
 * Fake picker source: fixed values, one refused value.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_picker_source extends fake_picker_family {
    /** @var array fixed values */
    public const array OPTIONS = [
        'a' => '<b>Alpha</b>',
        'b' => 'Beta',
        'c' => 'Gamma',
        'd' => 'D &amp; E',
        '5' => 'Five',
        'unit:7' => 'Unit 7',
    ];

    /**
     * Constructor.
     *
     * @param string $refused value that validate() refuses
     */
    public function __construct(
        /** @var string value that validate() refuses */
        private string $refused = ''
    ) {
    }

    #[\Override]
    public function get_args(): array {
        return [$this->refused];
    }

    #[\Override]
    public function labels(array $values): array {
        $result = [];
        foreach ($values as $value) {
            if (isset(self::OPTIONS[$value])) {
                $result[$value] = self::OPTIONS[$value];
            }
        }
        return $result;
    }

    #[\Override]
    public function validate(array $values): array {
        return in_array($this->refused, $values, true) ? [$this->refused => 'Not allowed'] : [];
    }
}
