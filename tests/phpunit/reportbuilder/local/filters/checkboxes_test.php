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

namespace tool_mulib\phpunit\reportbuilder\local\filters;

use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\filter;
use tool_mulib\phpunit\reportbuilder\local\filters\fixtures\cities_or_none;
use tool_mulib\reportbuilder\local\filters\checkboxes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checkboxes report builder filter tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(checkboxes::class)]
#[Group('MuTMS')]
final class checkboxes_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/phpunit/reportbuilder/local/filters/fixtures/cities_or_none.php');
    }

    /**
     * Usernames of users matching the filter.
     *
     * @param checkboxes $instance
     * @param array $values stored filter values
     * @return string[]
     */
    private function filter_users(checkboxes $instance, array $values): array {
        global $DB;
        [$sql, $params] = $instance->get_sql_filter($values);
        $where = $sql === '' ? '' : "AND $sql";
        return array_values($DB->get_fieldset_sql(
            "SELECT u.username FROM {user} u WHERE u.username LIKE 'cb%' $where ORDER BY u.username",
            $params
        ));
    }

    public function test_values(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $generator->create_user(['username' => 'cb1', 'city' => 'Prague']);
        $generator->create_user(['username' => 'cb2', 'city' => 'Brno']);
        $generator->create_user(['username' => 'cb3', 'city' => 'Ostrava']);

        $filter = (new filter(checkboxes::class, 'city', new \lang_string('city'), 'user', 'u.city'))
            ->set_options(['Prague' => 'Praha', 'Brno' => 'Brno', 'Ostrava' => 'Ostrava']);
        $instance = checkboxes::create($filter);
        $this->assertSame(['Prague' => 'Praha', 'Brno' => 'Brno', 'Ostrava' => 'Ostrava'], $instance->get_options());

        // Nothing checked does not filter, any checked option matches, unknown values are ignored.
        $this->assertSame(['', []], $instance->get_sql_filter([]));
        $this->assertSame(['', []], $instance->get_sql_filter(['user:city_values' => []]));
        $this->assertSame(['cb1', 'cb2', 'cb3'], $this->filter_users($instance, ['user:city_values' => []]));
        $this->assertSame(['cb1'], $this->filter_users($instance, ['user:city_values' => ['Prague', 'Paris']]));
        $this->assertSame(['cb1', 'cb3'], $this->filter_users($instance, ['user:city_values' => ['Ostrava', 'Prague']]));
        $this->assertSame(['Brno', 'Ostrava'], $instance->get_checked(['user:city_values' => ['Ostrava', 'Brno']]));

        // Values stored by a select filter with the same identifier keep working.
        $equal = ['user:city_operator' => select::EQUAL_TO, 'user:city_value' => 'Brno'];
        $this->assertSame(['Brno'], $instance->get_checked($equal));
        $this->assertSame(['cb2'], $this->filter_users($instance, $equal));
        $notequal = ['user:city_operator' => select::NOT_EQUAL_TO, 'user:city_value' => 'Brno'];
        $this->assertSame(['Prague', 'Ostrava'], $instance->get_checked($notequal));
        $this->assertSame(['cb1', 'cb3'], $this->filter_users($instance, $notequal));
        $this->assertSame([], $instance->get_checked(['user:city_operator' => select::ANY_VALUE, 'user:city_value' => 'Brno']));
        $this->assertSame([], $instance->get_checked(['user:city_operator' => select::EQUAL_TO, 'user:city_value' => '-124567']));
        // Checkbox values win over select values of older saves.
        $this->assertSame(['Prague'], $instance->get_checked($equal + ['user:city_values' => ['Prague']]));
    }

    public function test_option_sql(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $generator->create_user(['username' => 'cb1', 'city' => 'Prague']);
        $generator->create_user(['username' => 'cb2', 'city' => '']);
        $generator->create_user(['username' => 'cb3', 'city' => 'Brno']);

        // Options with conditions of their own are combined with plain values of the field.
        $filter = (new filter(cities_or_none::class, 'city', new \lang_string('city'), 'user', 'u.city'))
            ->set_options(['none' => 'None', 'Brno' => 'Brno']);
        $instance = cities_or_none::create($filter);
        $this->assertSame(['cb2'], $this->filter_users($instance, ['user:city_values' => ['none']]));
        $this->assertSame(['cb2', 'cb3'], $this->filter_users($instance, ['user:city_values' => ['none', 'Brno']]));
    }
}
