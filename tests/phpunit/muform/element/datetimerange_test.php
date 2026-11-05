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
use tool_mulib\muform\element\datetimerange;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;
use tool_mulib\tests\fake_langpack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Datetime range element tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\muform\element\datetimerange::class)]
#[Group('MuTMS')]
final class datetimerange_test extends muform_testcase {
    #[\Override]
    protected function setUp(): void {
        global $USER;
        parent::setUp();
        $USER->timezone = 'Europe/Prague';
    }

    /**
     * Timestamp of a wall time in Prague.
     *
     * @param string $text
     * @return int
     */
    private static function at(string $text): int {
        return (new \DateTime($text, new \DateTimeZone('Europe/Prague')))->getTimestamp();
    }

    /**
     * Add datetimerange element via hook.
     *
     * @param callable|null $setup called with the element before it is added
     */
    private function add_element(?callable $setup = null): void {
        $hook = function (muform_definition $hook) use ($setup): void {
            $element = new datetimerange('session', 'Session', ['placeholder' => 'When']);
            if ($setup) {
                $setup($element);
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'session' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_parsing(): void {
        $this->add_element();
        $range = ['start' => self::at('2026-11-06 11:30'), 'end' => self::at('2026-11-06 12:30')];
        $night = ['start' => self::at('2026-11-06 23:30'), 'end' => self::at('2026-11-07 01:30')];

        $this->assertSame($range, $this->submit("2026-11-06 11:30 \u{2013} 12:30")->get_data()->session);
        $this->assertSame($night, $this->submit('2026-11-06 23:30 - 01:30')->get_data()->session);
        $this->assertSame($night, $this->submit('2026-11-06 23:30 - 01:30+1')->get_data()->session);

        // JavaScript submits timestamps, or text before the server answered.
        $this->assertSame($range, $this->submit(['start' => (string)$range['start'], 'end' => (string)$range['end'], 'text' => ''])
            ->get_data()->session);
        $this->assertSame($night, $this->submit(['start' => '', 'end' => '', 'text' => '2026-11-06 23:30 - 01:30'])
            ->get_data()->session);

        $this->assertNull($this->submit('')->get_data()->session);
        $this->assertNull($this->submit(['start' => '', 'end' => '', 'text' => ''])->get_data()->session);
        $this->assertNull($this->submit(['start' => '0', 'end' => '0'])->get_data()->session);
    }

    public function test_invalid(): void {
        $this->add_element();
        foreach (['2026-11-06 11:30', 'tomorrow <b>soon</b>', '1793917800', '2026-11-06 11:30 - 10:30 +0'] as $text) {
            $form = $this->submit($text);
            $this->assertTrue($form->is_invalid(), $text);
            $this->assertNull($form->get_non_validated_data()->session);
            $this->assertSame(['Invalid date and time range'], $this->get_rendered_errors($form, 'session'));
        }
        $html = $this->render($this->submit('tomorrow <b>soon</b>'));
        $this->assertStringContainsString('value="tomorrow &lt;b&gt;soon&lt;/b&gt;"', $html);
        $this->assertStringContainsString('data-muform-datetimerange-start=""', $html);

        $form = $this->submit(['start' => '20', 'end' => '10']);
        $this->assertTrue($form->is_invalid());
        $form = $this->submit(['start' => 'x', 'end' => '10']);
        $this->assertTrue($form->is_invalid());

        // Multi-day ranges are not supported, typed texts are invalid, timestamps are trimmed.
        $start = self::at('2026-11-06 11:30');
        $form = $this->submit(['start' => (string)$start, 'end' => (string)self::at('2026-11-07 11:30')]);
        $this->assertSame(['start' => $start, 'end' => self::at('2026-11-07 11:30')], $form->get_data()->session);
        $form = $this->submit(['start' => (string)$start, 'end' => (string)self::at('2026-11-09 11:31')]);
        $this->assertDebuggingCalled('Range of datetimerange element "session" is trimmed to 24 hours');
        $this->assertSame(['start' => $start, 'end' => self::at('2026-11-07 11:30')], $form->get_data()->session);
        $this->assertTrue($this->submit('2026-11-06 11:30 - 12:30 +1')->is_invalid());
    }

    public function test_default_duration(): void {
        $this->add_element(fn(datetimerange $element) => $element->set_default_duration(5400)->set_default_time('09:00'));
        $this->assertSame(
            ['start' => self::at('2026-11-06 11:30'), 'end' => self::at('2026-11-06 13:00')],
            $this->submit('2026-11-06 11:30')->get_data()->session
        );
        $this->assertSame(
            ['start' => self::at('2026-11-06 09:00'), 'end' => self::at('2026-11-06 10:30')],
            $this->submit('2026-11-06')->get_data()->session
        );
        // Explicit end wins.
        $this->assertSame(
            ['start' => self::at('2026-11-06 11:30'), 'end' => self::at('2026-11-06 12:00')],
            $this->submit('2026-11-06 11:30 - 12:00')->get_data()->session
        );
        $html = $this->render(new simple_form($this->get_url(), []));
        $this->assertStringContainsString('data-muform-datetimerange-defaultduration="5400"', $html);
        $this->assertStringContainsString('data-muform-datetime-defaulthour="9"', $html);
        $this->assertStringContainsString('{&quot;seconds&quot;:5400,&quot;label&quot;:&quot;1 hour 30 minutes&quot;}', $html);

        $element = new datetimerange('other', 'Other');
        foreach ([0, -1] as $seconds) {
            try {
                $element->set_default_duration($seconds);
                $this->fail('Exception expected');
            } catch (\core\exception\coding_exception $e) {
                $this->assertStringContainsString('Default duration must be positive', $e->getMessage());
            }
        }
        $this->assertSame($element, $element->set_default_duration(null));
        $element->set_default_duration(DAYSECS + 1);
        $this->assertDebuggingCalled('Default duration of datetimerange element is trimmed to one day');
    }

    public function test_max_duration(): void {
        $this->add_element(fn(datetimerange $element) => $element->set_max_duration(4 * HOURSECS));
        $this->assertTrue($this->submit('2026-11-06 11:30 - 15:30')->is_valid());
        $form = $this->submit('2026-11-06 11:30 - 15:35');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['The maximum duration is 4 hours'], $this->get_rendered_errors($form, 'session'));
        $html = $this->render(new simple_form($this->get_url(), []));
        $this->assertStringNotContainsString('&quot;seconds&quot;:28800', $html);
        $this->assertStringContainsString('&quot;seconds&quot;:14400', $html);
    }

    public function test_current_data(): void {
        $this->add_element();
        $start = self::at('2026-11-06 23:30');
        $end = self::at('2026-11-07 01:30');
        $form = new simple_form($this->get_url(), ['session' => ['start' => $start, 'end' => $end]]);
        $this->assertSame(['start' => $start, 'end' => $end], $form->get_non_validated_data()->session);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            'id="id_session' . $suffix . '" name="session" value="2026-11-06 23:30 ' . "\u{2013}" . ' 01:30 +1"',
            $html
        );
        $this->assertStringContainsString('data-muform-datetimerange-start="' . $start . '"', $html);
        $this->assertStringContainsString('data-muform-datetimerange-end="' . $end . '"', $html);
        $this->assertStringContainsString(
            'data-muform-datetime-components="{&quot;year&quot;:2026,&quot;month&quot;:11,&quot;day&quot;:6,'
            . '&quot;hour&quot;:23,&quot;minute&quot;:30,&quot;second&quot;:0}"',
            $html
        );
        $this->assertStringContainsString(
            'data-muform-datetimerange-endcomponents="{&quot;year&quot;:2026,&quot;month&quot;:11,&quot;day&quot;:7,'
            . '&quot;hour&quot;:1,&quot;minute&quot;:30,&quot;second&quot;:0}"',
            $html
        );
        $this->assertStringContainsString('data-muform-datetimerange-defaultduration=""', $html);
        $this->assertStringContainsString('placeholder="When"', $html);
        $this->assertStringContainsString(
            'id="id_session' . $suffix . '_hint" data-muform-datetimerange-hint aria-live="polite">'
            . '2 hours, ends 2026-11-07 01:30</div>',
            $html
        );
        $describedby = 'aria-describedby="id_error_session' . $suffix . ' id_session' . $suffix . '_hint id_session'
            . $suffix . '_timezone"';
        $this->assertStringContainsString($describedby, $html);

        // Longer current data is trimmed.
        $form = new simple_form($this->get_url(), ['session' => ['start' => $start, 'end' => $end + 3 * DAYSECS]]);
        $this->assertDebuggingCalled('Range of datetimerange element "session" is trimmed to 24 hours');
        $this->assertSame(['start' => $start, 'end' => self::at('2026-11-07 23:30')], $form->get_non_validated_data()->session);

        // Objects from database records work too.
        $form = new simple_form($this->get_url(), ['session' => (object)['start' => $start, 'end' => $end]]);
        $this->assertSame(['start' => $start, 'end' => $end], $form->get_non_validated_data()->session);
        $form = new simple_form($this->get_url(), ['session' => null]);
        $this->assertNull($form->get_non_validated_data()->session);

        // Timestamps do not depend on the timezone, text does.
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'UTC']));
        $html = $this->render(new simple_form($this->get_url(), ['session' => ['start' => $start, 'end' => $end]]));
        $this->assertStringContainsString('value="2026-11-06 22:30 ' . "\u{2013}" . ' 00:30 +1"', $html);
    }

    public function test_czech(): void {
        $this->add_element();
        fake_langpack::install('cs');
        force_current_language('cs');
        $start = self::at('2026-11-06 11:30');
        $end = self::at('2026-11-06 12:30');

        $html = $this->render(new simple_form($this->get_url(), ['session' => ['start' => $start, 'end' => $end]]));
        $this->assertStringContainsString('value="6. 11. 2026 11:30 ' . "\u{2013}" . ' 12:30"', $html);
        $this->assertSame(['start' => $start, 'end' => $end], $this->submit('6.11.2026 11.30 - 12.30')->get_data()->session);
    }

    public function test_required(): void {
        $this->add_element(fn(datetimerange $element) => $element->set_required(true));
        $form = $this->submit('');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'session'));
        $this->assertTrue($this->submit('2026-11-06 11:30 - 12:30')->is_valid());
    }

    public function test_frozen(): void {
        $this->add_element(fn(datetimerange $element) => $element->set_frozen(true));
        $this->simulate_post(simple_form::class, ['name' => 'x', 'session' => '2026-11-06 11:30 - 12:30', 'submit' => '1']);
        $start = self::at('2026-11-06 23:30');
        $end = self::at('2026-11-07 01:30');
        $form = new simple_form($this->get_url(), ['session' => ['start' => $start, 'end' => $end]]);
        $this->assertSame(['start' => $start, 'end' => $end], $form->get_data()->session);
        $html = $this->render($form);
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_session' . $form->get_idsuffix() . '">'
            . '<span class="muform-datetimerange-text text-nowrap">2026-11-06 23:30 ' . "\u{2013}"
            . ' 01:30<sup title="2026-11-07 01:30">+1</sup></span></div>',
            $html
        );
        $this->assertStringNotContainsString(' name="session"', $html);
    }
}
