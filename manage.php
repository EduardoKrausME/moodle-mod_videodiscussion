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
 * Teacher discussion prompt management.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videodiscussion:managediscussions', $context);

$PAGE->set_url('/mod/videodiscussion/manage.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managediscussions', 'videodiscussion'));
$PAGE->set_heading(format_string($course->fullname));

$manager = new discussion_manager();
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
$canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);

$params = ['activityid' => $activity->id];
$groupsql = '';
if ($groupmode == NOGROUPS) {
    $groupsql = ' AND t.groupid = 0';
} else if ($currentgroup > 0) {
    $groupsql = ' AND (t.groupid = 0 OR t.groupid = :currentgroup)';
    $params['currentgroup'] = $currentgroup;
} else if (!$canaccessallgroups) {
    $groupsql = ' AND 1 = 0';
}

$sql = "SELECT t.*, u.firstname, u.lastname
          FROM {videodiscussion_threads} t
     LEFT JOIN {user} u ON u.id = t.userid
         WHERE t.videodiscussionid = :activityid {$groupsql}
      ORDER BY t.teacherprompt DESC, t.timepoint ASC, t.timecreated ASC";
$records = $DB->get_records_sql($sql, $params);

$teacherthreads = [];
$studentthreads = [];
foreach ($records as $thread) {
    $manageable = $manager->can_manage_group($cm, $context, $USER->id, (int)$thread->groupid);
    $row = [
        'id' => $thread->id,
        'timecode' => \mod_videodiscussion\timecode::format((float)$thread->timepoint),
        'subject' => format_string($thread->subject),
        'author' => (int)$thread->userid === 0 ? get_string('deleteduser', 'videodiscussion') : fullname($thread),
        'group' => (int)$thread->groupid > 0 ? format_string(groups_get_group_name($thread->groupid)) :
            get_string('allgroups', 'videodiscussion'),
        'mandatory' => !empty($thread->mandatory),
        'requireownpost' => !empty($thread->requireownpost),
        'canedit' => !empty($thread->teacherprompt) && $manageable,
        'candelete' => $manageable,
        'editurl' => $manageable ? (new moodle_url('/mod/videodiscussion/discussion/edit.php', [
            'id' => $cm->id,
            'threadid' => $thread->id,
        ]))->out(false) : '',
        'deleteurl' => $manageable ? (new moodle_url('/mod/videodiscussion/discussion/thread_delete.php', [
            'id' => $cm->id,
            'threadid' => $thread->id,
            'sesskey' => sesskey(),
        ]))->out(false) : '',
    ];

    if (!empty($thread->teacherprompt)) {
        $teacherthreads[] = $row;
    } else {
        $studentthreads[] = $row;
    }
}

$data = [
    'teacherthreads' => $teacherthreads,
    'hasteacherthreads' => !empty($teacherthreads),
    'studentthreads' => $studentthreads,
    'hasstudentthreads' => !empty($studentthreads),
    'addurl' => (new moodle_url('/mod/videodiscussion/discussion/edit.php', ['id' => $cm->id]))->out(false),
    'viewurl' => (new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id]))->out(false),
];

$PAGE->requires->js_call_amd('mod_videodiscussion/main', 'initConfirmations');

echo $OUTPUT->header();
if ($groupmode != NOGROUPS) {
    groups_print_activity_menu($cm, $PAGE->url);
}
echo $OUTPUT->render_from_template('mod_videodiscussion/manage', $data);
echo $OUTPUT->footer();
