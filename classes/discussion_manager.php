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
 * Discussion query and access helper.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use context_module;

/**
 * Class discussion_manager.
 */
class discussion_manager {
    /**
     * Returns the currently selected activity group.
     *
     * @param \stdClass $cm
     * @return int
     */
    public function current_group(\stdClass $cm): int {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == NOGROUPS) {
            return 0;
        }
        return (int)groups_get_activity_group($cm, true);
    }

    /**
     * Checks whether a user can view the selected group.
     *
     * @param \stdClass $cm
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @return bool
     */
    public function can_view_group(\stdClass $cm, context_module $context, int $userid, int $groupid): bool {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == NOGROUPS || $groupid === 0) {
            return true;
        }
        if (has_capability('moodle/site:accessallgroups', $context, $userid)) {
            return true;
        }
        if ($groupmode == VISIBLEGROUPS) {
            return true;
        }
        return groups_is_member($groupid, $userid);
    }

    /**
     * Checks whether a user can post in a selected group.
     *
     * @param \stdClass $cm
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @return bool
     */
    public function can_post_group(\stdClass $cm, context_module $context, int $userid, int $groupid): bool {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == NOGROUPS) {
            return $groupid === 0;
        }
        if (has_capability('moodle/site:accessallgroups', $context, $userid)) {
            return true;
        }
        return $groupid > 0 && groups_is_member($groupid, $userid);
    }

    /**
     * Checks whether a user can manage content in a group.
     *
     * @param \stdClass $cm
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @return bool
     */
    public function can_manage_group(\stdClass $cm, context_module $context, int $userid, int $groupid): bool {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == NOGROUPS) {
            return $groupid === 0;
        }
        if (has_capability('moodle/site:accessallgroups', $context, $userid)) {
            return true;
        }
        return $groupid > 0 && groups_is_member($groupid, $userid);
    }

    /**
     * Returns activity threads formatted for templates.
     *
     * @param \stdClass $activity
     * @param \stdClass $cm
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @return array
     */
    public function get_threads(\stdClass $activity, \stdClass $cm, context_module $context, int $userid, int $groupid): array {
        global $DB;

        $params = ['activityid' => $activity->id];
        $groupsql = ' AND (t.groupid = 0';
        if ($groupid > 0) {
            $groupsql .= ' OR t.groupid = :groupid';
            $params['groupid'] = $groupid;
        }
        $groupsql .= ')';

        $sql = "SELECT t.*, u.firstname, u.lastname
                  FROM {videodiscussion_threads} t
             LEFT JOIN {user} u ON u.id = t.userid
                 WHERE t.videodiscussionid = :activityid {$groupsql}
              ORDER BY t.timepoint ASC, t.timecreated ASC";
        $threads = $DB->get_records_sql($sql, $params);
        $canmanage = has_capability('mod/videodiscussion:managediscussions', $context);
        $canhighlight = has_capability('mod/videodiscussion:highlight', $context);
        $result = [];

        foreach ($threads as $thread) {
            if (!$this->can_view_group($cm, $context, $userid, (int)$thread->groupid)) {
                continue;
            }

            $postgroup = (int)$thread->groupid > 0 ? (int)$thread->groupid : $groupid;
            $canpost = $this->can_post_group($cm, $context, $userid, $postgroup);
            $canreply = $canpost && has_capability('mod/videodiscussion:reply', $context);
            $ownresponse = $DB->record_exists('videodiscussion_posts', [
                'threadid' => $thread->id,
                'userid' => $userid,
                'groupid' => $postgroup,
            ]);
            $reveal = $canmanage || empty($thread->requireownpost) || $ownresponse || !$canpost;
            $posts = $this->get_posts(
                $activity,
                $cm,
                $thread,
                $context,
                $userid,
                $postgroup,
                $reveal,
                $canhighlight,
                $canreply
            );

            $teacherprompt = !empty($thread->teacherprompt);
            $groupmanageable = $this->can_manage_group($cm, $context, $userid, (int)$thread->groupid);
            $canedit = $teacherprompt
                ? ($canmanage && $groupmanageable)
                : $this->can_edit_thread($thread, $activity, $context, $userid);
            $candelete = $teacherprompt
                ? ($canmanage && $groupmanageable)
                : $this->can_delete_thread($thread, $activity, $context, $userid);

            $author = $teacherprompt
                ? get_string('teacherprompt', 'videodiscussion')
                : ((int)$thread->userid === 0 ? get_string('deleteduser', 'videodiscussion') : fullname($thread));

            $editurl = '';
            if ($canedit) {
                $path = $teacherprompt
                    ? '/mod/videodiscussion/discussion/edit.php'
                    : '/mod/videodiscussion/discussion/student_edit.php';
                $editurl = (new \moodle_url($path, [
                    'id' => $cm->id,
                    'threadid' => $thread->id,
                ]))->out(false);
            }

            $deleteurl = '';
            if ($candelete) {
                $deleteurl = (new \moodle_url('/mod/videodiscussion/discussion/thread_delete.php', [
                    'id' => $cm->id,
                    'threadid' => $thread->id,
                    'sesskey' => sesskey(),
                ]))->out(false);
            }

            $result[] = [
                'id' => (int)$thread->id,
                'timepoint' => (float)$thread->timepoint,
                'timecode' => timecode::format((float)$thread->timepoint),
                'subject' => format_string($thread->subject),
                'message' => format_text($thread->message, (int)$thread->messageformat, ['context' => $context]),
                'author' => $author,
                'teacherprompt' => $teacherprompt,
                'mandatory' => !empty($thread->mandatory),
                'requireownpost' => !empty($thread->requireownpost),
                'revealblocked' => !$reveal,
                'ownresponse' => $ownresponse,
                'posts' => $posts,
                'hasposts' => !empty($posts),
                'canreply' => $canreply,
                'canedit' => $canedit,
                'candelete' => $candelete,
                'editurl' => $editurl,
                'deleteurl' => $deleteurl,
            ];
        }

        return $result;
    }

    /**
     * Returns posts for one thread.
     *
     * @param \stdClass $activity
     * @param \stdClass $cm
     * @param \stdClass $thread
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @param bool $reveal
     * @param bool $canhighlight
     * @param bool $canreply
     * @return array
     */
    private function get_posts(
        \stdClass $activity,
        \stdClass $cm,
        \stdClass $thread,
        context_module $context,
        int $userid,
        int $groupid,
        bool $reveal,
        bool $canhighlight,
        bool $canreply
    ): array {
        global $DB;

        $params = ['threadid' => $thread->id, 'userid' => $userid];
        if ($groupid > 0) {
            $params['groupid'] = $groupid;
            $groupsql = ' AND (p.groupid = 0 OR p.groupid = :groupid)';
        } else {
            $groupsql = ' AND p.groupid = 0';
        }

        $visibility = $reveal ? '' : ' AND p.userid = :userid';
        $sql = "SELECT p.*, u.firstname, u.lastname
                  FROM {videodiscussion_posts} p
             LEFT JOIN {user} u ON u.id = p.userid
                 WHERE p.threadid = :threadid {$groupsql} {$visibility}
              ORDER BY p.timecreated ASC";
        $records = $DB->get_records_sql($sql, $params);

        $posts = [];
        foreach ($records as $post) {
            $postgroup = (int)$post->groupid;
            $groupmanageable = $this->can_manage_group($cm, $context, $userid, $postgroup);
            $mayhighlight = $canhighlight && $groupmanageable;
            $canedit = $this->can_edit_post($post, $activity, $context, $userid);
            $candelete = $this->can_delete_post($post, $activity, $context, $userid) && $groupmanageable;

            $posts[] = [
                'id' => (int)$post->id,
                'threadid' => (int)$thread->id,
                'groupid' => (int)$post->groupid,
                'parentid' => (int)$post->parentid,
                'isreply' => (int)$post->parentid > 0,
                'author' => (int)$post->userid === 0 ? get_string('deleteduser', 'videodiscussion') : fullname($post),
                'message' => format_text($post->message, (int)$post->messageformat, ['context' => $context]),
                'highlighted' => !empty($post->highlighted),
                'canhighlight' => $mayhighlight,
                'canreply' => $canreply,
                'canedit' => $canedit,
                'candelete' => $candelete,
                'highlighturl' => $mayhighlight ? (new \moodle_url('/mod/videodiscussion/highlight.php', [
                    'id' => $cm->id,
                    'postid' => $post->id,
                    'sesskey' => sesskey(),
                ]))->out(false) : '',
                'editurl' => $canedit ? (new \moodle_url('/mod/videodiscussion/discussion/post_edit.php', [
                    'id' => $cm->id,
                    'postid' => $post->id,
                ]))->out(false) : '',
                'deleteurl' => $candelete ? (new \moodle_url('/mod/videodiscussion/discussion/post_delete.php', [
                    'id' => $cm->id,
                    'postid' => $post->id,
                    'sesskey' => sesskey(),
                ]))->out(false) : '',
                'time' => userdate((int)$post->timecreated),
            ];
        }

        return $posts;
    }

    /**
     * Checks that the selected thread is visible to a user who wants to post.
     *
     * @param \stdClass $thread
     * @param \stdClass $cm
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @return bool
     */
    public function can_access_thread(
        \stdClass $thread,
        \stdClass $cm,
        context_module $context,
        int $userid,
        int $groupid
    ): bool {
        if ((int)$thread->groupid === 0) {
            return $this->can_post_group($cm, $context, $userid, $groupid);
        }
        return (int)$thread->groupid === $groupid && $this->can_post_group($cm, $context, $userid, $groupid);
    }

    /**
     * Checks whether the user may edit their own post.
     *
     * @param \stdClass $post
     * @param \stdClass $activity
     * @param context_module $context
     * @param int $userid
     * @return bool
     */
    public function can_edit_post(
        \stdClass $post,
        \stdClass $activity,
        context_module $context,
        int $userid
    ): bool {
        return (int)$post->userid === $userid
            && has_capability('mod/videodiscussion:editownpost', $context, $userid)
            && $this->within_edit_window((int)$post->timecreated, (int)$activity->posteditwindow);
    }

    /**
     * Checks whether the user may delete a post.
     *
     * @param \stdClass $post
     * @param \stdClass $activity
     * @param context_module $context
     * @param int $userid
     * @return bool
     */
    public function can_delete_post(
        \stdClass $post,
        \stdClass $activity,
        context_module $context,
        int $userid
    ): bool {
        if (has_capability('mod/videodiscussion:deleteanypost', $context, $userid)) {
            return true;
        }

        return (int)$post->userid === $userid
            && has_capability('mod/videodiscussion:deleteownpost', $context, $userid)
            && $this->within_edit_window((int)$post->timecreated, (int)$activity->posteditwindow);
    }

    /**
     * Checks whether the user may edit their own student-created discussion.
     *
     * @param \stdClass $thread
     * @param \stdClass $activity
     * @param context_module $context
     * @param int $userid
     * @return bool
     */
    public function can_edit_thread(
        \stdClass $thread,
        \stdClass $activity,
        context_module $context,
        int $userid
    ): bool {
        if (!empty($thread->teacherprompt)) {
            return false;
        }

        return (int)$thread->userid === $userid
            && has_capability('mod/videodiscussion:editowndiscussion', $context, $userid)
            && $this->within_edit_window((int)$thread->timecreated, (int)$activity->posteditwindow);
    }

    /**
     * Checks whether the user may delete a discussion.
     *
     * @param \stdClass $thread
     * @param \stdClass $activity
     * @param context_module $context
     * @param int $userid
     * @return bool
     */
    public function can_delete_thread(
        \stdClass $thread,
        \stdClass $activity,
        context_module $context,
        int $userid
    ): bool {
        if (has_capability('mod/videodiscussion:managediscussions', $context, $userid)) {
            return true;
        }
        if (!empty($thread->teacherprompt)) {
            return false;
        }

        return (int)$thread->userid === $userid
            && has_capability('mod/videodiscussion:deleteowndiscussion', $context, $userid)
            && $this->within_edit_window((int)$thread->timecreated, (int)$activity->posteditwindow);
    }

    /**
     * Counts required prompts and answers for completion checks.
     *
     * @param int $activityid
     * @param int $userid
     * @param array $groupids
     * @return array
     */
    public function mandatory_stats(int $activityid, int $userid, array $groupids): array {
        global $DB;

        $groupids = array_values(array_unique(array_map('intval', $groupids)));
        $threads = $DB->get_records('videodiscussion_threads', [
            'videodiscussionid' => $activityid,
            'mandatory' => 1,
            'teacherprompt' => 1,
        ]);

        $total = 0;
        $answered = 0;
        foreach ($threads as $thread) {
            $threadgroup = (int)$thread->groupid;
            if ($threadgroup > 0 && !in_array($threadgroup, $groupids, true)) {
                continue;
            }

            $total++;
            $postgroups = $threadgroup > 0 ? [$threadgroup] : ($groupids ?: [0]);
            foreach ($postgroups as $postgroup) {
                if ($DB->record_exists('videodiscussion_posts', [
                    'threadid' => $thread->id,
                    'userid' => $userid,
                    'groupid' => $postgroup,
                ])) {
                    $answered++;
                    break;
                }
            }
        }

        return ['answered' => $answered, 'total' => $total];
    }

    /**
     * Checks whether an own-content edit/delete window is still open.
     *
     * @param int $timecreated
     * @param int $window
     * @return bool
     */
    private function within_edit_window(int $timecreated, int $window): bool {
        return $window === 0 || time() <= ($timecreated + $window);
    }
}
