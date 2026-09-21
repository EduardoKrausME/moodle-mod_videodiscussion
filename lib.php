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
 * Library functions for Video Discussion.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares Moodle features supported by the activity.
 *
 * @param string $feature
 * @return mixed
 */
function videodiscussion_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Adds an activity instance.
 *
 * @param stdClass $data
 * @param mod_videodiscussion_mod_form|null $mform
 * @return int
 */
function videodiscussion_add_instance($data, $mform = null) {
    global $DB;

    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $data->id = $DB->insert_record('videodiscussion', $data);

    $context = context_module::instance($data->coursemodule);
    if (!empty($data->videofile)) {
        file_save_draft_area_files(
            $data->videofile,
            $context->id,
            'mod_videodiscussion',
            'video',
            0,
            ['subdirs' => false, 'maxfiles' => 1]
        );
    }

    videodiscussion_grade_item_update($data);
    return $data->id;
}

/**
 * Updates an activity instance.
 *
 * @param stdClass $data
 * @param mod_videodiscussion_mod_form|null $mform
 * @return bool
 */
function videodiscussion_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('videodiscussion', $data);

    $context = context_module::instance($data->coursemodule);
    if (isset($data->videofile)) {
        file_save_draft_area_files(
            $data->videofile,
            $context->id,
            'mod_videodiscussion',
            'video',
            0,
            ['subdirs' => false, 'maxfiles' => 1]
        );
    }

    videodiscussion_grade_item_update($data);
    videodiscussion_update_grades($data);
    return true;
}

/**
 * Deletes an activity instance.
 *
 * @param int $id
 * @return bool
 */
function videodiscussion_delete_instance($id) {
    global $DB;

    $activity = $DB->get_record('videodiscussion', ['id' => $id]);
    if (!$activity) {
        return false;
    }

    $cm = get_coursemodule_from_instance('videodiscussion', $id);
    $context = $cm ? context_module::instance($cm->id) : null;

    $threadids = $DB->get_fieldset_select('videodiscussion_threads', 'id', 'videodiscussionid = ?', [$id]);
    if ($threadids) {
        [$insql, $params] = $DB->get_in_or_equal($threadids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('videodiscussion_posts', "threadid {$insql}", $params);
    }
    $DB->delete_records('videodiscussion_threads', ['videodiscussionid' => $id]);
    $DB->delete_records('videodiscussion_progress', ['videodiscussionid' => $id]);
    $DB->delete_records('videodiscussion_grades', ['videodiscussionid' => $id]);
    $DB->delete_records('videodiscussion', ['id' => $id]);

    if ($context) {
        get_file_storage()->delete_area_files($context->id, 'mod_videodiscussion');
    }

    videodiscussion_grade_item_delete($activity);
    return true;
}

/**
 * Serves uploaded videos.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool|null
 */
function videodiscussion_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'video') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videodiscussion:view', $context);

    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_videodiscussion', 'video', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, false, $options);
}

/**
 * Updates or creates the gradebook item.
 *
 * @param stdClass $activity
 * @param mixed $grades
 * @return int
 */
function videodiscussion_grade_item_update($activity, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $activity->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademin' => 0,
        'grademax' => max(0, (float)$activity->grade),
    ];
    if ((float)$activity->grade <= 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update(
        'mod/videodiscussion',
        $activity->course,
        'mod',
        'videodiscussion',
        $activity->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the gradebook item.
 *
 * @param stdClass $activity
 * @return int
 */
function videodiscussion_grade_item_delete($activity) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update(
        'mod/videodiscussion',
        $activity->course,
        'mod',
        'videodiscussion',
        $activity->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Sends participation grades to the gradebook.
 *
 * @param stdClass $activity
 * @param int $userid
 * @param bool $nullifnone
 * @return void
 */
function videodiscussion_update_grades($activity, $userid = 0, $nullifnone = true) {
    global $DB;

    if ((float)$activity->grade <= 0) {
        videodiscussion_grade_item_update($activity);
        return;
    }

    $params = ['videodiscussionid' => $activity->id];
    if ($userid) {
        $params['userid'] = $userid;
    }
    $records = $DB->get_records('videodiscussion_grades', $params);
    $grades = [];
    foreach ($records as $record) {
        $grades[$record->userid] = (object)[
            'userid' => $record->userid,
            'rawgrade' => $record->grade,
            'feedback' => $record->feedback,
            'feedbackformat' => FORMAT_PLAIN,
        ];
    }
    if ($userid && !$grades && $nullifnone) {
        $grades[$userid] = (object)['userid' => $userid, 'rawgrade' => null];
    }
    videodiscussion_grade_item_update($activity, $grades ?: null);
}
