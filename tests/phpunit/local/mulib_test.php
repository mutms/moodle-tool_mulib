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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_mulib\phpunit\local;

use tool_mulib\local\mulib;

/**
 * MuTMS helper tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\mulib
 * @covers \tool_mulib\task\lang_cleanup
 */
final class mulib_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_is_mutms_plugin(): void {
        $this->assertTrue(mulib::is_mutms_plugin('tool_mulib'));
        $this->assertTrue(mulib::is_mutms_plugin('tool_mutenancy'));
        $this->assertTrue(mulib::is_mutms_plugin('tool_mucohort'));
        $this->assertTrue(mulib::is_mutms_plugin('auth_musaml'));
        $this->assertFalse(mulib::is_mutms_plugin('tool_certificate'));
        $this->assertFalse(mulib::is_mutms_plugin('local_mutest'));
        // Not every plugin with "mu" prefix is a MuTMS plugin.
        $this->assertFalse(mulib::is_mutms_plugin('tool_multilang'));
        $this->assertFalse(mulib::is_mutms_plugin(''));

        foreach (mulib::MUTMS_PLUGINS as $component) {
            [$type, $name] = \core_component::normalize_component($component);
            $this->assertSame($component, $type . '_' . $name);
        }
    }

    public function test_is_mucatalog_available(): void {
        $this->assertSame(
            file_exists(__DIR__ . '/../../../../../tool/mucatalog/version.php'),
            mulib::is_mucatalog_available()
        );
    }

    public function test_is_mucatalog_active(): void {
        $this->assertFalse(mulib::is_mucatalog_active());

        set_config('active', '1', 'tool_mucatalog');

        if (mulib::is_mucatalog_available()) {
            $this->assertTrue(mulib::is_mucatalog_active());
        } else {
            $this->assertFalse(mulib::is_mucatalog_active());
        }
    }

    public function test_is_muprog_available(): void {
        $this->assertSame(
            file_exists(__DIR__ . '/../../../../../tool/muprog/version.php'),
            mulib::is_muprog_available()
        );
    }

    public function test_is_muprog_active(): void {
        $this->assertFalse(mulib::is_muprog_active());

        set_config('active', '1', 'tool_muprog');

        if (mulib::is_muprog_available()) {
            $this->assertTrue(mulib::is_muprog_active());
        } else {
            $this->assertFalse(mulib::is_muprog_active());
        }
    }

    public function test_is_mucertify_available(): void {
        $this->assertSame(
            file_exists(__DIR__ . '/../../../../../tool/mucertify/version.php'),
            mulib::is_mucertify_available()
        );
    }

    public function test_is_mucertify_active(): void {
        $this->assertFalse(mulib::is_mucertify_active());

        set_config('active', '1', 'tool_mucertify');

        if (mulib::is_mucertify_available()) {
            $this->assertTrue(mulib::is_mucertify_active());
        } else {
            $this->assertFalse(mulib::is_mucertify_active());
        }
    }

    public function test_is_mutrain_available(): void {
        $this->assertSame(
            file_exists(__DIR__ . '/../../../../../tool/mutrain/version.php'),
            mulib::is_mutrain_available()
        );
    }

    public function test_is_mutrain_active(): void {
        $this->assertFalse(mulib::is_mutrain_active());

        set_config('active', '1', 'tool_mutrain');

        if (mulib::is_mutrain_available()) {
            $this->assertTrue(mulib::is_mutrain_active());
        } else {
            $this->assertFalse(mulib::is_mutrain_active());
        }
    }

    public function test_is_muhome_available(): void {
        $this->assertSame(
            file_exists(__DIR__ . '/../../../../../tool/muhome/version.php'),
            mulib::is_muhome_available()
        );
    }

    public function test_is_muhome_active(): void {
        $this->assertFalse(mulib::is_muhome_active());

        set_config('active', '1', 'tool_muhome');

        if (mulib::is_muhome_available()) {
            $this->assertTrue(mulib::is_muhome_active());
        } else {
            $this->assertFalse(mulib::is_muhome_active());
        }
    }

    public function test_is_mutenancy_available(): void {
        $this->assertSame(
            file_exists(__DIR__ . '/../../../../../tool/mutenancy/version.php'),
            mulib::is_mutenancy_available()
        );
    }

    public function test_is_mutenancy_active(): void {
        if (!mulib::is_mutenancy_available()) {
            $this->assertFalse(mulib::is_mutenancy_active());
            return;
        }

        \tool_mutenancy\local\tenancy::deactivate();
        $this->assertFalse(mulib::is_mutenancy_active());

        \tool_mutenancy\local\tenancy::activate();
        $this->assertTrue(mulib::is_mutenancy_active());
    }

    /**
     * Test conversion of dangerous characters and named entities to numeric entities.
     * NOTE: functionality was submitted upstream, this test will be removed later.
     */
    public function test_clean_string(): void {
        $string = 'Žluťoučký koníček <tag> "test" \'example\' & escaped &amp; &lt; &gt; &quot; ';
        $cleaned = clean_string($string);

        $this->assertSame(
            'Žluťoučký koníček &#60;tag&#62; &#34;test&#34; &#39;example&#39; &#38; escaped &#38; &#60; &#62; &#34; ',
            $cleaned
        );

        // Repeated cleaning does not change result.
        $this->assertSame($cleaned, clean_string($cleaned));

        // Function s() does not modify it.
        $this->assertSame($cleaned, s($cleaned));

        // Function format_string() does not modify it.
        $this->assertSame($cleaned, format_string($cleaned));

        // Function clean_text() does not remove data.
        $this->assertSame($cleaned, clean_string(clean_text($cleaned)));

        // It can be converted back to raw UTF-8 characters.
        $this->assertSame(\core_text::entities_to_utf8($string), \core_text::entities_to_utf8($cleaned));
    }

    public function test_json_encode(): void {
        $data = ['name' => 'Žluťoučký kůň', 'url' => 'https://example.com/a/b', 'list' => [1, 2], 'empty' => new \stdClass()];
        $this->assertSame(
            '{"name":"Žluťoučký kůň","url":"https://example.com/a/b","list":[1,2],"empty":{}}',
            mulib::json_encode($data)
        );
        $expected = <<<'JSON'
{
    "name": "Žluťoučký kůň",
    "url": "https://example.com/a/b",
    "list": [
        1,
        2
    ],
    "empty": {}
}
JSON;
        $this->assertSame($expected, mulib::json_encode($data, true));
        $this->assertSame('null', mulib::json_encode(null));

        $this->expectException(\JsonException::class);
        mulib::json_encode("\xB1\x31");
    }

    public function test_uuid7(): void {
        $before = (int)floor(microtime(true) * 1000);
        $uuid = mulib::uuid7();
        $after = (int)floor(microtime(true) * 1000);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid);
        $ms = hexdec(str_replace('-', '', substr($uuid, 0, 13)));
        $this->assertGreaterThanOrEqual($before, $ms);
        $this->assertLessThanOrEqual($after, $ms);

        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = mulib::uuid7();
        }
        $this->assertCount(100, array_unique($uuids));
    }

    public function test_purge_downloaded_translations(): void {
        global $CFG;

        \tool_mulib\tests\fake_langpack::install('cs');
        $file = "$CFG->langotherroot/cs/tool_mulib.php";
        file_put_contents($file, "<?php\n\$string['pluginname'] = 'Zatuchlý blábol z AMOS';\n");
        $fr = "$CFG->langotherroot/fr/tool_mulib.php";
        mkdir(dirname($fr), $CFG->directorypermissions, true);
        file_put_contents($fr, "<?php\n\$string['pluginname'] = 'Baguette périmée';\n");
        get_string_manager()->reset_caches();

        $string = [];
        include(__DIR__ . '/../../../lang/cs/tool_mulib.php');
        $shipped = $string['pluginname'];
        $this->assertNotSame('Zatuchlý blábol z AMOS', $shipped);

        $this->assertSame('Zatuchlý blábol z AMOS', get_string_manager()->get_string('pluginname', 'tool_mulib', null, 'cs'));

        set_config('purgedownloadedlang', 0, 'tool_mulib');
        $this->assertSame(0, mulib::purge_downloaded_translations());
        $this->assertFileExists($file);

        set_config('purgedownloadedlang', 1, 'tool_mulib');
        $this->assertSame(1, mulib::purge_downloaded_translations());
        $this->assertFileDoesNotExist($file);
        $this->assertFileExists($fr);
        $this->assertSame($shipped, get_string_manager()->get_string('pluginname', 'tool_mulib', null, 'cs'));
        $this->assertSame('Předvolby', get_string_manager()->get_string('preferences', 'core', null, 'cs'));
        $this->assertSame('Evropa/Praha', get_string_manager()->get_string('europe/prague', 'core_timezones', null, 'cs'));
        $this->assertSame('Pondělí', get_string_manager()->get_string('monday', 'core_calendar', null, 'cs'));
    }

    public function test_callback_langpack_installed(): void {
        global $CFG;

        \tool_mulib\tests\fake_langpack::install('de');
        $file = "$CFG->langotherroot/de/tool_mulib.php";
        file_put_contents($file, "<?php\n\$string['pluginname'] = 'Zatuchlý blábol z AMOS';\n");

        \tool_langimport\event\langpack_updated::event_with_langcode('de')->trigger();
        $this->assertFileDoesNotExist($file);

        file_put_contents($file, "<?php\n\$string['pluginname'] = 'Zatuchlý blábol z AMOS';\n");
        \tool_langimport\event\langpack_imported::event_with_langcode('de')->trigger();
        $this->assertFileDoesNotExist($file);
    }

    public function test_lang_cleanup_task(): void {
        global $CFG;

        \tool_mulib\tests\fake_langpack::install('cs');
        $file = "$CFG->langotherroot/cs/tool_mulib.php";
        file_put_contents($file, "<?php\n\$string['pluginname'] = 'Zatuchlý blábol z AMOS';\n");

        $task = new \tool_mulib\task\lang_cleanup();
        $this->expectOutputString("Deleted 1 downloaded translation files conflicting with official MuTMS translations\n");
        $task->execute();
        $this->assertFileDoesNotExist($file);
    }
}
