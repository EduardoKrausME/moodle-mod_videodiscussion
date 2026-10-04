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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy provider tests.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use advanced_testcase;
use context_module;
use core_privacy\local\request\approved_contextlist;
use mod_videodiscussion\privacy\provider;

/**
 * Tests privacy deletion semantics.
 *
 * @covers \mod_videodiscussion\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /**
     * Deleting one user keeps other users' replies.
     *
     * @return void
     */
    public function test_delete_user_keeps_other_replies(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('videodiscussion', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('videodiscussion', $activity->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $now = time();

        $teacherpromptid = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $teacher->id,
            'groupid' => 0,
            'timepoint' => 5,
            'subject' => 'Prompt',
            'message' => 'Teacher prompt',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 1,
            'requireownpost' => 0,
            'teacherprompt' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $threadid = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $owner->id,
            'groupid' => 0,
            'timepoint' => 10,
            'subject' => 'Owner thread',
            'message' => 'Owner message',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 0,
            'requireownpost' => 0,
            'teacherprompt' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $ownerpost = $DB->insert_record('videodiscussion_posts', (object)[
            'threadid' => $threadid,
            'parentid' => 0,
            'userid' => $owner->id,
            'groupid' => 0,
            'message' => 'Owner reply',
            'messageformat' => FORMAT_PLAIN,
            'highlighted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $otherpost = $DB->insert_record('videodiscussion_posts', (object)[
            'threadid' => $threadid,
            'parentid' => $ownerpost,
            'userid' => $other->id,
            'groupid' => 0,
            'message' => 'Other reply',
            'messageformat' => FORMAT_PLAIN,
            'highlighted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('videodiscussion_progress', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $owner->id,
            'duration' => 100,
            'lastposition' => 10,
            'uniquewatched' => 10,
            'percent' => 10,
            'watchedsegments' => '[[0,10]]',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $gradeid = $DB->insert_record('videodiscussion_grades', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $other->id,
            'grader' => $owner->id,
            'grade' => 80,
            'feedback' => 'Feedback',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $contextlist = new approved_contextlist($owner, 'mod_videodiscussion', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('videodiscussion_posts', ['id' => $ownerpost]));
        $this->assertTrue($DB->record_exists('videodiscussion_posts', ['id' => $otherpost]));

        $thread = $DB->get_record('videodiscussion_threads', ['id' => $threadid], '*', MUST_EXIST);
        $this->assertEquals(0, (int)$thread->userid);
        $this->assertSame('', $thread->subject);
        $this->assertSame('', $thread->message);

        $prompt = $DB->get_record('videodiscussion_threads', ['id' => $teacherpromptid], '*', MUST_EXIST);
        $this->assertEquals($teacher->id, (int)$prompt->userid);

        $this->assertFalse($DB->record_exists('videodiscussion_progress', [
            'videodiscussionid' => $activity->id,
            'userid' => $owner->id,
        ]));
        $grade = $DB->get_record('videodiscussion_grades', ['id' => $gradeid], '*', MUST_EXIST);
        $this->assertEquals(0, (int)$grade->grader);
    }

    /**
     * Bulk context deletion keeps prompts as configuration.
     *
     * @return void
     */
    public function test_delete_all_user_data_preserves_teacher_prompts(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('videodiscussion', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('videodiscussion', $activity->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $now = time();

        $promptid = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $teacher->id,
            'groupid' => 0,
            'timepoint' => 0,
            'subject' => 'Keep me',
            'message' => 'Prompt content',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 1,
            'requireownpost' => 0,
            'teacherprompt' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        provider::delete_data_for_all_users_in_context($context);

        $prompt = $DB->get_record('videodiscussion_threads', ['id' => $promptid], '*', MUST_EXIST);
        $this->assertEquals(0, (int)$prompt->userid);
        $this->assertSame('Keep me', $prompt->subject);
        $this->assertSame('Prompt content', $prompt->message);
    }
}
