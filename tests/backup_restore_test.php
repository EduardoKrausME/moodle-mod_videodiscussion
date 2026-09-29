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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Backup and restore tests.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use advanced_testcase;

/**
 * Tests backup and restore behaviour.
 *
 * @coversNothing
 */
final class backup_restore_test extends advanced_testcase {
    /**
     * A backup without user data keeps teacher prompts but excludes all learner data.
     *
     * @return void
     */
    public function test_backup_without_userinfo_excludes_learner_data(): void {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $activity = $generator->create_module('videodiscussion', ['course' => $course->id]);
        $now = time();

        $promptid = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $teacher->id,
            'groupid' => 0,
            'timepoint' => 5,
            'subject' => 'Teacher prompt',
            'message' => 'Discuss this moment.',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 1,
            'requireownpost' => 1,
            'teacherprompt' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $learnerthreadid = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $student->id,
            'groupid' => 0,
            'timepoint' => 10,
            'subject' => 'Learner thread',
            'message' => 'Learner-created discussion.',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 0,
            'requireownpost' => 0,
            'teacherprompt' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('videodiscussion_posts', (object)[
            'threadid' => $promptid,
            'parentid' => 0,
            'userid' => $student->id,
            'groupid' => 0,
            'message' => 'Learner answer to teacher prompt.',
            'messageformat' => FORMAT_PLAIN,
            'highlighted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('videodiscussion_posts', (object)[
            'threadid' => $learnerthreadid,
            'parentid' => 0,
            'userid' => $student->id,
            'groupid' => 0,
            'message' => 'Learner reply.',
            'messageformat' => FORMAT_PLAIN,
            'highlighted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('videodiscussion_progress', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $student->id,
            'duration' => 100,
            'lastposition' => 50,
            'uniquewatched' => 50,
            'percent' => 50,
            'watchedsegments' => '[[0,50]]',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('videodiscussion_grades', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $student->id,
            'grader' => $teacher->id,
            'grade' => 80,
            'feedback' => 'Feedback',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $activity->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(false);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $rc = new \restore_controller(
            $backupid,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_CURRENT_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $activities = get_fast_modinfo($course)->get_instances_of('videodiscussion');
        $this->assertCount(2, $activities);

        $restoredid = 0;
        foreach ($activities as $cm) {
            if ((int)$cm->instance !== (int)$activity->id) {
                $restoredid = (int)$cm->instance;
                break;
            }
        }
        $this->assertGreaterThan(0, $restoredid);

        $threads = $DB->get_records('videodiscussion_threads', ['videodiscussionid' => $restoredid]);
        $this->assertCount(1, $threads);

        $restoredprompt = reset($threads);
        $this->assertEquals(1, $restoredprompt->teacherprompt);
        $this->assertEquals(0, $restoredprompt->userid);
        $this->assertEquals('Teacher prompt', $restoredprompt->subject);
        $this->assertEquals('Discuss this moment.', $restoredprompt->message);

        $this->assertFalse($DB->record_exists('videodiscussion_posts', ['threadid' => $restoredprompt->id]));
        $this->assertFalse($DB->record_exists('videodiscussion_progress', ['videodiscussionid' => $restoredid]));
        $this->assertFalse($DB->record_exists('videodiscussion_grades', ['videodiscussionid' => $restoredid]));
    }
}
