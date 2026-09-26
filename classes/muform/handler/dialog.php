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

use core\exception\coding_exception;
use core\url;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use tool_mulib\muform\form;

/**
 * Answers form requests from native dialogs with JSON.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dialog extends \tool_mulib\muform\handler {
    /** @var string request header marking dialog requests */
    public const string HEADER = 'X-Muform-Dialog';

    /** @var string reload the page after submission */
    public const string ACTION_RELOAD = 'reload';
    /** @var string redirect to the URL returned by handler after submission */
    public const string ACTION_REDIRECT = 'redirect';
    /** @var string close the dialog only, page JS may listen for muform:dialog-submitted event */
    public const string ACTION_NOTHING = 'nothing';

    #[\Override]
    public function is_dialog(): bool {
        return true;
    }

    #[\Override]
    public function render(form|callable $content, ?string $title = null): ResponseInterface {
        global $OUTPUT, $PAGE;

        if (!defined('PREFERRED_RENDERER_TARGET')) {
            define('PREFERRED_RENDERER_TARGET', RENDERER_TARGET_GENERAL);
        }
        // Theme initialisation is required before JavaScript can be collected, the page output is not needed.
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();

        ob_start();
        try {
            // NOTE: $OUTPUT was replaced by the real renderer in header(), closures must not capture the old one.
            $html = self::get_html($content, $OUTPUT);
            $stray = ob_get_contents();
            $javascript = $PAGE->requires->get_end_code();
        } finally {
            ob_end_clean();
            $PAGE->end_collecting_javascript_requirements();
        }
        if ($stray !== '') {
            $html = $stray . $html;
        }

        return $this->json([
            'status' => 'render',
            'title' => clean_string($title ?? $PAGE->heading),
            'html' => $html,
            'javascript' => $javascript,
        ]);
    }

    #[\Override]
    public function submitted(url $returnurl, array $data = []): ResponseInterface {
        return $this->json([
            'status' => 'submitted',
            'redirecturl' => $returnurl->out(false),
            'data' => $data,
        ]);
    }

    #[\Override]
    public function cancelled(url $returnurl): ResponseInterface {
        return $this->json([
            'status' => 'cancelled',
            'redirecturl' => $returnurl->out(false),
        ]);
    }

    /**
     * Write JSON payload, classic scripts send it and stop.
     *
     * @param array $payload
     * @return ResponseInterface
     */
    private function json(array $payload): ResponseInterface {
        $response = $this->response ?? \core\di::get(ResponseFactoryInterface::class)->createResponse();
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        if ($this->response) {
            return $response;
        }
        self::emit($response);
    }

    /**
     * Send response and stop.
     *
     * @param ResponseInterface $response
     * @return never
     */
    private static function emit(ResponseInterface $response): never {
        if (headers_sent()) {
            throw new coding_exception('Headers were already sent, dialog response cannot be emitted');
        }
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            header($name . ': ' . implode(', ', $values));
        }
        echo (string)$response->getBody();
        exit;
    }
}
