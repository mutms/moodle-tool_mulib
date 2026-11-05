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
            'component' => 'tool_muprog',
            'type' => 'allocation',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);
        $query2 = query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 2',
            'component' => 'tool_mucohort',
            'type' => 'rule',
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

        $this->assertSame('Program allocation', $this->format_column('type', $query1->id));
        $this->assertSame('Cohort rule', $this->format_column('type', $query2->id));
        $this->assertSame('xyz&lt;b&gt;', $this->format_column('type', $query3id));
    }
}
