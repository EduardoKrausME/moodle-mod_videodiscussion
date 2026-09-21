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
 * Main activity view.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videodiscussion\discussion_manager;
use mod_videodiscussion\player;
use mod_videodiscussion\timecode;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videodiscussion', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videodiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videodiscussion:view', $context);

$PAGE->set_url('/mod/videodiscussion/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}
$event = \mod_videodiscussion\event\course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videodiscussion', $activity);
$event->trigger();

$manager = new discussion_manager();
$groupid = $manager->current_group($cm);
$threads = $manager->get_threads($activity, $cm, $context, $USER->id, $groupid);
$progress = $DB->get_record('videodiscussion_progress', [
    'videodiscussionid' => $activity->id,
    'userid' => $USER->id,
]);
if (!$progress) {
    $progress = (object)[
        'duration' => 0,
        'lastposition' => 0,
        'percent' => 0,
        'watchedsegments' => '[]',
    ];
}

$playerconfig = player::config($activity, $context);
$clientconfig = [
    'cmid' => $cm->id,
    'lastposition' => (float)$progress->lastposition,
    'segments' => json_decode((string)$progress->watchedsegments, true) ?: [],
    'source' => $playerconfig,
];
$markers = [];
foreach ($threads as $thread) {
    $markers[] = [
        'id' => $thread['id'],
        'timepoint' => $thread['timepoint'],
        'timecode' => $thread['timecode'],
        'subject' => $thread['subject'],
        'mandatory' => $thread['mandatory'],
    ];
}

$templatedata = [
    'name' => format_string($activity->name),
    'intro' => format_module_intro('videodiscussion', $activity, $cm->id),
    'hasintro' => trim((string)$activity->intro) !== '',
    'player' => $playerconfig,
    'threads' => $threads,
    'hasthreads' => !empty($threads),
    'markers' => $markers,
    'allowstudentthreads' => !empty($activity->allowstudentthreads)
        && has_capability('mod/videodiscussion:adddiscussion', $context)
        && $manager->can_post_group($cm, $context, $USER->id, $groupid),
    'canmanage' => has_capability('mod/videodiscussion:managediscussions', $context),
    'canreport' => has_capability('mod/videodiscussion:viewreport', $context),
    'manageurl' => (new moodle_url('/mod/videodiscussion/manage.php', ['id' => $cm->id]))->out(false),
    'reporturl' => (new moodle_url('/mod/videodiscussion/report/report.php', ['id' => $cm->id]))->out(false),
    'posturl' => (new moodle_url('/mod/videodiscussion/post.php'))->out(false),
    'cmid' => $cm->id,
    'groupid' => $groupid,
    'sesskey' => sesskey(),
    'configjson' => json_encode($clientconfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    'progress' => [
        'percent' => round((float)$progress->percent, 2),
        'percentrounded' => (int)round((float)$progress->percent),
        'lastposition' => timecode::format((float)$progress->lastposition),
        'target' => (int)$activity->completionpercent,
        'targettext' => get_string('watchtarget', 'videodiscussion', (int)$activity->completionpercent),
        'resumetext' => get_string('resumefrom', 'videodiscussion', timecode::format((float)$progress->lastposition)),
    ],
];

$PAGE->requires->js_call_amd('mod_videodiscussion/main', 'init');

echo $OUTPUT->header();
if (groups_get_activity_groupmode($cm) != NOGROUPS) {
    groups_print_activity_menu($cm, $PAGE->url);
}
echo $OUTPUT->render_from_template('mod_videodiscussion/view', $templatedata);
echo $OUTPUT->footer();
