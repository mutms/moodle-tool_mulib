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
 * Normalisation of date and time text typed into the muform datetime element.
 *
 * A pure function without session or data access: the browser sends the text,
 * timezone and language it was rendered with and gets back the timestamp,
 * the normalised text and the date components, or valid false.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datetime {
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
        path: '/muform/datetime',
        method: ['POST'],
        title: 'Normalise date and time text',
        description: 'Parse text typed into a muform datetime element in the given timezone and language',
        security: [],
        cookies: false,
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(
                    content: [
                        'text' => new scalar_type(param::RAW, required: true),
                        'timezone' => new scalar_type(param::RAW, required: true),
                        'lang' => new scalar_type(param::LANG, required: true),
                        'format' => new scalar_type(param::RAW, required: false),
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

        $format = (string)($body['format'] ?? '');
        if ($format === '') {
            $format = calendar::get_format($lang);
        }

        $text = (string)($body['text'] ?? '');
        try {
            $timestamp = calendar::parse($text, $tz, $format);
        } catch (\InvalidArgumentException $e) {
            $payload = ['valid' => false, 'text' => $text, 'timestamp' => null, 'components' => null, 'timezone' => $timezone];
            return new payload_response(payload: $payload, request: $request, response: $response);
        }

        $payload = calendar::describe($timestamp, $tz, $format);
        $payload['valid'] = true;
        $payload['timezone'] = $timezone;
        return new payload_response(payload: $payload, request: $request, response: $response);
    }
}
