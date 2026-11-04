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

namespace tool_mulib\tests;

/**
 * Fake minimal language packs for PHPUnit and Behat tests.
 *
 * Only a few core files are copied from fixtures (langconfig.php, moodle.php, admin.php, calendar.php, etc.),
 * MuTMS plugins ship their own translations, other strings fall back to English.
 * Fake packs are deleted automatically together with test dataroot files.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_langpack {
    /**
     * Languages available in fixtures.
     *
     * @return string[]
     */
    public static function available(): array {
        $result = [];
        foreach (new \DirectoryIterator(self::get_fixtures_dir()) as $item) {
            if (!$item->isDot() && $item->isDir()) {
                $result[] = $item->getFilename();
            }
        }
        sort($result);
        return $result;
    }

    /**
     * Install fake language pack into dataroot.
     *
     * @param string $lang
     */
    public static function install(string $lang): void {
        global $CFG;

        if (!in_array($lang, self::available(), true)) {
            throw new \core\exception\coding_exception("Fake language pack '$lang' is not available");
        }

        $source = self::get_fixtures_dir() . '/' . $lang;
        $target = $CFG->langotherroot . '/' . $lang;
        if (!is_dir($target)) {
            mkdir($target, $CFG->directorypermissions, true);
        }
        foreach (glob("$source/*.php") as $file) {
            copy($file, $target . '/' . basename($file));
        }

        get_string_manager()->reset_caches();
    }

    /**
     * Returns directory with fake language packs.
     *
     * @return string
     */
    private static function get_fixtures_dir(): string {
        return __DIR__ . '/../fixtures/lang';
    }
}
