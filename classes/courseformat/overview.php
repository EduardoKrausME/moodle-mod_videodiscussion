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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Activity overview integration.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\courseformat;

use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\url;
use core_courseformat\local\overview\overviewitem;
use mod_videodiscussion\discussion_manager;

/**
 * Moodle 5 activity overview integration.
 */
class overview extends \core_courseformat\activityoverviewbase {
    /**
     * Returns activity-specific overview columns.
     *
     * @return array
     */
    public function get_extra_overview_items(): array {
        global $DB, $USER;

        if (!has_capability('mod/videodiscussion:participate', $this->context, $USER, false)) {
            return [];
        }

        $activity = $this->cm->get_instance_record();
        $progress = $DB->get_record('videodiscussion_progress', [
            'videodiscussionid' => $this->cm->instance,
            'userid' => $USER->id,
        ], 'percent');

        $groupmode = groups_get_activity_groupmode($this->cm);
        if ($groupmode == NOGROUPS) {
            $groupids = [0];
        } else {
            $groups = groups_get_all_groups($this->course->id, $USER->id, $this->cm->groupingid, 'g.id') ?: [];
            $groupids = array_map('intval', array_keys($groups));
        }

        $mandatory = (new discussion_manager())->mandatory_stats(
            $this->cm->instance,
            $USER->id,
            $groupids
        );

        return [
            'watched' => new overviewitem(
                name: get_string('watchedpercent', 'videodiscussion'),
                value: $progress ? (float)$progress->percent : 0,
                content: format_float($progress ? (float)$progress->percent : 0, 2) . '%',
                textalign: text_align::END,
            ),
            'mandatory' => new overviewitem(
                name: get_string('mandatoryanswered', 'videodiscussion'),
                value: $mandatory['answered'],
                content: $mandatory['answered'] . '/' . $mandatory['total'],
                textalign: text_align::END,
            ),
        ];
    }

    /**
     * Returns teacher actions.
     *
     * @return overviewitem|null
     */
    public function get_actions_overview(): ?overviewitem {
        if (!has_capability('mod/videodiscussion:viewreport', $this->context)) {
            return null;
        }

        $link = new action_link(
            url: new url('/mod/videodiscussion/report/report.php', ['id' => $this->cm->id]),
            text: get_string('report', 'videodiscussion'),
            attributes: ['class' => button::BODY_OUTLINE->classes()],
        );

        return new overviewitem(
            name: get_string('actions'),
            value: get_string('report', 'videodiscussion'),
            content: $link,
            textalign: text_align::CENTER,
        );
    }
}
