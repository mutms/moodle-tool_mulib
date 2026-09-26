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

namespace tool_mulib\muform\handler;

use core\url;
use Psr\Http\Message\ResponseInterface;
use tool_mulib\muform\form;

/**
 * Answers form requests as a full page.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page extends \tool_mulib\muform\handler {
    #[\Override]
    public function is_dialog(): bool {
        return false;
    }

    #[\Override]
    public function render(form|callable $content, ?string $title = null): ResponseInterface {
        global $OUTPUT;

        // The page title and heading are set up by the handler script, pages do not need the title.
        $html = (string)$OUTPUT->header();
        // NOTE: $OUTPUT was replaced by the real renderer in header(), closures must not capture the old one.
        $html .= self::get_html($content, $OUTPUT);
        $html .= (string)$OUTPUT->footer();

        if ($this->response) {
            $this->response->getBody()->write($html);
            return $this->response;
        }
        echo $html;
        exit;
    }

    #[\Override]
    public function submitted(url $returnurl, array $data = []): ResponseInterface {
        return $this->redirect($returnurl);
    }

    #[\Override]
    public function cancelled(url $returnurl): ResponseInterface {
        return $this->redirect($returnurl);
    }

    /**
     * Redirect to URL.
     *
     * @param url $url
     * @return ResponseInterface
     */
    private function redirect(url $url): ResponseInterface {
        if ($this->response) {
            return \core\router\util::redirect($this->response, $url);
        }
        redirect($url);
    }
}
