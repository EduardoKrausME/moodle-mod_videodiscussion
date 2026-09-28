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
 * Custom completion rules.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        if ($rule === 'completionwatch') {
            $required = (int)($this->cm->customdata['customcompletionrules']['completionwatch'] ?? 0);
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
     * Returns rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $percent = (int)($this->cm->customdata['customcompletionrules']['completionwatch'] ?? 0);

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
