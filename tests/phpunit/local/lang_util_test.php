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

namespace tool_mulib\phpunit\local;

use tool_mulib\local\lang_util;

/**
 * Language pack helper tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\lang_util
 */
final class lang_util_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create fake downloaded language file.
     *
     * @param string $lang
     * @param string $file
     * @return string full path
     */
    private function create_downloaded_file(string $lang, string $file): string {
        global $CFG;
        $dir = "$CFG->langotherroot/$lang";
        if (!is_dir($dir)) {
            mkdir($dir, $CFG->directorypermissions, true);
        }
        file_put_contents("$dir/$file", "<?php\n\$string['pluginname'] = 'Moldy AMOS leftovers';\n");
        return "$dir/$file";
    }

    public function test_is_known_language(): void {
        $this->assertTrue(lang_util::is_known_language('en'));
        $this->assertTrue(lang_util::is_known_language('en_us'));
        $this->assertTrue(lang_util::is_known_language('cs'));
        $this->assertTrue(lang_util::is_known_language('de_du'));
        // Not installed packs are fine.
        $this->assertTrue(lang_util::is_known_language('fr'));

        $this->assertFalse(lang_util::is_known_language(''));
        $this->assertFalse(lang_util::is_known_language('xx'));
        $this->assertFalse(lang_util::is_known_language('en_local'));
        $this->assertFalse(lang_util::is_known_language('.'));
        $this->assertFalse(lang_util::is_known_language('..'));
        $this->assertFalse(lang_util::is_known_language('../en'));
        $this->assertFalse(lang_util::is_known_language('en/'));
    }

    public function test_purge_downloaded_translations(): void {
        $cs = $this->create_downloaded_file('cs', 'tool_mulib.php');
        $de = $this->create_downloaded_file('de', 'tool_mulib.php');
        $fr = $this->create_downloaded_file('fr', 'tool_mulib.php');
        $en = $this->create_downloaded_file('en', 'tool_mulib.php');
        $other = $this->create_downloaded_file('cs', 'tool_dataprivacy.php');

        $this->assertSame(0, lang_util::purge_downloaded_translations('tool_mulib', ['en', '', '../cs', 'xx']));
        $this->assertFileExists($en);
        $this->assertFileExists($cs);

        // Explicit languages do not need to be shipped with the plugin.
        $this->assertSame(1, lang_util::purge_downloaded_translations('tool_mulib', ['fr']));
        $this->assertFileDoesNotExist($fr);
        $fr = $this->create_downloaded_file('fr', 'tool_mulib.php');

        $this->assertSame(1, lang_util::purge_downloaded_translations('tool_mulib', ['cs']));
        $this->assertFileDoesNotExist($cs);
        $this->assertFileExists($de);

        $cs = $this->create_downloaded_file('cs', 'tool_mulib.php');
        $this->assertSame(2, lang_util::purge_downloaded_translations('tool_mulib'));
        $this->assertFileDoesNotExist($cs);
        $this->assertFileDoesNotExist($de);
        $this->assertFileExists($en);
        $this->assertFileExists($fr);
        $this->assertFileExists($other);

        $this->assertSame(0, lang_util::purge_downloaded_translations('tool_mulib'));
        $this->assertSame(0, lang_util::purge_downloaded_translations('local_grgrgrgr'));
    }

    public function test_purge_downloaded_translations_readonly(): void {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Permissions are ignored for root');
        }
        $cs = $this->create_downloaded_file('cs', 'tool_mulib.php');
        chmod($cs, 0444);
        $this->assertSame(1, lang_util::purge_downloaded_translations('tool_mulib', ['cs']));
        $this->assertFileDoesNotExist($cs);

        $cs = $this->create_downloaded_file('cs', 'tool_mulib.php');
        chmod(dirname($cs), 0555);
        try {
            $this->assertSame(0, lang_util::purge_downloaded_translations('tool_mulib', ['cs']));
            $this->assertDebuggingCalled("Cannot delete downloaded translation file $cs");
            $this->assertFileExists($cs);
        } finally {
            chmod(dirname($cs), 0777);
        }
    }

    public function test_purge_downloaded_translations_mod(): void {
        if (!\core_component::get_component_directory('mod_mudeck')) {
            $this->markTestSkipped('mod_mudeck is not available');
        }
        $de = $this->create_downloaded_file('de', 'mudeck.php');
        $wrong = $this->create_downloaded_file('de', 'mod_mudeck.php');

        $this->assertSame(1, lang_util::purge_downloaded_translations('mod_mudeck', ['de']));
        $this->assertFileDoesNotExist($de);
        $this->assertFileExists($wrong);
    }

    public function test_customise_string(): void {
        global $CFG;
        $sm = get_string_manager();

        lang_util::customise_string('en', 'tool_mulib', 'pluginname', 'Grumpy teapot library');
        lang_util::customise_string('en', 'tool_mulib', 'lang_settings', 'Dancing pickle\'s "quoted" settings');
        $this->assertSame('Grumpy teapot library', $sm->get_string('pluginname', 'tool_mulib', null, 'en'));
        $this->assertSame('Dancing pickle\'s "quoted" settings', $sm->get_string('lang_settings', 'tool_mulib', null, 'en'));
        $this->assertFileExists("$CFG->langlocalroot/en_local/tool_mulib.php");

        lang_util::customise_string('en', 'core', 'preferences', 'Wobbly penguin preferences');
        lang_util::customise_string('en', 'core_langconfig', 'strftimedatefullshort', '%Y/%m/%d');
        lang_util::customise_string('en', 'core_calendar', 'monday', 'Mo');
        $this->assertSame('Wobbly penguin preferences', $sm->get_string('preferences', 'core', null, 'en'));
        $this->assertSame('%Y/%m/%d', $sm->get_string('strftimedatefullshort', 'langconfig', null, 'en'));
        $this->assertSame('Mo', $sm->get_string('monday', 'calendar', null, 'en'));
        $this->assertFileExists("$CFG->langlocalroot/en_local/moodle.php");
        $this->assertFileExists("$CFG->langlocalroot/en_local/langconfig.php");
        $this->assertFileExists("$CFG->langlocalroot/en_local/calendar.php");

        lang_util::customise_string('en', 'tool_mulib', 'pluginname', null);
        $this->assertSame('Additional tools library', $sm->get_string('pluginname', 'tool_mulib', null, 'en'));
        $this->assertSame('Dancing pickle\'s "quoted" settings', $sm->get_string('lang_settings', 'tool_mulib', null, 'en'));

        if (\core_component::get_component_directory('mod_mudeck')) {
            lang_util::customise_string('en', 'mod_mudeck', 'pluginname', 'Sleepy walrus deck');
            $this->assertSame('Sleepy walrus deck', $sm->get_string('pluginname', 'mod_mudeck', null, 'en'));
            $this->assertFileExists("$CFG->langlocalroot/en_local/mudeck.php");
        }
    }

    public function test_customise_string_translation(): void {
        $sm = get_string_manager();

        \tool_mulib\tests\fake_langpack::install('cs');
        lang_util::customise_string('cs', 'tool_mulib', 'pluginname', 'Příliš žluťoučký kůň úpěl ďábelské ódy');
        $this->assertSame('Příliš žluťoučký kůň úpěl ďábelské ódy', $sm->get_string('pluginname', 'tool_mulib', null, 'cs'));
        $this->assertSame('Additional tools library', $sm->get_string('pluginname', 'tool_mulib', null, 'en'));

        // Customisations are never purged.
        $this->assertSame(0, \tool_mulib\local\mulib::purge_downloaded_translations());
        $this->assertSame('Příliš žluťoučký kůň úpěl ďábelské ódy', $sm->get_string('pluginname', 'tool_mulib', null, 'cs'));

        // Child packs inherit customisations of parent, en_us does not specify parent, en is used.
        \tool_mulib\tests\fake_langpack::install('en_us');
        lang_util::customise_string('en', 'tool_mulib', 'pluginname', 'Grumpy teapot library');
        lang_util::customise_string('en_us', 'tool_mulib', 'lang_settings', 'Yeehaw spaghetti settings');
        $this->assertSame('Grumpy teapot library', $sm->get_string('pluginname', 'tool_mulib', null, 'en_us'));
        $this->assertSame('Yeehaw spaghetti settings', $sm->get_string('lang_settings', 'tool_mulib', null, 'en_us'));
        $this->assertNotSame('Yeehaw spaghetti settings', $sm->get_string('lang_settings', 'tool_mulib', null, 'en'));
    }

    public function test_customise_string_invalid(): void {
        try {
            lang_util::customise_string('../en', 'tool_mulib', 'pluginname', 'Invisible unicorn');
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $e) {
            $this->assertStringContainsString("Invalid language '../en'", $e->getMessage());
        }
        try {
            lang_util::customise_string('fr', 'tool_mulib', 'pluginname', 'Invisible unicorn');
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $e) {
            $this->assertStringContainsString("Language 'fr' is not installed", $e->getMessage());
        }
    }

    public function test_customise_string_invalid_params(): void {
        $invalid = [
            ['en', 'tool_../../x', 'pluginname', "Invalid component 'tool_../../x'"],
            ['en', '', 'pluginname', "Invalid component ''"],
            ['en', 'tool_mulib', '../x', "Invalid string identifier '../x'"],
            ['en', 'tool_mulib', '', "Invalid string identifier ''"],
            ['en_local', 'tool_mulib', 'pluginname', "Invalid language 'en_local'"],
            ['en', 'tool_mulib', 'grrrgrrr', "String 'grrrgrrr' does not exist in component 'tool_mulib'"],
            ['en', 'local_grrrgrrr', 'pluginname', "String 'pluginname' does not exist in component 'local_grrrgrrr'"],
            ['en', 'core_grrrgrrr', 'pluginname', "String 'pluginname' does not exist in component 'core_grrrgrrr'"],
        ];
        foreach ($invalid as [$lang, $component, $stringid, $message]) {
            try {
                lang_util::customise_string($lang, $component, $stringid, 'Invisible unicorn');
                $this->fail('Exception expected');
            } catch (\core\exception\coding_exception $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_customise_string_obsolete(): void {
        global $CFG;

        lang_util::customise_string('en', 'tool_mulib', 'pluginname', 'Grumpy teapot library');
        $file = "$CFG->langlocalroot/en_local/tool_mulib.php";
        file_put_contents($file, "\$string['obsoletestring'] = 'Obsolete';\n", FILE_APPEND);

        // Obsolete customisations can be removed.
        lang_util::customise_string('en', 'tool_mulib', 'obsoletestring', null);
        $string = [];
        include($file);
        $this->assertSame(['pluginname' => 'Grumpy teapot library'], $string);
    }

    public function test_customise_string_customlang_sync(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/customlang/locallib.php');

        // Simulate existing checkout of the language customisation tool.
        $componentid = $DB->insert_record('tool_customlang_components', ['name' => 'tool_mulib', 'version' => '1']);
        $record = [
            'lang' => 'en',
            'componentid' => $componentid,
            'stringid' => 'pluginname',
            'original' => 'Additional tools library',
            'master' => 'Additional tools library',
            'local' => null,
            'timemodified' => time(),
            'outdated' => 0,
            'modified' => 0,
        ];
        $record['id'] = $DB->insert_record('tool_customlang', $record);

        lang_util::customise_string('en', 'tool_mulib', 'pluginname', 'Grumpy teapot library');
        $this->assertSame('Grumpy teapot library', $DB->get_field('tool_customlang', 'local', ['id' => $record['id']]));

        // Checkin rebuilds all local files from database, the customisation must survive.
        \tool_customlang_utils::checkin('en');
        $this->assertSame('Grumpy teapot library', get_string_manager()->get_string('pluginname', 'tool_mulib', null, 'en'));

        // Strings from files written by the tool are merged.
        $file = "$CFG->langlocalroot/en_local/tool_mulib.php";
        lang_util::customise_string('en', 'tool_mulib', 'lang_settings', 'Ticklish cactus settings');
        $string = [];
        include($file);
        $this->assertSame(['lang_settings' => 'Ticklish cactus settings', 'pluginname' => 'Grumpy teapot library'], $string);
        $this->assertSame([basename($file)], array_values(array_diff(scandir(dirname($file)), ['.', '..'])));
        lang_util::customise_string('en', 'tool_mulib', 'lang_settings', null);

        lang_util::customise_string('en', 'tool_mulib', 'pluginname', null);
        $this->assertNull($DB->get_field('tool_customlang', 'local', ['id' => $record['id']]));
        $this->assertFileDoesNotExist("$CFG->langlocalroot/en_local/tool_mulib.php");
        \tool_customlang_utils::checkin('en');
        $this->assertSame('Additional tools library', get_string_manager()->get_string('pluginname', 'tool_mulib', null, 'en'));
    }

    public function test_generator(): void {
        /** @var \tool_mulib_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mulib');
        $generator->create_lang_customisation([
            'component' => 'tool_mulib',
            'stringid' => 'pluginname',
            'value' => 'Sneezing marmalade',
        ]);
        $this->assertSame('Sneezing marmalade', get_string('pluginname', 'tool_mulib'));
    }
}
