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
 * Adds or edits teacher discussion prompts.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$threadid = optional_param('threadid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videodiscussion:managediscussions', $context);

$PAGE->set_url('/mod/videodiscussion/discussion/edit.php', ['id' => $cm->id, 'threadid' => $threadid]);
$PAGE->set_title($threadid ?
    get_string('editteacherprompt', 'videodiscussion') :
    get_string('addteacherprompt', 'videodiscussion'));
$PAGE->set_heading(format_string($course->fullname));

$groups = groups_get_all_groups($course->id, 0, $cm->groupingid, 'g.id,g.name');
$form = new \mod_videodiscussion\form\thread_form(null, [
    'cmid' => $cm->id,
    'threadid' => $threadid,
    'groups' => $groups ?: [],
]);
$thread = null;
if ($threadid) {
    $thread = $DB->get_record('videodiscussion_threads',
        ['id' => $threadid, 'videodiscussionid' => $activity->id], '*', MUST_EXIST);
    $form->set_data((object)[
        'id' => $cm->id,
        'threadid' => $thread->id,
        'timepointtext' => \mod_videodiscussion\timecode::format((float)$thread->timepoint),
        'subject' => $thread->subject,
        'message_editor' => ['text' => $thread->message, 'format' => $thread->messageformat],
        'mandatory' => $thread->mandatory,
        'requireownpost' => $thread->requireownpost,
        'groupid' => $thread->groupid,
    ]);
}
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videodiscussion/manage.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $timepoint = \mod_videodiscussion\timecode::parse((string)$data->timepointtext);
    $now = time();
    $record = (object)[
        'videodiscussionid' => $activity->id,
        'userid' => $USER->id,
        'groupid' => (int)$data->groupid,
        'timepoint' => $timepoint ?? 0,
        'subject' => $data->subject,
        'message' => $data->message_editor['text'],
        'messageformat' => $data->message_editor['format'],
        'mandatory' => empty($data->mandatory) ? 0 : 1,
        'requireownpost' => empty($data->requireownpost) ? 0 : 1,
        'teacherprompt' => 1,
        'timemodified' => $now,
    ];
    if ($thread) {
        $record->id = $thread->id;
        $record->timecreated = $thread->timecreated;
        $DB->update_record('videodiscussion_threads', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('videodiscussion_threads', $record);
    }
    redirect(new moodle_url('/mod/videodiscussion/manage.php',
        ['id' => $cm->id]), get_string('discussionsaved', 'videodiscussion'));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
