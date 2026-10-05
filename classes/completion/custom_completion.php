<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Custom completion rules.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\completion;

use core_completion\activity_custom_completion;
use mod_videodiscussion\discussion_manager;

/**
 * Custom completion implementation.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns the state for one rule.
     *
     * @param string $rule
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $activityid = $this->cm->instance;
        $activity = $DB->get_record(
            'videodiscussion',
            ['id' => $activityid],
            'id,completionpercent,completionwatch,completionmandatory',
            MUST_EXIST
        );

        if ($rule === 'completionwatch') {
            $required = !empty($activity->completionwatch) ? (int)$activity->completionpercent : 0;
            $percent = (float)$DB->get_field('videodiscussion_progress', 'percent', [
                'videodiscussionid' => $activityid,
                'userid' => $this->userid,
            ]);
            return $required > 0 && $percent >= $required ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        if ($rule === 'completionmandatory') {
            $groupmode = groups_get_activity_groupmode($this->cm);
            $groups = $groupmode == NOGROUPS
                ? []
                : (groups_get_all_groups($this->cm->course, $this->userid, $this->cm->groupingid, 'g.id') ?: []);
            $groupids = $groupmode == NOGROUPS ? [0] : array_map('intval', array_keys($groups));
            $stats = (new discussion_manager())->mandatory_stats($activityid, $this->userid, $groupids);
            return $stats['total'] === 0 || $stats['answered'] >= $stats['total']
                ? COMPLETION_COMPLETE
                : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Returns custom rules defined by this module.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionwatch', 'completionmandatory'];
    }

    /**
     * Returns the custom completion rules enabled for this activity instance.
     *
     * Normally Moodle obtains this from cm_info custom data. During a cache rebuild,
     * or immediately after completion settings change, that custom data may be absent.
     * In that case, fall back to the persisted activity settings.
     *
     * @return string[]
     */
    public function get_available_custom_rules(): array {
        $customdata = (array)$this->cm->get_custom_data();
        if (array_key_exists('customcompletionrules', $customdata)) {
            return parent::get_available_custom_rules();
        }

        if ((int)$this->cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
            return [];
        }

        global $DB;

        $activity = $DB->get_record(
            'videodiscussion',
            ['id' => $this->cm->instance],
            'id,completionpercent,completionwatch,completionmandatory',
            MUST_EXIST
        );

        $rules = [];
        if (!empty($activity->completionwatch) && (int)$activity->completionpercent > 0) {
            $rules[] = 'completionwatch';
        }
        if (!empty($activity->completionmandatory)) {
            $rules[] = 'completionmandatory';
        }

        return $rules;
    }

    /**
     * Returns rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $activity = $DB->get_record(
            'videodiscussion',
            ['id' => $this->cm->instance],
            'id,completionpercent,completionwatch',
            MUST_EXIST
        );
        $percent = !empty($activity->completionwatch) ? (int)$activity->completionpercent : 0;

        return [
            'completionwatch' => get_string('completiondetail:percent', 'videodiscussion', $percent),
            'completionmandatory' => get_string('completiondetail:mandatory', 'videodiscussion'),
        ];
    }

    /**
     * Returns completion-rule display order.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionwatch',
            'completionmandatory',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
