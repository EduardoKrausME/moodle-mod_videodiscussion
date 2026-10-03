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
 * Participation report and grading.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\report_manager;

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videodiscussion:viewreport', $context);

$PAGE->set_url('/mod/videodiscussion/report/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'videodiscussion'));
$PAGE->set_heading(format_string($course->fullname));

$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
$reportmanager = new report_manager();
$users = $reportmanager->get_participants($context, $cm, (int)$currentgroup);
$participantids = $reportmanager->participant_lookup($users);

if (data_submitted() && confirm_sesskey() && has_capability('mod/videodiscussion:grade', $context)) {
    $grades = optional_param_array('grades', [], PARAM_RAW_TRIMMED);
    $feedbacks = optional_param_array('feedbacks', [], PARAM_TEXT);
    $now = time();

    foreach ($participantids as $userid => $unused) {
        $rawgrade = $grades[$userid] ?? '';
        $feedback = trim((string)($feedbacks[$userid] ?? ''));

        if ($rawgrade === '') {
            $grade = null;
        } else if (!is_numeric($rawgrade)) {
            continue;
        } else {
            $grade = max(0, min((float)$activity->grade, (float)$rawgrade));
        }

        $existing = $DB->get_record('videodiscussion_grades', [
            'videodiscussionid' => $activity->id,
            'userid' => $userid,
        ]);

        if ($existing) {
            $existing->grade = $grade;
            $existing->feedback = $feedback;
            $existing->grader = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('videodiscussion_grades', $existing);
            $gradeid = $existing->id;
        } else {
            $gradeid = $DB->insert_record('videodiscussion_grades', (object)[
                'videodiscussionid' => $activity->id,
                'userid' => $userid,
                'grader' => $USER->id,
                'grade' => $grade,
                'feedback' => $feedback,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        videodiscussion_update_grades($activity, $userid, true);

        $event = \mod_videodiscussion\event\grade_updated::create([
            'objectid' => $gradeid,
            'context' => $context,
            'relateduserid' => $userid,
        ]);
        $event->trigger();
    }

    redirect($PAGE->url, get_string('gradessaved', 'videodiscussion'));
}

$rows = $reportmanager->get_rows($course, $cm, $activity, $users, (int)$currentgroup);
$groupmenu = $groupmode ? groups_print_activity_menu($cm, $PAGE->url, true) : '';

$data = [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'hasgroupmenu' => !empty($groupmenu),
    'groupmenu' => $groupmenu,
    'canGrade' => has_capability('mod/videodiscussion:grade', $context) && (float)$activity->grade > 0,
    'maxgrade' => format_float((float)$activity->grade, 2),
    'actionurl' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'viewurl' => (new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videodiscussion/report', $data);
echo $OUTPUT->footer();
