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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\reportbuilder\local\entities;

use tool_mulib\local\extdb\query;
use tool_mulib\tests\extdb\fake_queries;
use core_reportbuilder\local\filters\select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * External database query report entity tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\reportbuilder\local\entities\query::class)]
#[Group('MuTMS')]
final class query_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        fake_queries::register();
    }

    /**
     * Returns formatted value of entity column for given query.
     *
     * @param string $columnname
     * @param int $queryid
     * @return mixed
     */
    private function format_column(string $columnname, int $queryid) {
        global $DB;

        $entity = new \tool_mulib\reportbuilder\local\entities\query();
        $entity->initialise();
        $alias = $entity->get_table_alias('tool_mulib_extdb_query');
        $column = $entity->get_column($columnname);

        $fields = implode(', ', $column->get_fields());
        $sql = "SELECT {$fields}
                  FROM {tool_mulib_extdb_query} {$alias}
                 WHERE {$alias}.id = :id";
        $row = $DB->get_record_sql($sql, ['id' => $queryid], MUST_EXIST);

        return $column->format_value((array)$row);
    }

    public function test_type_column(): void {
        global $DB;

        /** @var \tool_mulib_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mulib');
        $server = $generator->create_extdb_server([]);

        $query1 = query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 1',
            'component' => 'tool_mulib',
            'type' => 'alpha',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);
        $query2 = query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 2',
            'component' => 'tool_mulib',
            'type' => 'beta',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);
        // Query type from plugin that is not available, it cannot be created via API.
        $query3id = $DB->insert_record('tool_mulib_extdb_query', (object)[
            'name' => 'Query 3',
            'contextid' => \core\context\system::instance()->id,
            'serverid' => $server->id,
            'component' => 'tool_unknown',
            'type' => 'xyz<b>',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);

        $this->assertSame('Alpha query', $this->format_column('type', $query1->id));
        $this->assertSame('Beta query', $this->format_column('type', $query2->id));
        $this->assertSame('xyz&lt;b&gt;', $this->format_column('type', $query3id));
    }

    /**
     * Returns ids of queries matching entity filter.
     *
     * @param string $filtername
     * @param array $values
     * @return array
     */
    private function get_filtered_ids(string $filtername, array $values): array {
        global $DB;

        $entity = new \tool_mulib\reportbuilder\local\entities\query();
        $entity->initialise();
        $alias = $entity->get_table_alias('tool_mulib_extdb_query');
        $filter = $entity->get_filter($filtername);
        $classname = $filter->get_filter_class();
        [$where, $params] = $classname::create($filter)->get_sql_filter($values);
        $where = $where === '' ? '1=1' : $where;

        $sql = "SELECT {$alias}.id
                  FROM {tool_mulib_extdb_query} {$alias}
                 WHERE {$where}
              ORDER BY {$alias}.id ASC";
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    public function test_type_filter(): void {
        /** @var \tool_mulib_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mulib');
        $server = $generator->create_extdb_server([]);

        $query1 = query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 1',
            'component' => 'tool_mulib',
            'type' => 'alpha',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);
        $query2 = query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 2',
            'component' => 'tool_mulib',
            'type' => 'beta',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);
        $query3 = query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 3',
            'component' => 'tool_mulib',
            'type' => 'alpha',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);

        $entity = new \tool_mulib\reportbuilder\local\entities\query();
        $entity->initialise();
        $filter = $entity->get_filter('type');
        $options = $filter->get_options();
        $this->assertSame('Alpha query', $options['tool_mulib/alpha']);
        $this->assertSame('Beta query', $options['tool_mulib/beta']);

        $this->assertSame(
            [(int)$query1->id, (int)$query2->id, (int)$query3->id],
            $this->get_filtered_ids('type', ['query:type_operator' => select::ANY_VALUE])
        );
        $this->assertSame(
            [(int)$query1->id, (int)$query3->id],
            $this->get_filtered_ids('type', [
                'query:type_operator' => select::EQUAL_TO,
                'query:type_value' => 'tool_mulib/alpha',
            ])
        );
        $this->assertSame(
            [(int)$query2->id],
            $this->get_filtered_ids('type', [
                'query:type_operator' => select::EQUAL_TO,
                'query:type_value' => 'tool_mulib/beta',
            ])
        );
        $this->assertSame(
            [(int)$query2->id],
            $this->get_filtered_ids('type', [
                'query:type_operator' => select::NOT_EQUAL_TO,
                'query:type_value' => 'tool_mulib/alpha',
            ])
        );
    }
}
