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
 * AJAX endpoint for watched progress.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;

/**
 * Class save_progress.
 */
class save_progress extends external_api {

    /**
     * Method execute_parameters.
     *
     * @return external_function_parameters Return value.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'duration' => new external_value(PARAM_FLOAT, 'Video duration'),
            'lastposition' => new external_value(PARAM_FLOAT, 'Latest playback position'),
            'segmentsjson' => new external_value(PARAM_RAW, 'JSON array of watched intervals'),
        ]);
    }

    /**
     * Saves progress.
     *
     * @param int $cmid
     * @param float $duration
     * @param float $lastposition
     * @param string $segmentsjson
     * @return array
     */
    public static function execute(int $cmid, float $duration, float $lastposition, string $segmentsjson): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'duration' => $duration,
            'lastposition' => $lastposition,
            'segmentsjson' => $segmentsjson,
        ]);
        $cm = get_coursemodule_from_id('videodiscussion', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videodiscussion:view', $context);
        $activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
        $segments = json_decode($params['segmentsjson'], true);
        if (!is_array($segments)) {
            $segments = [];
        }
        $record = (new \mod_videodiscussion\progress_manager())->save(
            $activity->id,
            $USER->id,
            (float)$params['duration'],
            (float)$params['lastposition'],
            $segments
        );
        return ['percent' => (float)$record->percent, 'lastposition' => (float)$record->lastposition];
    }

    /**
     * Method execute_returns.
     *
     * @return external_single_structure Return value.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_FLOAT, 'Unique watched percentage'),
            'lastposition' => new external_value(PARAM_FLOAT, 'Stored last position'),
        ]);
    }
}
