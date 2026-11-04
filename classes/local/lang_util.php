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

namespace tool_mulib\local;

/**
 * Language pack helpers.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lang_util {
    /**
     * Is this a valid language code known to Moodle?
     *
     * NOTE: the language pack does not need to be installed,
     * every language from AMOS has installer strings in install/lang/.
     *
     * @param string $lang
     * @return bool
     */
    public static function is_known_language(string $lang): bool {
        global $CFG;
        if ($lang === 'en') {
            return true;
        }
        if ($lang === '' || clean_param($lang, PARAM_SAFEDIR) !== $lang) {
            return false;
        }
        return is_dir("$CFG->dirroot/install/lang/$lang");
    }

    /**
     * Delete translations of a plugin from downloaded language packs,
     * so that translations shipped with the plugin are used instead.
     *
     * Moodle loads strings from downloaded language packs after the plugin's own lang directory,
     * which means that outdated AMOS translations override translations shipped with plugins.
     * Local customisations (xx_local packs) are never touched.
     *
     * @param string $component frankenstyle component name
     * @param string[]|null $languages language codes, null means all languages shipped with the plugin
     * @return int number of deleted files
     */
    public static function purge_downloaded_translations(string $component, ?array $languages = null): int {
        global $CFG;

        if (empty($CFG->langotherroot) || !is_dir($CFG->langotherroot)) {
            return 0;
        }

        [$type, $name] = \core_component::normalize_component($component);
        $dir = \core_component::get_component_directory($component);
        if (!$dir || !is_dir("$dir/lang")) {
            return 0;
        }
        $file = ($type === 'mod' ? $name : "{$type}_{$name}") . '.php';

        if ($languages === null) {
            $languages = [];
            foreach (new \DirectoryIterator("$dir/lang") as $item) {
                if (!$item->isDot() && $item->isDir() && file_exists($item->getPathname() . '/' . $file)) {
                    $languages[] = $item->getFilename();
                }
            }
        }

        $deleted = 0;
        foreach ($languages as $lang) {
            // English strings are always loaded from plugins, downloaded en packs are ignored.
            if ($lang === 'en' || !self::is_known_language($lang)) {
                continue;
            }
            $target = "$CFG->langotherroot/$lang/$file";
            if (!is_file($target)) {
                continue;
            }
            // Language packs may be deployed read-only on purpose, do not trigger PHP warnings.
            if (@unlink($target)) {
                $deleted++;
            } else {
                debugging("Cannot delete downloaded translation file $target", DEBUG_DEVELOPER);
            }
        }

        if ($deleted) {
            get_string_manager()->reset_caches();
        }

        return $deleted;
    }

    /**
     * Customise language string the same way as the language customisation tool,
     * by writing it into $CFG->langlocalroot/<lang>_local/.
     *
     * Existing records of the language customisation tool are updated too,
     * so that the tool shows the new value and does not overwrite it later.
     *
     * @param string $lang installed language, for example en, en_us or cs
     * @param string $component frankenstyle component name, core and core_xxx are supported too
     * @param string $stringid
     * @param string|null $value null means remove customisation
     */
    public static function customise_string(string $lang, string $component, string $stringid, ?string $value): void {
        global $CFG, $DB;

        if (!self::is_known_language($lang)) {
            throw new \core\exception\coding_exception("Invalid language '$lang'");
        }
        if (!get_string_manager()->translation_exists($lang)) {
            throw new \core\exception\coding_exception("Language '$lang' is not installed");
        }
        if ($component === '' || clean_param($component, PARAM_COMPONENT) !== $component) {
            throw new \core\exception\coding_exception("Invalid component '$component'");
        }
        if ($stringid === '' || clean_param($stringid, PARAM_STRINGID) !== $stringid) {
            throw new \core\exception\coding_exception("Invalid string identifier '$stringid'");
        }

        [$type, $name] = \core_component::normalize_component($component);
        if ($type === 'core') {
            $file = $name ?? 'moodle';
            $component = $name === null ? 'core' : 'core_' . $name;
        } else {
            $file = ($type === 'mod') ? $name : $type . '_' . $name;
            $component = $type . '_' . $name;
        }

        // Only strings defined in English can be customised, the same as in the language customisation tool,
        // removal of obsolete customisations is allowed.
        if ($value !== null) {
            // Do not use string_exists(), it might get confused by local modifications.
            $english = get_string_manager()->load_component_strings($component, 'en', true, true);
            if (!array_key_exists($stringid, $english)) {
                throw new \core\exception\coding_exception("String '$stringid' does not exist in component '$component'");
            }
        }

        $dir = $CFG->langlocalroot . '/' . $lang . '_local';
        $path = $dir . '/' . $file . '.php';

        // Load existing customisations the same way as Moodle, then write the whole file again.
        $string = [];
        if (file_exists($path)) {
            include($path);
        }
        if ($value === null) {
            unset($string[$stringid]);
        } else {
            $string[$stringid] = $value;
        }

        if ($string) {
            ksort($string, SORT_STRING);
            $content = "<?php\n\ndefined('MOODLE_INTERNAL') || die();\n\n";
            foreach ($string as $key => $text) {
                $content .= '$string[' . var_export((string)$key, true) . '] = ' . var_export((string)$text, true) . ";\n";
            }
            if (!is_dir($dir)) {
                mkdir($dir, $CFG->directorypermissions, true);
            }
            // Concurrent requests must never include a partially written file.
            $temp = $path . '.tmp' . random_string(8);
            file_put_contents($temp, $content);
            @chmod($temp, $CFG->filepermissions);
            rename($temp, $path);
        } else if (file_exists($path)) {
            unlink($path);
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        // Keep the checkout of language customisation tool in sync, its checkin rebuilds all files from database.
        $sql = "SELECT s.*
                  FROM {tool_customlang} s
                  JOIN {tool_customlang_components} c ON c.id = s.componentid
                 WHERE s.lang = :lang AND c.name = :component AND s.stringid = :stringid";
        $params = ['lang' => $lang, 'component' => $component, 'stringid' => $stringid];
        if ($record = $DB->get_record_sql($sql, $params)) {
            $record->local = $value;
            $record->timecustomized = ($value === null) ? null : time();
            $record->modified = 0;
            $DB->update_record('tool_customlang', $record);
        }

        get_string_manager()->reset_caches();
    }
}
