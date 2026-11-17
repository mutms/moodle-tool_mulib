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

namespace tool_mulib\phpunit\muform\userpicker;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_user_source.php');

use tool_mulib\local\sql;
use tool_mulib\muform\autocomplete\member_cohorts;
use tool_mulib\muform\picker\filter;
use tool_mulib\muform\picker\filter_set;
use tool_mulib\muform\userpicker\base;
use tool_mulib\muform\userpicker\site_users;
use tool_mulib\phpunit\muform\fixtures\fake_user_source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Users picker family tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base::class)]
#[CoversClass(site_users::class)]
#[CoversClass(filter::class)]
#[CoversClass(filter_set::class)]
#[Group('MuTMS')]
final class base_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_search(): void {
        global $CFG;
        $this->setAdminUser();
        set_config('showuseridentity', 'email,idnumber');
        $generator = $this->getDataGenerator();
        $users = [];
        for ($i = 1; $i <= 55; $i++) {
            $users[$i] = $generator->create_user([
                'firstname' => 'User', 'lastname' => sprintf('Many%02d', $i), 'email' => "many$i@example.com", 'idnumber' => "ID$i",
            ]);
        }
        $hidden = $generator->create_user(['firstname' => 'User', 'lastname' => 'Hidden']);
        $suspended = $generator->create_user(['firstname' => 'User', 'lastname' => 'Many99', 'suspended' => 1]);
        $source = new fake_user_source();

        $this->assertSame([
            ['name' => 'email', 'label' => 'Email address'],
            ['name' => 'idnumber', 'label' => 'ID number'],
        ], $source->get_columns());

        $result = $source->search('Many', [], 0);
        $this->assertSame(55, $result['total']);
        $this->assertSame(0, $result['page']);
        $this->assertSame(base::PERPAGE, $result['perpage']);
        $this->assertCount(50, $result['rows']);
        $this->assertSame((int)$users[1]->id, $result['rows'][0]['id']);
        $this->assertSame(['email' => 'many1@example.com', 'idnumber' => 'ID1'], $result['rows'][0]['fields']);
        $this->assertSame('User Many01', $result['rows'][0]['fullname']);
        $this->assertStringContainsString('many1@example.com', $result['rows'][0]['label']);

        $result = $source->search('Many', [(string)$users[51]->id], 1);
        $this->assertSame(54, $result['total']);
        $this->assertSame(1, $result['page']);
        $expected = [(int)$users[52]->id, (int)$users[53]->id, (int)$users[54]->id, (int)$users[55]->id];
        $this->assertSame($expected, array_column($result['rows'], 'id'));
        // Pages out of range show the last page.
        $this->assertSame(1, $source->search('Many', [], 9)['page']);

        // Identity fields are searched.
        $result = $source->search('many7@', [], 0);
        $this->assertSame([(int)$users[7]->id], array_column($result['rows'], 'id'));
        $this->assertSame(0, $source->search('Hidden', [], 0)['total']);
        $this->assertSame(0, $source->search('Many99', [], 0)['total']);
        // Admin and the 55 users, not guest, hidden or suspended users.
        $result = $source->search('', [], 1);
        $this->assertSame(56, $result['total']);
        $ids = array_merge(array_column($source->search('', [], 0)['rows'], 'id'), array_column($result['rows'], 'id'));
        $this->assertNotContains((int)$CFG->siteguest, $ids);
        $this->assertNotContains((int)$hidden->id, $ids);
        $this->assertNotContains((int)$suspended->id, $ids);

