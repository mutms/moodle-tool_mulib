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
require_once(__DIR__ . '/../fixtures/fake_user_source.php');

use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\userpicker;
use tool_mulib\muform\element\userpickermany;
use tool_mulib\phpunit\muform\fixtures\fake_user_source;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * User picker element tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(userpicker::class)]
#[CoversClass(userpickermany::class)]
#[Group('MuTMS')]
final class userpicker_test extends muform_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $hook = function (muform_definition $hook): void {
            $hook->form->add((new userpicker('owner', 'Owner', new fake_user_source()))->set_required(true));
            $hook->form->add(new userpickermany('members', 'Members', new fake_user_source()));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit values and return form.
     *
     * @param mixed $owner
     * @param mixed $members
     * @return simple_form
     */
    private function submit(mixed $owner, mixed $members): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'owner' => $owner, 'members' => $members, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_values(): void {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Visible']);
        $hidden = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Hidden']);
        $suspended = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Suspended', 'suspended' => 1]);

        $data = $this->submit($user->id, "$user->id,2")->get_data();
        $this->assertSame((string)$user->id, $data->owner);
        $this->assertSame([(string)$user->id, '2'], $data->members);

        $form = $this->submit($hidden->id, '');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Error'], $this->get_rendered_errors($form, 'owner'));

        $form = $this->submit('', '');
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'owner'));

        $form = $this->submit($user->id, (string)$suspended->id);
        $this->assertTrue($form->is_invalid());
        $expected = ['Anna Suspended ' . $suspended->email . ': Suspended user'];
        $this->assertSame($expected, $this->get_rendered_errors($form, 'members'));
    }

    public function test_render(): void {
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['owner' => '2', 'members' => ['2']]);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-element="userpicker"', $html);
        $this->assertStringContainsString('data-muform-element="userpickermany"', $html);
        $this->assertStringContainsString('data-picker-panel="tool_mulib/muform/userpicker/panel"', $html);
        $this->assertStringContainsString('&quot;title&quot;:&quot;Select users&quot;', $html);
        $this->assertStringContainsString('&quot;title&quot;:&quot;Select user&quot;', $html);
        $this->assertStringContainsString('Admin User', $html);
    }
}
