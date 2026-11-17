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

namespace tool_mulib\route\api\muform;

use core\param;
use core\router\require_login;
use core\router\route;
use core\router\scope\unscoped_resource;
use core\router\schema\objects\array_of_strings;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\request_body;
use core\router\schema\response\content\payload_response_type;
use core\router\schema\response\payload_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tool_mulib\muform\picker\filter_set;
use tool_mulib\muform\userpicker\base;

/**
 * Search endpoint of the users picker family.
 *
 * The source class is reinstantiated from the request, its constructor does the access control;
 * only subclasses of tool_mulib\muform\userpicker\base living in <component>\muform\userpicker
 * namespaces of installed components are accepted.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userpicker {
    /**
     * One page of users offered by a source.
     *
     * Browser endpoint of muform elements: access control is the login session and the element
     * source, not OAuth scopes, hence unscoped; requests without a browser session are refused.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return payload_response
     */
    #[route(
        path: '/muform/userpicker/search',
        method: ['POST'],
        title: 'Search users picker candidates',
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(
                    content: [
                        'source' => new scalar_type(param::RAW, required: true),
                        'args' => new array_of_strings(param::INT, param::RAW),
                        'query' => new scalar_type(param::RAW, required: true),
                        'exclude' => new array_of_strings(param::INT, param::INT),
                        'page' => new scalar_type(param::INT),
                        'filters' => new array_of_strings(param::ALPHANUMEXT, param::RAW),
                    ],
                ),
            ),
        ),
        requirelogin: new require_login(requirelogin: true, autologinguest: false),
    )]
    #[unscoped_resource]
    public function search(ServerRequestInterface $request, ResponseInterface $response): payload_response {
        base::require_browser_session();
        $body = (array)$request->getParsedBody();
        $class = $body['source'] ?? '';
        $source = base::instantiate(is_string($class) ? $class : '', (array)($body['args'] ?? []));

        $exclude = [];
        foreach ((array)($body['exclude'] ?? []) as $value) {
            if (is_scalar($value)) {
                $exclude[] = (string)(int)$value;
            }
        }
        $query = $body['query'] ?? '';
        $page = $body['page'] ?? 0;
        $filters = filter_set::clean_values($body['filters'] ?? null);
        $payload = $source->search(is_string($query) ? $query : '', $exclude, is_scalar($page) ? (int)$page : 0, $filters);
        return new payload_response(payload: $payload, request: $request, response: $response);
    }
}
