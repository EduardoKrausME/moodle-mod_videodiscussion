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
 * Participation report and grading.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;

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
$canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);

if ($groupmode == SEPARATEGROUPS && !$canaccessallgroups && !$currentgroup) {
    $users = [];
} else {
    $users = get_enrolled_users(
        $context,
        'mod/videodiscussion:view',
        (int)$currentgroup,
        'u.id,u.firstname,u.lastname,u.email'
    );
}

$participantids = [];
foreach ($users as $participant) {
    $participantids[(int)$participant->id] = true;
}

if (data_submitted() && confirm_sesskey() && has_capability('mod/videodiscussion:grade', $context)) {
    $grades = optional_param_array('grades', [], PARAM_RAW_TRIMMED);
    $now = time();
    foreach ($grades as $userid => $rawgrade) {
        $userid = (int)$userid;
        if (!$userid || !isset($participantids[$userid])) {
            continue;
        }
        if ($rawgrade === '') {
            $grade = null;
        } else {
            $grade = max(0, min((float)$activity->grade, (float)$rawgrade));
        }
        $existing = $DB->get_record('videodiscussion_grades', [
            'videodiscussionid' => $activity->id,
            'userid' => $userid,
        ]);
        if ($existing) {
            $existing->grade = $grade;
            $existing->grader = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('videodiscussion_grades', $existing);
        } else {
            $DB->insert_record('videodiscussion_grades', (object)[
                'videodiscussionid' => $activity->id,
                'userid' => $userid,
                'grader' => $USER->id,
                'grade' => $grade,
                'feedback' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        videodiscussion_update_grades($activity, $userid, true);
    }
    redirect($PAGE->url, get_string('gradessaved', 'videodiscussion'));
}

$manager = new discussion_manager();
$rows = [];
foreach ($users as $user) {
    $usergroups = groups_get_all_groups($course->id, $user->id, $cm->groupingid, 'g.id');
    $groupids = $usergroups ? array_map('intval', array_keys($usergroups)) : [];
    $comments = $DB->count_records('videodiscussion_threads', [
        'videodiscussionid' => $activity->id,
        'userid' => $user->id,
        'teacherprompt' => 0,
    ]);
    $replies = $DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {videodiscussion_posts} p
           JOIN {videodiscussion_threads} t ON t.id = p.threadid
          WHERE t.videodiscussionid = :activityid AND p.userid = :userid",
        ['activityid' => $activity->id, 'userid' => $user->id]
    );
    $mandatory = $manager->mandatory_stats($activity->id, $user->id, $groupids);
    $progress = $DB->get_record('videodiscussion_progress', [
        'videodiscussionid' => $activity->id,
        'userid' => $user->id,
    ]);
    $grade = $DB->get_record('videodiscussion_grades', [
        'videodiscussionid' => $activity->id,
        'userid' => $user->id,
    ]);
    $rows[] = [
        'userid' => $user->id,
        'name' => fullname($user),
        'profileurl' => (new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]))->out(false),
        'comments' => $comments,
        'replies' => $replies,
        'mandatoryanswered' => $mandatory['answered'],
        'mandatorytotal' => $mandatory['total'],
        'mandatorytext' => $mandatory['answered'] . '/' . $mandatory['total'],
        'percent' => $progress ? round((float)$progress->percent, 2) : 0,
        'grade' => $grade && $grade->grade !== null ? format_float((float)$grade->grade, 2) : '',
    ];
}
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
