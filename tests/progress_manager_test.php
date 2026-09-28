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
 * Progress manager tests.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use advanced_testcase;

/**
 * Tests watched-segment tracking.
 *
 * @covers \mod_videodiscussion\progress_manager
 */
final class progress_manager_test extends advanced_testcase {
    /**
     * Progress merges legitimate intervals and rejects implausible jumps.
     *
     * @return void
     */
    public function test_progress_merge_and_forged_jump(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('videodiscussion', ['course' => $course->id]);

        $manager = new progress_manager();
        $record = $manager->save($activity->id, $user->id, 100, 10, [[0, 10]]);
        $this->assertEqualsWithDelta(10.0, (float)$record->percent, 0.01);

        $forged = [];
        for ($start = 0; $start < 100; $start += 10) {
            $forged[] = [$start, $start + 10];
        }

        $record = $manager->save($activity->id, $user->id, 100, 100, $forged);
        $this->assertEqualsWithDelta(10.0, (float)$record->percent, 0.01);
    }
}
