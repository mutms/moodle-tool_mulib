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

use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;
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
use tool_mulib\muform\autocomplete\base;

/**
 * Search endpoint of all single value autocomplete elements.
 *
 * The source class is reinstantiated from the request, its constructor does the access
 * control; only subclasses of the family base living in <component>\muform\autocomplete
 * namespaces of installed components are accepted. Plugins never add their own endpoint.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autocomplete {
    /**
     * Search options of a source.
     *
     * Browser endpoint of muform elements: access control is the login session and the element
     * source, not OAuth scopes, hence unscoped; requests without a browser session are refused.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return payload_response
     */
    #[route(
        path: '/muform/autocomplete',
        method: ['POST'],
        title: 'Search single value autocomplete options',
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(
                    content: [
                        'source' => new scalar_type(param::RAW, required: true),
                        'args' => new array_of_strings(param::INT, param::RAW),
                        'query' => new scalar_type(param::RAW, required: true),
                    ],
                ),
            ),
        ),
        requirelogin: new require_login(requirelogin: true, autologinguest: false),
    )]
    #[unscoped_resource]
    public function search(ServerRequestInterface $request, ResponseInterface $response): payload_response {
        global $PAGE;

        // Browser only endpoint, requests authenticated with OAuth2 tokens or API keys have no session.
        if (!\core\session\manager::is_session_active() && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            throw new \core\exception\access_denied_exception('accessdenied', 'admin');
        }

        if (isguestuser()) {
            throw new moodle_exception('noguest');
        }
        // Labels may be rendered from templates, which needs a page context.
        $PAGE->set_context(\core\context\system::instance());
        $body = (array)$request->getParsedBody();
        $source = self::instantiate((string)($body['source'] ?? ''), (array)($body['args'] ?? []));

        $maxitems = $source->get_maxitems();
        $list = $source->search((string)($body['query'] ?? ''), $maxitems);
        $payload = ['list' => null, 'overflow' => true, 'maxitems' => $maxitems];
        if ($list !== null) {
            $payload['list'] = [];
            foreach ($list as $value => $label) {
                $payload['list'][] = ['value' => (string)$value, 'label' => (string)$label];
            }
            $payload['overflow'] = false;
        }
        return new payload_response(payload: $payload, request: $request, response: $response);
    }

    /**
     * Reinstantiate a source class from its name and constructor arguments.
     *
     * @param string $class
     * @param array $args
     * @return base
     */
    public static function instantiate(string $class, array $args): base {
        if (!preg_match('/^([a-z][a-z0-9_]*)\\\\muform\\\\autocomplete\\\\[a-z][a-z0-9_]*$/D', $class, $matches)) {
            throw new invalid_parameter_exception('Invalid autocomplete source');
        }
        if (!\core_component::get_component_directory($matches[1])) {
            throw new invalid_parameter_exception('Invalid autocomplete source');
        }
        if (!class_exists($class) || !is_subclass_of($class, base::class)) {
            throw new invalid_parameter_exception('Invalid autocomplete source');
        }
        foreach ($args as $arg) {
            if (!is_scalar($arg)) {
                throw new invalid_parameter_exception('Invalid autocomplete source arguments');
            }
        }
        return new $class(...array_values($args));
    }
}
