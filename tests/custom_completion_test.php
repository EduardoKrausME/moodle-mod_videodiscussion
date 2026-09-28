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
 * Custom completion tests.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use advanced_testcase;
use mod_videodiscussion\completion\custom_completion;

/**
 * Tests custom completion rules.
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Watched percentage and mandatory prompts are evaluated independently.
     *
     * @return void
     */
    public function test_watched_and_mandatory_rules(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $activity = $this->getDataGenerator()->create_module('videodiscussion', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionwatch' => 1,
            'completionpercent' => 50,
            'completionmandatory' => 1,
        ]);

        $now = time();
        $promptid = $DB->insert_record('videodiscussion_threads', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $teacher->id,
            'groupid' => 0,
            'timepoint' => 10,
            'subject' => 'Required',
            'message' => 'Required prompt',
            'messageformat' => FORMAT_PLAIN,
            'mandatory' => 1,
            'requireownpost' => 0,
            'teacherprompt' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        rebuild_course_cache($course->id, true);
        $cm = get_fast_modinfo($course)->get_cm($activity->cmid);
        $completion = new custom_completion($cm, $user->id);

        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionwatch'));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionmandatory'));

        $DB->insert_record('videodiscussion_progress', (object)[
            'videodiscussionid' => $activity->id,
            'userid' => $user->id,
            'duration' => 100,
            'lastposition' => 60,
            'uniquewatched' => 60,
            'percent' => 60,
            'watchedsegments' => '[[0,60]]',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionwatch'));

        $DB->insert_record('videodiscussion_posts', (object)[
            'threadid' => $promptid,
            'parentid' => 0,
            'userid' => $user->id,
            'groupid' => 0,
            'message' => 'Answer',
            'messageformat' => FORMAT_PLAIN,
            'highlighted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionmandatory'));
    }

    /**
     * Rule list remains in sync with the completion class.
     *
     * @return void
     */
    public function test_defined_rules(): void {
        $this->assertSame(
            ['completionwatch', 'completionmandatory'],
            custom_completion::get_defined_custom_rules()
        );
    }
}
