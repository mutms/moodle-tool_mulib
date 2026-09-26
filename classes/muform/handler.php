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

namespace tool_mulib\muform;

use core\exception\coding_exception;
use core\url;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Answers a form request either as a full page or as a native dialog.
 *
 * The same handler URL serves the full page and the dialog, the dialog JS marks
 * its requests with the X-Muform-Dialog header. Forms know nothing about dialogs:
 *
 *   $handler = handler::from_request();
 *   if ($form->is_cancelled()) {
 *       $handler->cancelled($returnurl);
 *   }
 *   if ($data = $form->get_data()) {
 *       ...
 *       $handler->submitted($returnurl);
 *   }
 *   $handler->render($form);
 *
 * In classic scripts every answer is sent immediately and the script stops.
 * Router controllers pass the request and response and return the answers instead.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class handler {
    /**
     * Use from_request() instead.
     *
     * @param ResponseInterface|null $response router response, null in classic scripts
     */
    final protected function __construct(
        /** @var ResponseInterface|null router response, null in classic scripts */
        protected readonly ?ResponseInterface $response
    ) {
    }

    /**
     * Returns the dialog handler when the request comes from a muform dialog, the page handler otherwise.
     *
     * @param ServerRequestInterface|null $request router request, null in classic scripts
     * @param ResponseInterface|null $response router response, null in classic scripts
     * @return handler
     */
    final public static function from_request(
        ?ServerRequestInterface $request = null,
        ?ResponseInterface $response = null
    ): handler {
        if ($request) {
            if (!$response) {
                throw new coding_exception('Router response is required');
            }
            $value = $request->getHeaderLine(handler\dialog::HEADER);
        } else {
            if ($response) {
                throw new coding_exception('Router request is required');
            }
            $value = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper(handler\dialog::HEADER))] ?? '';
        }
        return ($value === '1') ? new handler\dialog($response) : new handler\page($response);
    }

    /**
     * Is this a dialog request?
     *
     * @return bool
     */
    abstract public function is_dialog(): bool;

    /**
     * Render the form, or other content, and stop.
     *
     * @param form|callable $content form, or callable receiving the page renderer and returning html
     * @param string|null $title dialog title, the page heading by default
     * @return ResponseInterface the answer in router controllers, classic scripts stop here
     */
    abstract public function render(form|callable $content, ?string $title = null): ResponseInterface;

    /**
     * The form was submitted and processed, go back and stop.
     *
     * @param url $returnurl where to go, dialogs go there only with the redirect action
     * @param array $data extra data for page JavaScript listening to muform:dialog-submitted event
     * @return ResponseInterface the answer in router controllers, classic scripts stop here
     */
    abstract public function submitted(url $returnurl, array $data = []): ResponseInterface;

    /**
     * The form was cancelled, go back and stop.
     *
     * @param url $returnurl where to go, dialogs just close
     * @return ResponseInterface the answer in router controllers, classic scripts stop here
     */
    abstract public function cancelled(url $returnurl): ResponseInterface;

    /**
     * Content html.
     *
     * @param form|callable $content
     * @param \core\output\core_renderer $output
     * @return string
     */
    final protected static function get_html(form|callable $content, \core\output\core_renderer $output): string {
        if ($content instanceof form) {
            return $content->render($output);
        }
        return (string)$content($output);
    }
}
