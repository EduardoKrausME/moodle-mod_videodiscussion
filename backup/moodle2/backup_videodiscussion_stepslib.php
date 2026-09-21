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
 * Backup structure.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class backup_videodiscussion_activity_structure_step.
 */
class backup_videodiscussion_activity_structure_step extends backup_activity_structure_step {

    /**
     * Method define_structure.
     *
     * @return mixed Return value.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('videodiscussion', ['id'], [
            'name', 'intro', 'introformat', 'videosource', 'videourl', 'allowstudentthreads',
            'defaultrevealafterpost', 'completionpercent', 'grade', 'timecreated', 'timemodified',
        ]);
        $threads = new backup_nested_element('threads');
        $thread = new backup_nested_element('thread', ['id'], [
            'userid', 'groupid', 'timepoint', 'subject', 'message', 'messageformat', 'mandatory',
            'requireownpost', 'teacherprompt', 'timecreated', 'timemodified',
        ]);
        $posts = new backup_nested_element('posts');
        $post = new backup_nested_element('post', ['id'], [
            'parentid', 'userid', 'groupid', 'message', 'messageformat', 'highlighted', 'timecreated', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'duration', 'lastposition', 'uniquewatched', 'percent', 'watchedsegments', 'timecreated', 'timemodified',
        ]);
        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], [
            'userid', 'grader', 'grade', 'feedback', 'timecreated', 'timemodified',
        ]);

        $activity->add_child($threads);
        $threads->add_child($thread);
        $thread->add_child($posts);
        $posts->add_child($post);
        $activity->add_child($progresses);
        $progresses->add_child($progress);
        $activity->add_child($grades);
        $grades->add_child($grade);

        $activity->set_source_table('videodiscussion', ['id' => backup::VAR_ACTIVITYID]);
        $thread->set_source_table('videodiscussion_threads', ['videodiscussionid' => backup::VAR_PARENTID]);
        $post->set_source_table('videodiscussion_posts', ['threadid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $progress->set_source_table('videodiscussion_progress', ['videodiscussionid' => backup::VAR_PARENTID]);
            $grade->set_source_table('videodiscussion_grades', ['videodiscussionid' => backup::VAR_PARENTID]);
        }

        $thread->annotate_ids('user', 'userid');
        $thread->annotate_ids('group', 'groupid');
        $post->annotate_ids('user', 'userid');
        $post->annotate_ids('group', 'groupid');
        $progress->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'grader');
        $activity->annotate_files('mod_videodiscussion', 'intro', null);
        $activity->annotate_files('mod_videodiscussion', 'video', null);
        return $this->prepare_activity_structure($activity);
    }
}
