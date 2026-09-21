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
 * Restore structure.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class restore_videodiscussion_activity_structure_step.
 */
class restore_videodiscussion_activity_structure_step extends restore_activity_structure_step {
    /** @return restore_path_element[] */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('videodiscussion', '/activity/videodiscussion');
        $paths[] = new restore_path_element('videodiscussion_thread', '/activity/videodiscussion/threads/thread');
        $paths[] = new restore_path_element('videodiscussion_post', '/activity/videodiscussion/threads/thread/posts/post');
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videodiscussion_progress', '/activity/videodiscussion/progresses/progress');
            $paths[] = new restore_path_element('videodiscussion_grade', '/activity/videodiscussion/grades/grade');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * process_videodiscussion
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videodiscussion($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videodiscussion', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('videodiscussion', $oldid, $newid, true);
    }

    /**
     * process_videodiscussion_thread
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videodiscussion_thread($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videodiscussionid = $this->get_new_parentid('videodiscussion');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->groupid = $this->get_mappingid('group', $data->groupid, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videodiscussion_threads', $data);
        $this->set_mapping('videodiscussion_thread', $oldid, $newid, true);
    }

    /**
     * process_videodiscussion_post
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videodiscussion_post($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->threadid = $this->get_new_parentid('videodiscussion_thread');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->groupid = $this->get_mappingid('group', $data->groupid, 0);
        if (!empty($data->parentid)) {
            $data->parentid = $this->get_mappingid('videodiscussion_post', $data->parentid, 0);
        }
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videodiscussion_posts', $data);
        $this->set_mapping('videodiscussion_post', $oldid, $newid, true);
    }

    /**
     * process_videodiscussion_progress
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videodiscussion_progress($data) {
        global $DB;
        $data = (object)$data;
        $data->videodiscussionid = $this->get_new_parentid('videodiscussion');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if ($data->userid) {
            $DB->insert_record('videodiscussion_progress', $data);
        }
    }

    /**
     * process_videodiscussion_grade
     *
     * @param $data
     * @return void
     * @throws dml_exception
     */
    protected function process_videodiscussion_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->videodiscussionid = $this->get_new_parentid('videodiscussion');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->grader = $this->get_mappingid('user', $data->grader, 0);
        if ($data->userid) {
            $DB->insert_record('videodiscussion_grades', $data);
        }
    }

    /**
     * Method after_execute.
     *
     * @return mixed Return value.
     */
    protected function after_execute() {
        $this->add_related_files('mod_videodiscussion', 'intro', null);
        $this->add_related_files('mod_videodiscussion', 'video', null);
    }
}
