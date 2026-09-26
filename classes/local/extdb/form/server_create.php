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

namespace tool_mulib\local\extdb\form;

use tool_mulib\local\extdb\pdb;
use tool_mulib\local\extdb\server;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\secret;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;

/**
 * Create a new server form.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class server_create extends form {
    #[\Override]
    protected function definition(): void {
        $name = (new text('name', get_string('name'), ['maxlength' => 255, 'width' => 'medium']))
            ->set_required(true);
        $this->add($name);

        $dsnattributes = ['type' => 'rawtext', 'maxlength' => 1333, 'width' => 'full'];
        $dsn = (new text('dsn', get_string('extdb_server_dsn', 'tool_mulib'), $dsnattributes))
            ->set_required(true)
            ->add_help_button('extdb_server_dsn', 'tool_mulib');
        $this->add($dsn);

        $this->add(new inforawhtml('extensions', '', server::get_pdo_extensions_html()));

        $this->add(new text('dbuser', get_string('extdb_server_dbuser', 'tool_mulib'), ['type' => 'rawtext', 'maxlength' => 100]));
        $this->add(new secret('dbpass', get_string('extdb_server_dbpass', 'tool_mulib'), ['maxlength' => 100]));

        $dboptionsattributes = ['type' => 'rawtext', 'rows' => 3];
        $dboptions = (new textarea('dboptions', get_string('extdb_server_dboptions', 'tool_mulib'), $dboptionsattributes))
            ->add_help_button('extdb_server_dboptions', 'tool_mulib');
        $this->add($dboptions);

        $post = $this->get_post_data();
        if (!empty($post['check'])) {
            $status = server::get_check_status_html($post);
            $this->add(new inforawhtml('status', get_string('extdb_server_status', 'tool_mulib'), $status));
        }

        $this->add(new textarea('note', get_string('note', 'core_notes'), ['rows' => 2]));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('extdb_server_create', 'tool_mulib')), 'buttons');
        $this->add(new reload('check', get_string('extdb_server_check', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        global $DB;

        if ($DB->record_exists_select('tool_mulib_extdb_server', "LOWER(name) = LOWER(?)", [trim($data['name'])])) {
            $allerrors['name'][] = get_string('error');
        }
        if ($data['dboptions'] !== '' && server::validate_dboptions($data['dboptions']) !== null) {
            $allerrors['dboptions'][] = get_string('error');
        }
    }
}
