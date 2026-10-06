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
 * Deletes or anonymises a discussion thread.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;
use mod_videodiscussion\event\thread_deleted;
use mod_videodiscussion\event\thread_updated;

require('../../../config.php');

$id = required_param('id', PARAM_INT);
$threadid = required_param('threadid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$thread = $DB->get_record('videodiscussion_threads', [
    'id' => $threadid,
    'videodiscussionid' => $activity->id,
], '*', MUST_EXIST);

$manager = new discussion_manager();
if (!$manager->can_delete_thread($thread, $activity, $context, $USER->id)) {
    throw new required_capability_exception($context, 'mod/videodiscussion:deleteowndiscussion', 'nopermissions', '');
}

$ismanaging = has_capability('mod/videodiscussion:managediscussions', $context);
$isowner = (int)$thread->userid === $USER->id;

if (!empty($thread->teacherprompt) || ($ismanaging && !$isowner)) {
    if (!$manager->can_manage_group($cm, $context, $USER->id, (int)$thread->groupid)) {
        throw new moodle_exception('errorgroupaccess', 'videodiscussion');
    }
} else if (!$manager->can_post_group($cm, $context, $USER->id, (int)$thread->groupid)) {
    throw new moodle_exception('errorgroupaccess', 'videodiscussion');
}

$actualdelete = !empty($thread->teacherprompt) || ($ismanaging && !$isowner)
    || !$DB->record_exists('videodiscussion_posts', ['threadid' => $thread->id]);

if ($actualdelete) {
    $event = thread_deleted::create([
        'objectid' => $thread->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('videodiscussion_threads', $thread);

    $DB->delete_records('videodiscussion_posts', ['threadid' => $thread->id]);
    $DB->delete_records('videodiscussion_threads', ['id' => $thread->id]);
    $event->trigger();
} else {
    $thread->userid = 0;
    $thread->subject = get_string('deleteddiscussion', 'videodiscussion');
    $thread->message = '';
    $thread->messageformat = FORMAT_PLAIN;
    $thread->timemodified = time();
    $DB->update_record('videodiscussion_threads', $thread);

    $event = thread_updated::create([
        'objectid' => $thread->id,
        'context' => $context,
    ]);
    $event->trigger();
}

if (!empty($activity->completionmandatory)) {
    (new completion_info($course))->reset_all_state($cm);
}

$redirecturl = $ismanaging
    ? new moodle_url('/mod/videodiscussion/manage.php', ['id' => $cm->id])
    : new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id]);

redirect($redirecturl, get_string('discussiondeleted', 'videodiscussion'));
