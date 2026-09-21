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
 * Handles student-created discussions and responses.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$threadid = optional_param('threadid', 0, PARAM_INT);
$parentid = optional_param('parentid', 0, PARAM_INT);
$message = required_param('message', PARAM_RAW);
$subject = optional_param('subject', '', PARAM_TEXT);
$timepoint = optional_param('timepoint', 0, PARAM_FLOAT);
$groupid = optional_param('groupid', 0, PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$manager = new discussion_manager();
$currentgroup = $manager->current_group($cm);
if ($groupid !== $currentgroup && !has_capability('moodle/site:accessallgroups', $context)) {
    throw new moodle_exception('errorgroupaccess', 'videodiscussion');
}
if (!$manager->can_post_group($cm, $context, $USER->id, $groupid)) {
    throw new moodle_exception('errorgroupaccess', 'videodiscussion');
}

if ($threadid) {
    require_capability('mod/videodiscussion:reply', $context);
    $thread = $DB->get_record('videodiscussion_threads',
        ['id' => $threadid, 'videodiscussionid' => $activity->id], '*', MUST_EXIST);
    $postgroup = (int)$thread->groupid > 0 ? (int)$thread->groupid : $groupid;
    if (!$manager->can_access_thread($thread, $cm, $context, $USER->id, $postgroup)) {
        throw new moodle_exception('errorthreadaccess', 'videodiscussion');
    }
    if ($parentid && !$DB->record_exists('videodiscussion_posts', ['id' => $parentid, 'threadid' => $threadid])) {
        $parentid = 0;
    }
    $now = time();
    $post = (object)[
        'threadid' => $threadid,
        'parentid' => $parentid,
        'userid' => $USER->id,
        'groupid' => $postgroup,
        'message' => trim($message),
        'messageformat' => FORMAT_PLAIN,
        'highlighted' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    if ($post->message === '') {
        throw new moodle_exception('errorposting', 'videodiscussion');
    }
    $postid = $DB->insert_record('videodiscussion_posts', $post);
    $event = \mod_videodiscussion\event\discussion_posted::create([
        'objectid' => $postid,
        'context' => $context,
        'other' => ['threadid' => $threadid],
    ]);
    $event->trigger();
    redirect(new moodle_url('/mod/videodiscussion/view.php',
        ['id' => $cm->id], 'thread-' . $threadid), get_string('replyposted', 'videodiscussion'));
}

require_capability('mod/videodiscussion:adddiscussion', $context);
if (empty($activity->allowstudentthreads) && !has_capability('mod/videodiscussion:managediscussions', $context)) {
    throw new required_capability_exception($context, 'mod/videodiscussion:managediscussions', 'nopermissions', '');
}
if (trim($subject) === '' || trim($message) === '') {
    throw new moodle_exception('errorposting', 'videodiscussion');
}
$now = time();
$thread = (object)[
    'videodiscussionid' => $activity->id,
    'userid' => $USER->id,
    'groupid' => $groupid,
    'timepoint' => max(0, $timepoint),
    'subject' => $subject,
    'message' => trim($message),
    'messageformat' => FORMAT_PLAIN,
    'mandatory' => 0,
    'requireownpost' => 0,
    'teacherprompt' => 0,
    'timecreated' => $now,
    'timemodified' => $now,
];
$threadid = $DB->insert_record('videodiscussion_threads', $thread);
redirect(new moodle_url('/mod/videodiscussion/view.php',
    ['id' => $cm->id], 'thread-' . $threadid), get_string('discussioncreated', 'videodiscussion'));
