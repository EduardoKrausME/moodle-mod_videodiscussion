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
 * Toggles the highlighted state of a response.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videodiscussion:highlight', $context);

$post = $DB->get_record_sql(
    "SELECT p.*
       FROM {videodiscussion_posts} p
       JOIN {videodiscussion_threads} t ON t.id = p.threadid
      WHERE p.id = :postid AND t.videodiscussionid = :activityid",
    ['postid' => $postid, 'activityid' => $cm->instance],
    MUST_EXIST
);
$post->highlighted = empty($post->highlighted) ? 1 : 0;
$post->timemodified = time();
$DB->update_record('videodiscussion_posts', $post);
redirect(new moodle_url('/mod/videodiscussion/view.php', ['id' => $cm->id], 'thread-' . $post->threadid));
