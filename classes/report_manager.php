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
 * Participation report data loader.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use context_module;
use moodle_url;
use stdClass;

/**
 * Loads report data in batches to avoid per-participant queries.
 */
class report_manager {
    /**
     * Returns participants visible in the selected group.
     *
     * @param context_module $context
     * @param stdClass $cm
     * @param int $currentgroup
     * @return array
     */
    public function get_participants(context_module $context, stdClass $cm, int $currentgroup): array {
        $groupmode = groups_get_activity_groupmode($cm);
        $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);

        if ($groupmode == SEPARATEGROUPS && !$canaccessallgroups && !$currentgroup) {
            return [];
        }

        return get_enrolled_users(
            $context,
            'mod/videodiscussion:participate',
            $currentgroup,
            'u.id,u.firstname,u.lastname'
        );
    }

    /**
     * Builds report rows using batched database queries.
     *
     * @param stdClass $course
     * @param stdClass $cm
     * @param stdClass $activity
     * @param array $users
     * @param int $currentgroup
     * @return array
     */
    public function get_rows(
        stdClass $course,
        stdClass $cm,
        stdClass $activity,
        array $users,
        int $currentgroup
    ): array {
        global $DB;

        if (!$users) {
            return [];
        }

        $userids = array_map('intval', array_keys($users));
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'reportuser');
        $baseparams = ['activityid' => $activity->id] + $userparams;

        $groupmode = groups_get_activity_groupmode($cm);
        [$threadgroupsql, $threadgroupparams] = $this->group_sql('t.groupid', $groupmode, $currentgroup, 'threadgroup');
        [$postgroupsql, $postgroupparams] = $this->group_sql('p.groupid', $groupmode, $currentgroup, 'postgroup');

        $comments = $DB->get_records_sql(
            "SELECT t.userid, COUNT(1) AS total
               FROM {videodiscussion_threads} t
              WHERE t.videodiscussionid = :activityid
                AND t.teacherprompt = 0
                AND t.userid {$usersql}
                    {$threadgroupsql}
           GROUP BY t.userid",
            $baseparams + $threadgroupparams
        );

        $replies = $DB->get_records_sql(
            "SELECT p.userid, COUNT(1) AS total
               FROM {videodiscussion_posts} p
               JOIN {videodiscussion_threads} t ON t.id = p.threadid
              WHERE t.videodiscussionid = :activityid
                AND p.userid {$usersql}
                    {$postgroupsql}
           GROUP BY p.userid",
            $baseparams + $postgroupparams
        );

        $progress = $DB->get_records_select(
            'videodiscussion_progress',
            "videodiscussionid = :activityid AND userid {$usersql}",
            $baseparams,
            '',
            'userid,percent'
        );
        $grades = $DB->get_records_select(
            'videodiscussion_grades',
            "videodiscussionid = :activityid AND userid {$usersql}",
            $baseparams,
            '',
            'userid,grade,feedback'
        );

        $mandatory = $this->mandatory_stats_for_users($course, $cm, $activity, $userids, $currentgroup);
        $rows = [];

        foreach ($users as $user) {
            $userid = (int)$user->id;
            $grade = $grades[$userid] ?? null;
            $stats = $mandatory[$userid] ?? ['answered' => 0, 'total' => 0];

            $rows[] = [
                'userid' => $userid,
                'name' => fullname($user),
                'profileurl' => (new moodle_url('/user/view.php', [
                    'id' => $userid,
                    'course' => $course->id,
                ]))->out(false),
                'comments' => isset($comments[$userid]) ? (int)$comments[$userid]->total : 0,
                'replies' => isset($replies[$userid]) ? (int)$replies[$userid]->total : 0,
                'mandatoryanswered' => $stats['answered'],
                'mandatorytotal' => $stats['total'],
                'mandatorytext' => $stats['answered'] . '/' . $stats['total'],
                'percent' => isset($progress[$userid]) ? round((float)$progress[$userid]->percent, 2) : 0,
                'grade' => $grade && $grade->grade !== null ? format_float((float)$grade->grade, 2) : '',
                'feedback' => $grade ? (string)$grade->feedback : '',
            ];
        }

        return $rows;
    }

    /**
     * Returns a user-id lookup for grade POST validation.
     *
     * @param array $users
     * @return array
     */
    public function participant_lookup(array $users): array {
        $lookup = [];
        foreach ($users as $user) {
            $lookup[(int)$user->id] = true;
        }
        return $lookup;
    }

    /**
     * Creates an SQL group condition.
     *
     * @param string $field
     * @param int $groupmode
     * @param int $currentgroup
     * @param string $paramname
     * @return array
     */
    private function group_sql(string $field, int $groupmode, int $currentgroup, string $paramname): array {
        if ($groupmode == NOGROUPS) {
            return [" AND {$field} = 0", []];
        }
        if ($currentgroup > 0) {
            return [" AND {$field} = :{$paramname}", [$paramname => $currentgroup]];
        }
        return ['', []];
    }

    /**
     * Calculates mandatory-prompt stats for all report participants in batches.
     *
     * @param stdClass $course
     * @param stdClass $cm
     * @param stdClass $activity
     * @param array $userids
     * @param int $currentgroup
     * @return array
     */
    private function mandatory_stats_for_users(
        stdClass $course,
        stdClass $cm,
        stdClass $activity,
        array $userids,
        int $currentgroup
    ): array {
        global $DB;

        $groupmode = groups_get_activity_groupmode($cm);
        $params = ['activityid' => $activity->id];
        $groupsql = '';

        if ($groupmode == NOGROUPS) {
            $groupsql = ' AND groupid = 0';
        } else if ($currentgroup > 0) {
            $groupsql = ' AND (groupid = 0 OR groupid = :currentgroup)';
            $params['currentgroup'] = $currentgroup;
        }

        $threads = $DB->get_records_select(
            'videodiscussion_threads',
            "videodiscussionid = :activityid AND mandatory = 1 AND teacherprompt = 1 {$groupsql}",
            $params,
            '',
            'id,groupid'
        );

        $stats = [];
        foreach ($userids as $userid) {
            $stats[$userid] = ['answered' => 0, 'total' => 0];
        }
        if (!$threads) {
            return $stats;
        }

        $memberships = $this->get_group_memberships($course, $cm, $userids);
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mandatoryuser');
        [$threadsql, $threadparams] = $DB->get_in_or_equal(array_keys($threads), SQL_PARAMS_NAMED, 'mandatorythread');

        $posts = $DB->get_records_sql(
            "SELECT p.id, p.userid, p.threadid, p.groupid
               FROM {videodiscussion_posts} p
              WHERE p.userid {$usersql}
                AND p.threadid {$threadsql}",
            $userparams + $threadparams
        );

        $answers = [];
        foreach ($posts as $post) {
            $answers[(int)$post->userid][(int)$post->threadid][(int)$post->groupid] = true;
        }

        foreach ($userids as $userid) {
            $usergroups = $memberships[$userid] ?? [];
            foreach ($threads as $thread) {
                $threadgroup = (int)$thread->groupid;

                if ($threadgroup > 0 && !in_array($threadgroup, $usergroups, true)) {
                    continue;
                }

                $stats[$userid]['total']++;
                if ($threadgroup > 0) {
                    if (!empty($answers[$userid][$thread->id][$threadgroup])) {
                        $stats[$userid]['answered']++;
                    }
                    continue;
                }

                $candidategroups = $groupmode == NOGROUPS
                    ? [0]
                    : ($currentgroup > 0 ? [$currentgroup] : ($usergroups ?: [0]));

                foreach ($candidategroups as $groupid) {
                    if (!empty($answers[$userid][$thread->id][$groupid])) {
                        $stats[$userid]['answered']++;
                        break;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Returns group memberships for report participants with a single query.
     *
     * @param stdClass $course
     * @param stdClass $cm
     * @param array $userids
     * @return array
     */
    private function get_group_memberships(stdClass $course, stdClass $cm, array $userids): array {
        global $DB;

        $result = [];
        foreach ($userids as $userid) {
            $result[$userid] = [];
        }

        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            return $result;
        }

        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'memberuser');
        $params['courseid'] = $course->id;

        $groupingjoin = '';
        $groupingwhere = '';
        if (!empty($cm->groupingid)) {
            $groupingjoin = ' JOIN {groupings_groups} gg ON gg.groupid = g.id';
            $groupingwhere = ' AND gg.groupingid = :groupingid';
            $params['groupingid'] = $cm->groupingid;
        }

        $members = $DB->get_recordset_sql(
            "SELECT gm.id, gm.userid, gm.groupid
               FROM {groups_members} gm
               JOIN {groups} g ON g.id = gm.groupid
                    {$groupingjoin}
              WHERE gm.userid {$usersql}
                AND g.courseid = :courseid
                    {$groupingwhere}",
            $params
        );

        foreach ($members as $member) {
            $result[(int)$member->userid][] = (int)$member->groupid;
        }
        $members->close();

        return $result;
    }
}
