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
 * Participation report tests.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use advanced_testcase;

/**
 * Tests group-scoped participation aggregation.
 *
 * @covers \mod_videodiscussion\report_manager
 */
final class report_manager_test extends advanced_testcase {
    /**
     * A selected group must not include participation from another group.
     *
     * @return void
     */
    public function test_rows_are_scoped_to_selected_group(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $groupa = groups_create_group((object)['courseid' => $course->id, 'name' => 'Group A']);
        $groupb = groups_create_group((object)['courseid' => $course->id, 'name' => 'Group B']);
        groups_add_member($groupa, $user);
        groups_add_member($groupb, $user);

        $activity = $this->getDataGenerator()->create_module('videodiscussion', [
            'course' => $course->id,
            'groupmode' => SEPARATEGROUPS,
        ]);
        $cm = get_coursemodule_from_instance('videodiscussion', $activity->id, $course->id, false, MUST_EXIST);
        $now = time();

        $threada = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $user->id,
            'groupid' => $groupa,
            'timepoint' => 10,
            'subject' => 'A',
            'message' => 'A',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 0,
            'requireownpost' => 0,
            'teacherprompt' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $threadb = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $user->id,
            'groupid' => $groupb,
            'timepoint' => 20,
            'subject' => 'B',
            'message' => 'B',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 0,
            'requireownpost' => 0,
            'teacherprompt' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $prompta = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $teacher->id,
            'groupid' => $groupa,
            'timepoint' => 30,
            'subject' => 'Required A',
            'message' => 'Required A',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 1,
            'requireownpost' => 0,
            'teacherprompt' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $teacher->id,
            'groupid' => $groupb,
            'timepoint' => 40,
            'subject' => 'Required B',
            'message' => 'Required B',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 1,
            'requireownpost' => 0,
            'teacherprompt' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        foreach ([[$threada, $groupa], [$threadb, $groupb], [$prompta, $groupa]] as [$threadid, $groupid]) {
            $DB->insert_record('videodiscussion_posts', (object)[
                'threadid' => $threadid,
                'parentid' => 0,
                'userid' => $user->id,
                'groupid' => $groupid,
                'message' => 'Reply',
                'messageformat' => FORMAT_PLAIN,
                'highlighted' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $manager = new report_manager();
        $rows = $manager->get_rows(
            $course,
            $cm,
            $DB->get_record('videodiscussion', ['id' => $activity->id], '*', MUST_EXIST),
            [$user->id => $user],
            $groupa
        );

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['comments']);
        $this->assertSame(2, $rows[0]['replies']);
        $this->assertSame('1/1', $rows[0]['mandatorytext']);
    }
}
