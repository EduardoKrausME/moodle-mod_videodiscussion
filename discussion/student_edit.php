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

/**
 * Edits a student-created discussion.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$threadid = required_param('threadid', PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$thread = $DB->get_record('videodiscussion_threads', [
    'id' => $threadid,
    'videodiscussionid' => $activity->id,
    'teacherprompt' => 0,
], '*', MUST_EXIST);

$manager = new discussion_manager();
if (!$manager->can_edit_thread($thread, $activity, $context, $USER->id)
        || !$manager->can_post_group($cm, $context, $USER->id, (int)$thread->groupid)) {
    throw new required_capability_exception($context, 'mod/videodiscussion:editowndiscussion', 'nopermissions', '');
}

$PAGE->set_url('/mod/videodiscussion/discussion/student_edit.php', [
    'id' => $cm->id,
    'threadid' => $thread->id,
]);
$PAGE->set_title(get_string('editstudentdiscussion', 'videodiscussion'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \mod_videodiscussion\form\student_thread_form(null, [
    'cmid' => $cm->id,
    'threadid' => $thread->id,
]);
$form->set_data((object)[
    'id' => $cm->id,
    'threadid' => $thread->id,
    'timepointtext' => \mod_videodiscussion\timecode::format((float)$thread->timepoint),
    'subject' => $thread->subject,
    'message' => $thread->message,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id], 'thread-' . $thread->id));
}

if ($data = $form->get_data()) {
    $thread->timepoint = \mod_videodiscussion\timecode::parse((string)$data->timepointtext) ?? 0;
    $thread->subject = trim($data->subject);
    $thread->message = trim($data->message);
    $thread->messageformat = FORMAT_PLAIN;
    $thread->timemodified = time();
    $DB->update_record('videodiscussion_threads', $thread);

    $event = \mod_videodiscussion\event\thread_updated::create([
        'objectid' => $thread->id,
        'context' => $context,
    ]);
    $event->trigger();

    redirect(
        new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id], 'thread-' . $thread->id),
        get_string('discussionsaved', 'videodiscussion')
    );
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
