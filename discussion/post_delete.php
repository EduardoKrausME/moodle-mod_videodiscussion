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
 * Deletes a response.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
require_sesskey();

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
if (!$manager->can_delete_post($post, $activity, $context, $USER->id)) {
    throw new required_capability_exception($context, 'mod/videodiscussion:deleteownpost', 'nopermissions', '');
}

if ((int)$post->userid === $USER->id) {
    if (!$manager->can_post_group($cm, $context, $USER->id, (int)$post->groupid)) {
        throw new moodle_exception('errorgroupaccess', 'videodiscussion');
    }
} else if (!$manager->can_manage_group($cm, $context, $USER->id, (int)$post->groupid)) {
    throw new moodle_exception('errorgroupaccess', 'videodiscussion');
}

$event = \mod_videodiscussion\event\post_deleted::create([
    'objectid' => $post->id,
    'context' => $context,
    'other' => ['threadid' => $thread->id],
]);
$event->add_record_snapshot('videodiscussion_posts', $post);

$DB->set_field(
    'videodiscussion_posts',
    'parentid',
    (int)$post->parentid,
    ['parentid' => $post->id]
);
$DB->delete_records('videodiscussion_posts', ['id' => $post->id]);
$event->trigger();

if (!empty($activity->completionmandatory)) {
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$post->userid);
    }
}

redirect(
    new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id], 'thread-' . $thread->id),
    get_string('responsedeleted', 'videodiscussion')
);
