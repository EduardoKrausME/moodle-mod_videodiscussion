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
 * Edits a user's response.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;
use mod_videodiscussion\event\post_updated;
use mod_videodiscussion\form\post_form;

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$post = $DB->get_record_sql(
    "SELECT p.*
       FROM {videodiscussion_posts} p
       JOIN {videodiscussion_threads} t ON t.id = p.threadid
      WHERE p.id = :postid
        AND t.videodiscussionid = :activityid",
    ['postid' => $postid, 'activityid' => $activity->id],
    MUST_EXIST
);
$thread = $DB->get_record('videodiscussion_threads', ['id' => $post->threadid], '*', MUST_EXIST);

$manager = new discussion_manager();
if (!$manager->can_edit_post($post, $activity, $context, $USER->id)
        || !$manager->can_post_group($cm, $context, $USER->id, (int)$post->groupid)
        || !$manager->can_access_thread($thread, $cm, $context, $USER->id, (int)$post->groupid)) {
    throw new required_capability_exception($context, 'mod/videodiscussion:editownpost', 'nopermissions', '');
}

$PAGE->set_url('/mod/videodiscussion/discussion/post_edit.php', ['id' => $cm->id, 'postid' => $post->id]);
$PAGE->set_title(get_string('editresponse', 'videodiscussion'));
$PAGE->set_heading(format_string($course->fullname));

$form = new post_form(null, [
    'cmid' => $cm->id,
    'postid' => $post->id,
]);
$form->set_data((object)[
    'id' => $cm->id,
    'postid' => $post->id,
    'message' => $post->message,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id], 'thread-' . $thread->id));
}

if ($data = $form->get_data()) {
    $post->message = trim($data->message);
    $post->messageformat = FORMAT_PLAIN;
    $post->timemodified = time();
    $DB->update_record('videodiscussion_posts', $post);

    $event = post_updated::create([
        'objectid' => $post->id,
        'context' => $context,
        'other' => ['threadid' => $thread->id],
    ]);
    $event->trigger();

    redirect(
        new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id], 'thread-' . $thread->id),
        get_string('responsesaved', 'videodiscussion')
    );
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
