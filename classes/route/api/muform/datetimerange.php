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
use core\param;
use core\router\route;
use core\router\scope\unscoped_resource;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\request_body;
use core\router\schema\response\content\payload_response_type;
use core\router\schema\response\payload_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tool_mulib\muform\util\calendar;

/**
 * Normalisation of date and time range text typed into the muform datetimerange element.
 *
 * A pure function without session or data access: the browser sends the text,
 * timezone, language, default time and default duration it was rendered with and gets back
 * both timestamps, the normalised text, the date components and a hint, or valid false.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datetimerange {
    /**
     * Parse text and describe the result.
     *
     * Browser endpoint of muform elements: access control is the login session and the element
     * source, not OAuth scopes, hence unscoped.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return payload_response
     */
    #[route(
        path: '/muform/datetimerange',
        method: ['POST'],
        title: 'Normalise date and time range text',
        description: 'Parse text typed into a muform datetimerange element in the given timezone and language',
        security: [],
        cookies: false,
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(
                    content: [
                        'text' => new scalar_type(param::RAW, required: true),
                        'timezone' => new scalar_type(param::RAW, required: true),
                        'lang' => new scalar_type(param::LANG, required: true),
                        'defaulthour' => new scalar_type(param::INT, required: false),
                        'defaultminute' => new scalar_type(param::INT, required: false),
                        'defaultduration' => new scalar_type(param::INT, required: false),
                    ],
                ),
            ),
        ),
    )]
    #[unscoped_resource]
    public function normalise(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): payload_response {
        $body = (array)$request->getParsedBody();

        $timezone = (string)($body['timezone'] ?? '');
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new invalid_parameter_exception('Invalid timezone');
        }
        $tz = new \DateTimeZone($timezone);

        $lang = (string)($body['lang'] ?? '');
        if ($lang === '' || !get_string_manager()->translation_exists($lang)) {
            throw new invalid_parameter_exception('Invalid language');
        }

        $defaulthour = (int)($body['defaulthour'] ?? 0);
        $defaultminute = (int)($body['defaultminute'] ?? 0);
        if ($defaulthour < 0 || $defaulthour > 23 || $defaultminute < 0 || $defaultminute > 59) {
            throw new invalid_parameter_exception('Invalid default time');
        }
        $defaultduration = isset($body['defaultduration']) ? (int)$body['defaultduration'] : null;
        if ($defaultduration !== null && $defaultduration <= 0) {
            throw new invalid_parameter_exception('Invalid default duration');
        }

        $text = (string)($body['text'] ?? '');
        try {
            $range = calendar::parse_range($text, $tz, $lang, $defaulthour, $defaultminute, $defaultduration);
        } catch (\InvalidArgumentException $e) {
            $payload = calendar::describe_range(null, $tz, $lang);
            $payload['text'] = $text;
            $payload['valid'] = false;
            $payload['timezone'] = $timezone;
            return new payload_response(payload: $payload, request: $request, response: $response);
        }

        // Duration texts in the hint use the language of the page.
        $previous = force_current_language($lang);
        try {
            $payload = calendar::describe_range($range, $tz, $lang);
        } finally {
            force_current_language($previous);
        }
        $payload['valid'] = true;
        $payload['timezone'] = $timezone;
        return new payload_response(payload: $payload, request: $request, response: $response);
    }
}
