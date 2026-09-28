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
 * Privacy provider.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Class provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns privacy metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videodiscussion_threads', [
            'userid' => 'privacy:metadata:videodiscussion_threads:userid',
            'subject' => 'privacy:metadata:videodiscussion_threads:subject',
            'message' => 'privacy:metadata:videodiscussion_threads:message',
            'timepoint' => 'privacy:metadata:videodiscussion_threads:timepoint',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:videodiscussion_threads');

        $collection->add_database_table('videodiscussion_posts', [
            'userid' => 'privacy:metadata:videodiscussion_posts:userid',
            'message' => 'privacy:metadata:videodiscussion_posts:message',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:videodiscussion_posts');

        $collection->add_database_table('videodiscussion_progress', [
            'userid' => 'privacy:metadata:videodiscussion_progress:userid',
            'duration' => 'privacy:metadata:videodiscussion_progress:duration',
            'lastposition' => 'privacy:metadata:videodiscussion_progress:lastposition',
            'uniquewatched' => 'privacy:metadata:videodiscussion_progress:uniquewatched',
            'percent' => 'privacy:metadata:videodiscussion_progress:percent',
            'watchedsegments' => 'privacy:metadata:videodiscussion_progress:watchedsegments',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:videodiscussion_progress');

        $collection->add_database_table('videodiscussion_grades', [
            'userid' => 'privacy:metadata:videodiscussion_grades:userid',
            'grade' => 'privacy:metadata:videodiscussion_grades:grade',
            'feedback' => 'privacy:metadata:videodiscussion_grades:feedback',
            'grader' => 'privacy:metadata:videodiscussion_grades:grader',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:videodiscussion_grades');

        return $collection;
    }

    /**
     * Returns contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videodiscussion} vd ON vd.id = cm.instance
                 WHERE EXISTS (
                           SELECT 1 FROM {videodiscussion_threads} t
                            WHERE t.videodiscussionid = vd.id AND t.userid = :threaduser
                       )
                    OR EXISTS (
                           SELECT 1
                             FROM {videodiscussion_posts} p
                             JOIN {videodiscussion_threads} pt ON pt.id = p.threadid
                            WHERE pt.videodiscussionid = vd.id AND p.userid = :postuser
                       )
                    OR EXISTS (
                           SELECT 1 FROM {videodiscussion_progress} pr
                            WHERE pr.videodiscussionid = vd.id AND pr.userid = :progressuser
                       )
                    OR EXISTS (
                           SELECT 1 FROM {videodiscussion_grades} g
                            WHERE g.videodiscussionid = vd.id
                              AND (g.userid = :gradeuser OR g.grader = :grader)
                       )";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videodiscussion',
            'threaduser' => $userid,
            'postuser' => $userid,
            'progressuser' => $userid,
            'gradeuser' => $userid,
            'grader' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Adds users whose data is stored in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videodiscussion', $context->instanceid);
        if (!$cm) {
            return;
        }

        $params = ['activityid' => $cm->instance];
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {videodiscussion_threads} WHERE videodiscussionid = :activityid AND userid <> 0',
            $params
        );
        $userlist->add_from_sql(
            'p.userid',
            "SELECT p.userid
               FROM {videodiscussion_posts} p
               JOIN {videodiscussion_threads} t ON t.id = p.threadid
              WHERE t.videodiscussionid = :activityid AND p.userid <> 0",
            $params
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {videodiscussion_progress} WHERE videodiscussionid = :activityid',
            $params
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {videodiscussion_grades} WHERE videodiscussionid = :activityid',
            $params
        );
        $userlist->add_from_sql(
            'grader',
            'SELECT grader FROM {videodiscussion_grades} WHERE videodiscussionid = :activityid AND grader <> 0',
            $params
        );
    }

    /**
     * Exports user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('videodiscussion', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $activity = $DB->get_record('videodiscussion', ['id' => $cm->instance]);
            if (!$activity) {
                continue;
            }

            $base = helper::get_context_data($context, $userid);
            writer::with_context($context)->export_data([], $base);

            $threads = $DB->get_records('videodiscussion_threads', [
                'videodiscussionid' => $activity->id,
                'userid' => $userid,
            ]);
            $posts = $DB->get_records_sql(
                "SELECT p.*
                   FROM {videodiscussion_posts} p
                   JOIN {videodiscussion_threads} t ON t.id = p.threadid
                  WHERE t.videodiscussionid = :activityid AND p.userid = :userid",
                ['activityid' => $activity->id, 'userid' => $userid]
            );
            $progress = $DB->get_record('videodiscussion_progress', [
                'videodiscussionid' => $activity->id,
                'userid' => $userid,
            ]);
            $grades = $DB->get_records('videodiscussion_grades', [
                'videodiscussionid' => $activity->id,
                'userid' => $userid,
            ]);
            $gradesgiven = $DB->get_records('videodiscussion_grades', [
                'videodiscussionid' => $activity->id,
                'grader' => $userid,
            ]);

            writer::with_context($context)->export_data(['discussions'], (object)[
                'threads' => array_values($threads),
                'posts' => array_values($posts),
            ]);

            if ($progress) {
                writer::with_context($context)->export_data(['progress'], (object)[
                    'duration' => $progress->duration,
                    'lastposition' => $progress->lastposition,
                    'uniquewatched' => $progress->uniquewatched,
                    'percent' => $progress->percent,
                    'watchedsegments' => $progress->watchedsegments,
                    'timecreated' => transform::datetime($progress->timecreated),
                    'timemodified' => transform::datetime($progress->timemodified),
                ]);
            }

            writer::with_context($context)->export_data(['grades'], (object)[
                'grades' => array_values($grades),
            ]);
            writer::with_context($context)->export_data(['grades_given'], (object)[
                'grades' => array_values($gradesgiven),
            ]);
        }
    }

    /**
     * Deletes all user data while preserving teacher prompts as activity configuration.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videodiscussion', $context->instanceid);
        if (!$cm) {
            return;
        }

        $threadids = $DB->get_fieldset_select(
            'videodiscussion_threads',
            'id',
            'videodiscussionid = ?',
            [$cm->instance]
        );
        if ($threadids) {
            [$insql, $params] = $DB->get_in_or_equal($threadids, SQL_PARAMS_NAMED, 'privacythread');
            $DB->delete_records_select('videodiscussion_posts', "threadid {$insql}", $params);
        }

        $DB->delete_records_select(
            'videodiscussion_threads',
            'videodiscussionid = ? AND teacherprompt = 0',
            [$cm->instance]
        );
        $DB->set_field_select(
            'videodiscussion_threads',
            'userid',
            0,
            'videodiscussionid = ? AND teacherprompt = 1',
            [$cm->instance]
        );
        $DB->delete_records('videodiscussion_progress', ['videodiscussionid' => $cm->instance]);
        $DB->delete_records('videodiscussion_grades', ['videodiscussionid' => $cm->instance]);
    }

    /**
     * Deletes data for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_from_context($context, $contextlist->get_user()->id);
        }
    }

    /**
     * Deletes data for multiple users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_from_context($userlist->get_context(), (int)$userid);
        }
    }

    /**
     * Deletes or anonymises one user's data without removing other users' contributions.
     *
     * @param \context $context
     * @param int $userid
     * @return void
     */
    private static function delete_user_from_context(\context $context, int $userid): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videodiscussion', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('videodiscussion_progress', [
            'videodiscussionid' => $cm->instance,
            'userid' => $userid,
        ]);
        $DB->delete_records('videodiscussion_grades', [
            'videodiscussionid' => $cm->instance,
            'userid' => $userid,
        ]);
        $DB->set_field('videodiscussion_grades', 'grader', 0, [
            'videodiscussionid' => $cm->instance,
            'grader' => $userid,
        ]);

        $DB->delete_records_select(
            'videodiscussion_posts',
            'userid = ? AND threadid IN (SELECT id FROM {videodiscussion_threads} WHERE videodiscussionid = ?)',
            [$userid, $cm->instance]
        );

        $threads = $DB->get_records('videodiscussion_threads', [
            'videodiscussionid' => $cm->instance,
            'userid' => $userid,
        ], '', 'id,teacherprompt');

        foreach ($threads as $thread) {
            if (!empty($thread->teacherprompt)) {
                $DB->set_field('videodiscussion_threads', 'userid', 0, ['id' => $thread->id]);
                continue;
            }

            if ($DB->record_exists('videodiscussion_posts', ['threadid' => $thread->id])) {
                $DB->set_field('videodiscussion_threads', 'userid', 0, ['id' => $thread->id]);
                $DB->set_field('videodiscussion_threads', 'subject', '', ['id' => $thread->id]);
                $DB->set_field('videodiscussion_threads', 'message', '', ['id' => $thread->id]);
            } else {
                $DB->delete_records('videodiscussion_threads', ['id' => $thread->id]);
            }
        }
    }
}
