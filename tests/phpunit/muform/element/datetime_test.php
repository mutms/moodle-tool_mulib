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

namespace tool_mulib\phpunit\muform\element;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../muform_testcase.php');

use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\datetime;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;
use tool_mulib\tests\fake_langpack;

/**
 * Datetime element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\datetime
 */
final class datetime_test extends muform_testcase {
    #[\Override]
    protected function setUp(): void {
        global $USER;
        parent::setUp();
        $USER->timezone = 'Europe/Prague';
    }

    /**
     * Add datetime element via hook.
     *
     * @param bool $required
     * @param bool $frozen
     * @param string|null $defaulttime
     */
    private function add_element(bool $required = false, bool $frozen = false, ?string $defaulttime = null): void {
        $hook = function (muform_definition $hook) use ($required, $frozen, $defaulttime): void {
            $element = (new datetime('starts', 'Starts', ['placeholder' => 'Any time']))
                ->set_required($required)
                ->set_frozen($frozen);
            if ($defaulttime !== null) {
                $element->set_default_time($defaulttime);
            }
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit value and return form.
     *
     * @param mixed $value
     * @return simple_form
     */
    private function submit(mixed $value): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'starts' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_parsing(): void {
        $this->add_element();
        $this->assertSame(1790450923, $this->submit('1790450923')->get_data()->starts);
        $this->assertSame(1790450923, $this->submit(' 1790450923 ')->get_data()->starts);
        $this->assertSame(1790443723, $this->submit('2026-09-26 19:28:43')->get_data()->starts);
        $this->assertSame(1790443680, $this->submit('2026-09-26 19:28')->get_data()->starts);
        $this->assertSame(1790443680, $this->submit('2026-09-26T19:28')->get_data()->starts);
        $this->assertSame(1790373600, $this->submit('2026-09-26')->get_data()->starts);
        $this->assertNull($this->submit('')->get_data()->starts);
        $this->assertNull($this->submit('0')->get_data()->starts);
        $this->assertNull($this->submit(['x'])->get_data()->starts);
    }

    public function test_invalid_text(): void {
        $this->add_element();
        $form = $this->submit('sometime <b>soon</b>');
        $this->assertTrue($form->is_invalid());
        $this->assertNull($form->get_non_validated_data()->starts);
        $this->assertSame(['Invalid date and time'], $this->get_rendered_errors($form, 'starts'));
        $html = $this->render($form);
        $this->assertStringContainsString('value="sometime &lt;b&gt;soon&lt;/b&gt;"', $html);
        $this->assertStringContainsString('data-muform-datetime-timestamp=""', $html);
    }

    public function test_current_data(): void {
        $this->add_element();
        $form = new simple_form($this->get_url(), ['starts' => 1790443723]);
        $this->assertSame(1790443723, $form->get_non_validated_data()->starts);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('id="id_starts' . $suffix . '" name="starts" value="2026-09-26 19:28"', $html);
        $this->assertStringContainsString('data-muform-datetime-timestamp="1790443723"', $html);
        $this->assertStringContainsString(
            'data-muform-datetime-components="{&quot;year&quot;:2026,&quot;month&quot;:9,&quot;day&quot;:26,'
            . '&quot;hour&quot;:19,&quot;minute&quot;:28,&quot;second&quot;:43}"',
            $html
        );
        $this->assertStringContainsString('data-muform-datetime-timezone="Europe/Prague"', $html);
        $this->assertStringContainsString('data-muform-datetime-lang="en"', $html);
        $this->assertStringContainsString('data-muform-datetime-step="5"', $html);
        $this->assertStringContainsString('data-muform-datetime-defaulthour="0"', $html);
        $this->assertStringContainsString('data-muform-datetime-defaultminute="0"', $html);
        $this->assertStringContainsString('data-muform-datetime-firstday="1"', $html);
        $describedby = 'aria-describedby="id_error_starts' . $suffix . ' id_starts' . $suffix . '_timezone"';
        $this->assertStringContainsString($describedby, $html);
        $this->assertStringContainsString('placeholder="Any time"', $html);
        $this->assertStringContainsString(
            '<div class="form-text text-muted w-100" id="id_starts' . $suffix . '_timezone">Time zone: '
            . get_string('europe/prague', 'core_timezones') . '</div>',
            $html
        );

        $form = new simple_form($this->get_url(), ['starts' => 0]);
        $this->assertNull($form->get_non_validated_data()->starts);
        $form = new simple_form($this->get_url(), ['starts' => '2026-09-26 19:28:43']);
        $this->assertSame(1790443723, $form->get_non_validated_data()->starts);

        // Timestamps do not depend on the timezone, text does.
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'UTC']));
        $form = new simple_form($this->get_url(), ['starts' => 1790443723]);
        $this->assertSame(1790443723, $form->get_non_validated_data()->starts);
        $html = $this->render($form);
        $this->assertStringContainsString('value="2026-09-26 17:28"', $html);
        $this->assertStringContainsString('data-muform-datetime-timezone="UTC"', $html);
    }

    public function test_invalid_guesses(): void {
        $this->add_element();
        foreach (['26 September 2026 19:28', 'tomorrow', '4.11', '2026-02-31', '26.9.2026'] as $text) {
            $form = $this->submit($text);
            $this->assertTrue($form->is_invalid(), $text);
            $this->assertSame(['Invalid date and time'], $this->get_rendered_errors($form, 'starts'));
        }
    }

    public function test_default_time(): void {
        $this->add_element(false, false, '23:59');
        $this->assertSame(1790459940, $this->submit('2026-09-26')->get_data()->starts);
        $this->assertSame(1790443680, $this->submit('2026-09-26 19:28')->get_data()->starts);
        $html = $this->render(new simple_form($this->get_url(), []));
        $this->assertStringContainsString('data-muform-datetime-defaulthour="23"', $html);
        $this->assertStringContainsString('data-muform-datetime-defaultminute="59"', $html);

        $element = new datetime('ends', 'Ends');
        $this->assertSame($element, $element->set_default_time('7:05'));
        foreach (['24:00', '12:60', '12', '12:5', 'noon', ''] as $time) {
            try {
                $element->set_default_time($time);
                $this->fail('Exception expected for: ' . $time);
            } catch (\core\exception\coding_exception $e) {
                $this->assertStringContainsString('Invalid default time', $e->getMessage());
            }
        }
    }

    public function test_timezone_label(): void {
        global $USER;
        $this->add_element();

        // Users in site timezone do not see the timezone on every field.
        $this->setTimezone('Europe/Prague');
        $USER->timezone = '99';
        $form = new simple_form($this->get_url(), ['starts' => 1790443723]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringNotContainsString('_timezone', $html);
        $this->assertStringContainsString('aria-describedby="id_error_starts' . $suffix . '"', $html);
        $this->assertStringContainsString('data-muform-datetime-timezone="Europe/Prague"', $html);

        $USER->timezone = 'America/New_York';
        $html = $this->render(new simple_form($this->get_url(), ['starts' => 1790443723]));
        $this->assertStringContainsString('_timezone">Time zone: ', $html);
    }

    public function test_first_day(): void {
        global $CFG;
        $this->add_element();

        $CFG->calendar_startwday = 0;
        $html = $this->render(new simple_form($this->get_url(), []));
        $this->assertStringContainsString('data-muform-datetime-firstday="0"', $html);
        set_user_preference('calendar_startwday', 6);
        $html = $this->render(new simple_form($this->get_url(), []));
        $this->assertStringContainsString('data-muform-datetime-firstday="6"', $html);
    }

    public function test_czech(): void {
        $this->add_element();
        fake_langpack::install('cs');
        force_current_language('cs');

        $form = new simple_form($this->get_url(), ['starts' => 1790443723]);
        $html = $this->render($form);
        $this->assertStringContainsString('value="26. 9. 2026 19:28"', $html);
        $this->assertStringContainsString('data-muform-datetime-lang="cs"', $html);

        $this->assertSame(1790443680, $this->submit('26.9.2026 19:28')->get_data()->starts);
        $this->assertSame(1790443680, $this->submit('26. 9. 26 19:28')->get_data()->starts);
        $this->assertSame(1790373600, $this->submit('26. 9. 2026')->get_data()->starts);
        $this->assertTrue($this->submit('9/26/2026')->is_invalid());
    }

    public function test_required(): void {
        $this->add_element(true);
        $form = $this->submit('');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'starts'));
        $this->assertStringContainsString(' required aria-required="true"', $this->render($form));

        $form = $this->submit('0');
        $this->assertTrue($form->is_invalid());

        $form = $this->submit('2026-09-26 19:28:43');
        $this->assertTrue($form->is_valid());
    }

    public function test_frozen(): void {
        $this->add_element(false, true);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'starts' => '1790450923', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['starts' => 1790357323]);
        $this->assertSame(1790357323, $form->get_data()->starts);
        $html = $this->render($form);
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_starts' . $form->get_idsuffix() . '">2026-09-25 19:28</div>',
            $html
        );
        $this->assertStringNotContainsString(' name="starts"', $html);
        $this->assertStringContainsString('<span id="id_starts' . $form->get_idsuffix() . '_label"', $html);
        $this->assertStringNotContainsString('for="id_starts' . $form->get_idsuffix() . '"', $html);
    }
}
