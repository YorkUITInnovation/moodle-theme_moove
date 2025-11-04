<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Launch savy external API.
 *
 * @package    theme_moove
 * @copyright  2025 Patrick Thibaudeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_moove\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/externallib.php");

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use theme_moove\util\savy;

/**
 * Launch savy by securely creating a chat in the backend using the user's information.
 * This avoids sending unencrypted data to the front-end.
 *
 * @package    theme_moove
 * @copyright  2025 Patrick Thibaudeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class launch_savy extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function launch_savy_parameters() {
        return new external_function_parameters([
            'anonymous' => new external_value(PARAM_BOOL, 'Whether to launch anonymously (for testing)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function launch_savy_returns() {
        return new external_single_structure([
            'script' => new external_value(PARAM_RAW, 'The embed payload to launch Cria', true),
            'botId' => new external_value(PARAM_TEXT, 'The bot ID', true),
            'stack' => new external_value(PARAM_TEXT, 'Fail Reason', false),
        ]);
    }

    /**
     * The actual implementation of the API method.
     *
     * @param bool $anonymous Whether to launch anonymously
     * @return array
     */
    public static function launch_savy($anonymous = false) {
        // Get the payload.
        try {
            $payload = savy::get_savy_payload($anonymous);
        } catch (\Exception $e) {
            return [
                'script' => "console.error('Retrieving savy payload failed!')",
                'stack' => $e->getMessage(),
                'botId' => 'test',
            ];
        }

        try {
            $savyresponse = savy::exec_embed($payload);
        } catch (\Exception $e) {
            return [
                'script' => "console.error('Creating savy chat failed!')",
                'stack' => $e->getMessage(),
                'botId' => 'test',
            ];
        }

        $botid = get_config('theme_moove', 'savy_bot_id');
        if (empty($botid)) {
            $botid = 'test';
        }

        return [
            'script' => $savyresponse,
            'botId' => $botid,
        ];
    }
}

