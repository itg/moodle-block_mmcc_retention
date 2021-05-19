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

/*
 * A block for showing Retention Management System alert counts in Moodle.
 *
 * @package     block_mmcc_retention
 * @copyright   2017 Matt Rice <mrice1@midmich.edu>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define("REQUEST_URL", "https://rms.midmich.edu/instructor/users/{{user_name}}/course_sections.json");
define("CURL_TIMEOUT", 1000);      //ms

class block_mmcc_retention extends block_list {

    public function init() {
        $this->title = get_string('mmcc_retention', 'block_mmcc_retention');
    }

    public function instance_allow_multiple() {
        // only one instance of this block is required
        return false;
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        global $COURSE, $USER, $OUTPUT;
        $context = context_course::instance($COURSE->id);

        $this->content          = new stdClass;
        if (has_capability('moodle/course:update', $context)) {

            $this->mmcc_config = get_config('block_mmcc_retention');

            $this->content->items   = array();
            $this->content->icons   = array();
            $this->content->footer  = "";

            $user_name = $USER->username;
            $alerts = $this->get_retention_alerts($user_name, $this->mmcc_config->apitoken);

            if ("" !== $alerts["error"]) {
                $this->content->items[] = html_writer::tag('span', $alerts["error"]);
                $this->content->icons[] = $OUTPUT->pix_icon('i/invalid', 'invalid');
            }
            else {
                $count = 0;
                foreach($alerts["course_sections"] as $alert) {
                    $count++;
                    $text = $alert["short_name"] . ' ' . $alert["title"] . " (" . $alert["unread_count"] . ")";

                    $this->content->items[] = html_writer::tag('a', $text, array('href' => $alert["url"], "target" => "_blank"));
                    $img = 't/messages';
                    if (0 != $alert["unread_count"]) {
                        $img = "i/flagged";
                    }
                    $this->content->icons[] = $OUTPUT->pix_icon($img, 'message count');
                }

                if (0 == $count) {
                    $this->content->items[] = html_writer::tag('a', "Open the Referral Management System", array('href' => "https://rms.midmich.edu", "target" => "_blank"));
                    $this->content->icons[] = $OUTPUT->pix_icon('i/publish', 'RMS');
                }

                $this->content->footer = "Courses active in the RMS: " . $count;
            }
        }

        return $this->content;
    }

    function get_retention_alerts( $user_name = "", $api_token = "" ) {
        /*
         * We expect data of this form:
         * {"course_sections":
         *     [
         *         {
         *         "title":"Intro Website Design",
         *         "short_name":"ART.152.M02",
         *         "url":"https://rms.midmich.edu/instructor/course_sections/55134",
         *         "unread_count":0
         *         }
         *     ]
         * }
         *
         *
         */

        $curl_handle = NULL;
        $alerts = array();
        try {
            // Build headers
            $http_headers = array(
                "Content-Type: application/json",
                "X-API-KEY: " . $api_token,
            );
            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, str_replace("{{user_name}}", $user_name, REQUEST_URL));
            curl_setopt($curl_handle, CURLOPT_HEADER, true);                    // Include HTTP headers in response
            curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $http_headers);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_handle, CURLOPT_TIMEOUT_MS, CURL_TIMEOUT);
            curl_setopt($curl_handle, CURLOPT_FAILONERROR, false);              // Treat HTML errors (e.g. 404) as successfull calls
            curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "GET");            // Send via GET
            $response = curl_exec($curl_handle);
            if (false == $response) {
                // Call failed for some reason - possibly timed out
                $alerts["error"] = "Remote call failed (general failure)";
            }
            else {
                // Get just the response body
                // http://stackoverflow.com/questions/6259471/extracting-json-from-curl-results/6259477#6259477
                $body = substr($response, curl_getinfo($curl_handle, CURLINFO_HEADER_SIZE));

                // Parse JSON from $response body
                $alerts = json_decode($body, true);

                // Append other info
                $alerts["http_code"] = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
                $alerts["error"] = "";
            }
        }
        catch (Exception $e) {
            // Die silently
            $alerts = array();
            $alerts["error"] = "Remote call failed (cURL error): " . curl_error($curl_handle);
        }
        if (isset($curl_handle)) {
            curl_close($curl_handle);
            $curl_handle = NULL;
        }

        // Ensure these fields are set before returning
        if (!isset($alerts["error"])) {
            $alerts["error"] = "";
        }

        if (!isset($alerts["http_code"])) {
            $alerts["http_code"] = "100";
        }

        if (!isset($alerts["course_sections"])) {
            $alerts["course_sections"] = [];
        }

        return $alerts;
    }

    function specialisation() {
        //empty!
    }
}
