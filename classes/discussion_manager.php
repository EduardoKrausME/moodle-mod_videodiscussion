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
 * Discussion query and access helper.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Checks whether a user can use the selected group.
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
            $ownresponse = $DB->record_exists('videodiscussion_posts', [
                'threadid' => $thread->id,
                'userid' => $userid,
                'groupid' => $postgroup,
            ]);
            $reveal = $canmanage || empty($thread->requireownpost) || $ownresponse || !$canpost;
            $posts = $this->get_posts($thread, $context, $userid, $postgroup, $reveal, $canhighlight);
            $author = $thread->teacherprompt ? get_string('teacherprompt', 'videodiscussion') : fullname($thread);
            $result[] = [
                'id' => (int)$thread->id,
                'timepoint' => (float)$thread->timepoint,
                'timecode' => timecode::format((float)$thread->timepoint),
                'subject' => format_string($thread->subject),
                'message' => format_text($thread->message, (int)$thread->messageformat, ['context' => $context]),
                'author' => $author,
                'teacherprompt' => !empty($thread->teacherprompt),
                'mandatory' => !empty($thread->mandatory),
                'requireownpost' => !empty($thread->requireownpost),
                'revealblocked' => !$reveal,
                'ownresponse' => $ownresponse,
                'posts' => $posts,
                'hasposts' => !empty($posts),
                'canreply' => $canpost && has_capability('mod/videodiscussion:reply', $context),
                'canmanage' => $canmanage,
                'editurl' => (new \moodle_url('/mod/videodiscussion/discussion/edit.php',
                    ['id' => $cm->id, 'threadid' => $thread->id]))->out(false),
                'deleteurl' => (new \moodle_url('/mod/videodiscussion/manage.php', [
                    'id' => $cm->id,
                    'delete' => $thread->id,
                    'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }
        return $result;
    }

    /**
     * Returns posts for one thread.
     *
     * @param \stdClass $thread
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @param bool $reveal
     * @param bool $canhighlight
     * @return array
     */
    private function get_posts(\stdClass $thread, context_module $context, int $userid,
                               int $groupid, bool $reveal, bool $canhighlight): array {
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
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.threadid = :threadid {$groupsql} {$visibility}
              ORDER BY p.timecreated ASC";
        $records = $DB->get_records_sql($sql, $params);
        $posts = [];
        foreach ($records as $post) {
            $posts[] = [
                'id' => (int)$post->id,
                'parentid' => (int)$post->parentid,
                'isreply' => (int)$post->parentid > 0,
                'author' => fullname($post),
                'message' => format_text($post->message, (int)$post->messageformat, ['context' => $context]),
                'highlighted' => !empty($post->highlighted),
                'canhighlight' => $canhighlight,
                'highlighturl' => (new \moodle_url('/mod/videodiscussion/highlight.php', [
                    'id' => $context->instanceid,
                    'postid' => $post->id,
                    'sesskey' => sesskey(),
                ]))->out(false),
                'time' => userdate((int)$post->timecreated),
            ];
        }
        return $posts;
    }

    /**
     * Checks that the selected thread is visible to a user.
     *
     * @param \stdClass $thread
     * @param \stdClass $cm
     * @param context_module $context
     * @param int $userid
     * @param int $groupid
     * @return bool
     */
    public function can_access_thread(\stdClass $thread, \stdClass $cm, context_module $context, int $userid, int $groupid): bool {
        if ((int)$thread->groupid === 0) {
            return $this->can_post_group($cm, $context, $userid, $groupid);
        }
        return (int)$thread->groupid === $groupid && $this->can_post_group($cm, $context, $userid, $groupid);
    }

    /**
     * Counts required prompts and answers for reporting.
     *
     * @param int $activityid
     * @param int $userid
     * @param array $groupids
     * @return array{answered:int,total:int}
     */
    public function mandatory_stats(int $activityid, int $userid, array $groupids): array {
        global $DB;
        $threads = $DB->get_records('videodiscussion_threads', [
            'videodiscussionid' => $activityid,
            'mandatory' => 1,
        ]);
        $total = 0;
        $answered = 0;
        foreach ($threads as $thread) {
            if ((int)$thread->groupid > 0 && !in_array((int)$thread->groupid, $groupids, true)) {
                continue;
            }
            $total++;
            $postgroups = (int)$thread->groupid > 0 ? [(int)$thread->groupid] : ($groupids ?: [0]);
            foreach ($postgroups as $groupid) {
                if ($DB->record_exists('videodiscussion_posts', [
                    'threadid' => $thread->id,
                    'userid' => $userid,
                    'groupid' => $groupid,
                ])) {
                    $answered++;
                    break;
                }
            }
        }
        return ['answered' => $answered, 'total' => $total];
    }
}