        // Without the capability identity fields are not shown.
        $this->setUser($generator->create_user());
        $this->assertSame([], $source->get_columns());
        $this->assertSame([], $source->search('many7@', [], 0)['rows']);
    }

    public function test_labels_and_validate(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Visible']);
        $hidden = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Hidden']);
        $kept = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Kept']);
        $suspended = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Suspended', 'suspended' => 1]);
        $source = new fake_user_source();

        $labels = $source->labels([(string)$user->id, (string)$hidden->id, (string)$kept->id, '0', 'x', (string)$suspended->id]);
        $this->assertSame([(string)$user->id, (string)$kept->id, (string)$suspended->id], array_map('strval', array_keys($labels)));
        $this->assertStringContainsString('Anna Visible', $labels[$user->id]);
        $this->assertSame(0, $source->search('Kept', [], 0)['total']);
        $errors = $source->validate([(string)$user->id, (string)$suspended->id]);
        $this->assertSame([(string)$suspended->id => 'Suspended user'], $errors);

        // Current users of the edited item stay valid, they are not offered by the search.
        $deleted = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Hidden']);
        delete_user($deleted);
        $source->currentuserids = [(int)$hidden->id, (int)$deleted->id, (int)$user->id];
        $labels = $source->labels([(string)$user->id, (string)$hidden->id, (string)$deleted->id, (string)$kept->id]);
        $this->assertSame([(string)$user->id, (string)$kept->id, (string)$hidden->id], array_map('strval', array_keys($labels)));
        $this->assertStringContainsString('Anna Hidden', $labels[$hidden->id]);
        $this->assertSame([], $source->labels([(string)$user->id . 'x']));
        $this->assertSame(0, $source->search('Hidden', [], 0)['total']);
        $source->currentuserids = [];
        $this->assertArrayNotHasKey($hidden->id, $source->labels([(string)$hidden->id]));

        $panel = $source->export_panel(true);
        $this->assertSame('tool_mulib/muform/userpicker/panel', $panel['panel']);
        $this->assertSame('Select users', $panel['strings']['title']);
        $this->assertSame('Select user', $source->export_panel(false)['strings']['title']);
    }

    public function test_filters(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $anna = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Filtered', 'city' => 'Prague']);
        $bert = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Filtered', 'city' => 'Brno', 'suspended' => 1]);
        $cyril = $generator->create_user(['firstname' => 'Cyril', 'lastname' => 'Filtered', 'city' => 'Brno']);
        $ids = fn(array $result): array => array_column($result['rows'], 'id');
        $yesno = [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']];

        $source = new site_users(\core\context\system::instance()->id);
        $active = ['key' => 'active', 'type' => 'boolean', 'label' => 'Active', 'options' => $yesno, 'value' => '1'];
        $active += ['default' => '1', 'pinned' => true, 'anylabel' => 'Any'];
        $cohort = ['key' => 'cohort', 'type' => 'autocomplete', 'label' => 'Cohort', 'options' => [], 'value' => null];
        $cohort += ['default' => null, 'pinned' => false, 'anylabel' => 'Any'];
        $cohort['source'] = ['class' => member_cohorts::class, 'args' => [\core\context\system::instance()->id]];
        $cohort['valuelabel'] = null;

        // Suspended users are hidden by default.
        $result = $source->search('Filtered', [], 0);
        $this->assertSame([(int)$anna->id, (int)$cyril->id], $ids($result));
        $this->assertSame([$active, $cohort], $result['filters']);
        $result = $source->search('Filtered', [], 0, ['active' => '1']);
        $this->assertSame([(int)$anna->id, (int)$cyril->id], $ids($result));
        $this->assertSame([$active, $cohort], $result['filters']);

        $result = $source->search('Filtered', [], 0, ['active' => '0']);
        $this->assertSame([(int)$bert->id], $ids($result));
        $this->assertSame([array_merge($active, ['value' => '0']), $cohort], $result['filters']);

        // Removed filters and invalid values do not restrict the list.
        foreach ([[], ['active' => ''], ['active' => '2'], ['active' => ['1']], ['other' => '1']] as $values) {
            $result = $source->search('Filtered', [], 0, $values);
            $this->assertSame([(int)$anna->id, (int)$bert->id, (int)$cyril->id], $ids($result));
            $this->assertSame([array_merge($active, ['value' => null]), $cohort], $result['filters']);
        }

        // Sources that do not allow suspended users have no filter for them.
        $source = new fake_user_source();
        $result = $source->search('Filtered', [], 0, ['active' => '0']);
        $this->assertSame([(int)$anna->id, (int)$cyril->id], $ids($result));
        $this->assertSame([$cohort], $result['filters']);

        // Filters of sources are combined with the candidates and the other filters.
        $source = new class extends base {
            #[\Override]
            public function get_args(): array {
                return [];
            }

            #[\Override]
            protected function get_context(): \core\context {
                return \core\context\system::instance();
            }

            #[\Override]
            protected function get_candidates_sql(): ?sql {
                return new sql("u.lastname = 'Filtered' OR u.lastname = ?", ['Nobody']);
            }

            #[\Override]
            protected function get_filters(): array {
                $filters = parent::get_filters();
                $filters[] = filter::select(
                    'city',
                    'City',
                    ['Prague' => 'Praha', 'Brno' => 'Brno'],
                    fn(string $value): sql => new sql('u.city = :city OR u.city = :city2', ['city' => $value, 'city2' => 'x']),
                    'Brno'
                );
                return $filters;
            }
        };
        $city = [
            'key' => 'city',
            'type' => 'select',
            'label' => 'City',
            'options' => [['value' => 'Prague', 'label' => 'Praha'], ['value' => 'Brno', 'label' => 'Brno']],
            'value' => 'Brno',
            'default' => 'Brno',
            'pinned' => false,
            'anylabel' => 'Any',
        ];
        $result = $source->search('', [], 0);
        $this->assertSame([(int)$cyril->id], $ids($result));
        $this->assertSame([$active, $cohort, $city], $result['filters']);
        $result = $source->search('', [], 0, ['city' => 'Brno']);
        $this->assertSame([(int)$bert->id, (int)$cyril->id], $ids($result));
        $this->assertSame([array_merge($active, ['value' => null]), $cohort, $city], $result['filters']);
        $result = $source->search('', [], 0, ['active' => '1', 'city' => 'Prague']);
        $this->assertSame([(int)$anna->id], $ids($result));
        $result = $source->search('', [], 0, ['city' => 'Paris']);
        $this->assertSame([(int)$anna->id, (int)$bert->id, (int)$cyril->id], $ids($result));

        // Filters do not change what may be stored.
        $this->assertSame([], $source->validate([(string)$bert->id]));
        $this->assertArrayHasKey($bert->id, $source->labels([(string)$bert->id]));
    }

    public function test_cohort_filter(): void {
        $syscontext = \core\context\system::instance();
        $generator = $this->getDataGenerator();
        $anna = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Filtered']);
        $bert = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Filtered', 'suspended' => 1]);
        $cyril = $generator->create_user(['firstname' => 'Cyril', 'lastname' => 'Filtered']);
        $cohort1 = $generator->create_cohort(['name' => 'Cohort <b>1</b>']);
        $cohort2 = $generator->create_cohort(['name' => 'Cohort 2']);
        cohort_add_member($cohort1->id, $anna->id);
        cohort_add_member($cohort1->id, $bert->id);
        cohort_add_member($cohort2->id, $cyril->id);
        $ids = fn(array $result): array => array_column($result['rows'], 'id');

        $this->setAdminUser();
        $source = new site_users($syscontext->id);
        $result = $source->search('Filtered', [], 0, ['active' => '1', 'cohort' => (string)$cohort1->id]);
        $this->assertSame([(int)$anna->id], $ids($result));
        $this->assertSame('cohort', $result['filters'][1]['key']);
        $this->assertSame((string)$cohort1->id, $result['filters'][1]['value']);
        $this->assertSame('Cohort 1', $result['filters'][1]['valuelabel']);
        $result = $source->search('Filtered', [], 0, ['cohort' => (string)$cohort1->id]);
        $this->assertSame([(int)$anna->id, (int)$bert->id], $ids($result));
        $result = $source->search('Filtered', [], 0, ['cohort' => $cohort2->id]);
        $this->assertSame([(int)$cyril->id], $ids($result));
        foreach (['0', '-1', 'x', (string)($cohort2->id + 1000)] as $value) {
            $result = $source->search('Filtered', [], 0, ['cohort' => $value]);
            $this->assertSame([(int)$anna->id, (int)$bert->id, (int)$cyril->id], $ids($result));
            $this->assertNull($result['filters'][1]['value']);
        }

        // Cohorts whose members may not be listed are ignored.
        $viewer = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $roleid, $syscontext);
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $viewer->id, $syscontext);
        $this->setUser($viewer);
        $source = new site_users($syscontext->id);
        $result = $source->search('Filtered', [], 0, ['cohort' => (string)$cohort2->id]);
        $this->assertSame([(int)$anna->id, (int)$bert->id, (int)$cyril->id], $ids($result));
        $this->assertNull($result['filters'][1]['value']);
        $this->assertNull($result['filters'][1]['valuelabel']);

        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $syscontext);
        $result = $source->search('Filtered', [], 0, ['cohort' => (string)$cohort2->id]);
        $this->assertSame([(int)$cyril->id], $ids($result));
        $this->assertSame('Cohort 2', $result['filters'][1]['valuelabel']);
    }

    public function test_filter_definition(): void {
        $filter = filter::boolean('active', 'Active', new sql('u.suspended = 0'), new sql('u.suspended = 1'));
        $this->assertSame('active', $filter->get_key());
        $this->assertNull($filter->get_default());
        $this->assertSame('1', $filter->normalise(1));
        $this->assertNull($filter->normalise(true));
        $this->assertNull($filter->normalise('yes'));
        $this->assertSame('u.suspended = 0', $filter->get_sql('1')->sql);
        $this->assertSame('u.suspended = 1', $filter->get_sql('0')->sql);
        $this->assertNull($filter->get_sql(null));
        $this->assertNull($filter->get_sql('2'));
        $this->assertSame('0', filter::boolean('active', 'Active', new sql('1=1'), new sql('1=0'), false)->get_default());
        $this->assertFalse($filter->export(null)['pinned']);
        $exported = $filter->set_pinned(true, 'All users')->export(null);
        $this->assertTrue($exported['pinned']);
        $this->assertSame('All users', $exported['anylabel']);

        $set = new filter_set([$filter], ['active' => '0', 'other' => '1']);
        $this->assertSame('0', $set->get_value('active'));
        $this->assertNull($set->get_value('other'));
        $this->assertSame(['(u.suspended = 1)'], array_column($set->get_sqls(), 'sql'));
        $this->assertSame([], (new filter_set([$filter], null))->get_sqls());
        $this->assertNull(filter_set::clean_values(null));
        $this->assertNull(filter_set::clean_values('active'));
        $this->assertSame(['active' => '1'], filter_set::clean_values((object)['active' => 1, 'bad' => ['x']]));
        $this->assertSame(
            [
                'filteradd', 'filterremove', 'filterresetall', 'filterresetdefault',
                'filternoresults', 'filtersearching', 'filtertoomanyresults', 'search',
            ],
            array_keys(filter_set::get_panel_strings())
        );
        try {
            new filter_set([$filter, $filter], null);
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Duplicate picker filter: active', $ex->getMessage());
        }

        $this->setAdminUser();
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 1']);
        $source = new member_cohorts(\core\context\system::instance()->id);
        $where = fn(string $value): sql => new sql('x.cohortid = ?', [(int)$value]);
        $filter = filter::autocomplete('cohort', 'Cohort', $source, $where, (string)$cohort->id);
        $this->assertSame((string)$cohort->id, $filter->get_default());
        $this->assertSame((string)$cohort->id, $filter->normalise((int)$cohort->id));
        $this->assertNull($filter->normalise((string)($cohort->id + 1)));
        $this->assertNull($filter->normalise(''));
        $this->assertSame([(int)$cohort->id], array_values($filter->get_sql((string)$cohort->id)->params));
        $this->assertNull($filter->get_sql((string)($cohort->id + 1)));
        $exported = $filter->export((string)$cohort->id);
        $this->assertSame('autocomplete', $exported['type']);
        $this->assertSame([], $exported['options']);
        $expected = ['class' => member_cohorts::class, 'args' => [\core\context\system::instance()->id]];
        $this->assertSame($expected, $exported['source']);
        $this->assertSame('Cohort 1', $exported['valuelabel']);
        $this->assertNull($filter->export(null)['valuelabel']);

        try {
            filter::boolean('Active', 'Active', new sql('1=1'), new sql('1=0'));
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Invalid picker filter key: Active', $ex->getMessage());
        }
        try {
            filter::select('city', 'City', ['a' => 'A'], fn(string $value): sql => new sql('1=1'), 'b');
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Default value of picker filter is not an option: city', $ex->getMessage());
        }
    }

    public function test_site_users(): void {
        $syscontext = \core\context\system::instance();
        $this->setAdminUser();
        $source = new site_users($syscontext->id);
        $this->assertSame([$syscontext->id], $source->get_args());
        $this->assertInstanceOf(site_users::class, base::instantiate(site_users::class, [$syscontext->id]));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\core\exception\required_capability_exception::class);
        new site_users($syscontext->id);
    }
}
