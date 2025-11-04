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
 * Savy utility class.
 *
 * @package    theme_moove
 * @copyright  2025 Patrick Thibaudeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_moove\util;

/**
 * Savy utility class for handling Savy/Cria chatbot integration.
 *
 * @package    theme_moove
 * @copyright  2025 Patrick Thibaudeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class savy {

    /**
     * Execute embed request to Cria API.
     *
     * @param array $payload The payload to send to the API
     * @return string The response from the API
     */
    public static function exec_embed($payload) {
        // Get the plugin configuration.
        $config = get_config('theme_moove');

        // Check if required config exists.
        if (empty($config->savy_bot_id) || empty($config->savy_cria_api_key) || empty($config->cria_embed_url)) {
            // Return a test script for development/testing.
            return "console.log('Savy configuration missing. Please configure savy_bot_id, savy_cria_api_key, and cria_embed_url in theme settings.');\n" .
                   "window.CRIA = window.CRIA || {};\n" .
                   "window.CRIA['test'] = { switch: function() { alert('Savy is not configured. Please add Savy settings to your theme.'); } };";
        }

        $payloaddata = $payload;
        if (!empty($config->savy_json_encode)) {
            $payloaddata = json_encode($payload);
        }

        // Set the URL.
        $botid = $config->savy_bot_id;
        $apikey = $config->savy_cria_api_key;
        $url = $config->cria_embed_url . '/embed/' . $botid . '/load?hideLauncher=true';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payloaddata,
            CURLOPT_HTTPHEADER => [
                'accept: application/javascript',
                'X-Api-Key: ' . $apikey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($curl);
        curl_close($curl);

        // Check for curl errors.
        if ($curlerror) {
            return "console.error('Savy API Error: " . addslashes($curlerror) . "');\n" .
                   "alert('Failed to connect to Savy service: " . addslashes($curlerror) . "');";
        }

        // Check for HTTP errors.
        if ($httpcode !== 200) {
            return "console.error('Savy API returned HTTP " . $httpcode . "');\n" .
                   "alert('Savy service error (HTTP " . $httpcode . "). Please check your configuration.');";
        }

        // Validate response is JavaScript, not HTML.
        if (empty($response) || stripos(trim($response), '<html') !== false || stripos(trim($response), '<!doctype') !== false) {
            return "console.error('Savy API returned invalid response');\n" .
                   "alert('Savy service returned an invalid response. Please check your Bot ID and API key.');";
        }

        return $response;
    }

    /**
     * Checks if the user can render the savy button.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function can_render_savy() {
        global $USER;

        // Early bail out conditions.
        if (!isloggedin() || isguestuser() || user_not_fully_set_up($USER) ||
            get_user_preferences('auth_forcepasswordchange')) {
            return false;
        }

        // Policy agreement check.
        if (!$USER->policyagreed && !is_siteadmin()) {
            $manager = new \core_privacy\local\sitepolicy\manager();
            if ($manager->is_defined()) {
                return false;
            }
        }

        // If all checks pass, there is sufficient data.
        return true;
    }

    /**
     * Get the Savy payload for the current user.
     *
     * @param bool $anonymous Whether to return anonymous payload
     * @return array|null The payload array or null if user cannot access
     */
    public static function get_savy_payload($anonymous): ?array {
        // If anonymous, return a dummy payload.
        if ($anonymous) {
            return [
                'anonymous' => true,
                'user' => [
                    'id' => 0,
                    'name' => 'Anonymous',
                    'email' => '',
                    'role' => 'anonymous',
                ],
            ];
        }

        global $USER;

        // Early bail out conditions.
        if (!isloggedin() || isguestuser() || user_not_fully_set_up($USER) ||
            get_user_preferences('auth_forcepasswordchange')) {
            return null;
        }

        // Policy agreement check.
        if (!$USER->policyagreed && !is_siteadmin()) {
            $manager = new \core_privacy\local\sitepolicy\manager();
            if ($manager->is_defined()) {
                return null;
            }
        }

        // Build basic user payload.
        $payload = [
            'anonymous' => false,
            'user' => [
                'id' => $USER->id,
                'name' => fullname($USER),
                'email' => $USER->email,
                'username' => $USER->username,
                'idnumber' => $USER->idnumber,
            ],
        ];

        return $payload;
    }
}

