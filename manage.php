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

require('../../config.php');

$id = required_param('id', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videodiscussion:managediscussions', $context);

$PAGE->set_url('/mod/videodiscussion/manage.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managediscussions', 'videodiscussion'));
$PAGE->set_heading(format_string($course->fullname));

if ($delete) {
    require_sesskey();
    $thread = $DB->get_record('videodiscussion_threads', ['id' => $delete, 'videodiscussionid' => $activity->id], '*', MUST_EXIST);
    $DB->delete_records('videodiscussion_posts', ['threadid' => $thread->id]);
    $DB->delete_records('videodiscussion_threads', ['id' => $thread->id]);
    redirect($PAGE->url, get_string('discussiondeleted', 'videodiscussion'));
}

$sql = "SELECT t.*, u.firstname, u.lastname
          FROM {videodiscussion_threads} t
     LEFT JOIN {user} u ON u.id = t.userid
         WHERE t.videodiscussionid = :activityid
      ORDER BY t.timepoint ASC, t.timecreated ASC";
$records = $DB->get_records_sql($sql, ['activityid' => $activity->id]);
$threads = [];
foreach ($records as $thread) {
    $threads[] = [
        'id' => $thread->id,
        'timecode' => \mod_videodiscussion\timecode::format((float)$thread->timepoint),
        'subject' => format_string($thread->subject),
        'author' => $thread->teacherprompt ? get_string('teacherprompt', 'videodiscussion') : fullname($thread),
        'mandatory' => !empty($thread->mandatory),
        'requireownpost' => !empty($thread->requireownpost),
        'editurl' => (new moodle_url('/mod/videodiscussion/discussion/edit.php',
            ['id' => $cm->id, 'threadid' => $thread->id]))->out(false),
        'deleteurl' => (new moodle_url('/mod/videodiscussion/manage.php',
            ['id' => $cm->id, 'delete' => $thread->id, 'sesskey' => sesskey()]))->out(false),
    ];
}
$data = [
    'threads' => $threads,
    'hasthreads' => !empty($threads),
    'addurl' => (new moodle_url('/mod/videodiscussion/discussion/edit.php', ['id' => $cm->id]))->out(false),
    'viewurl' => (new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videodiscussion/manage', $data);
echo $OUTPUT->footer();
