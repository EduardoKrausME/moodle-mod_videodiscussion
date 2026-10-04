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
 * Player tests.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use advanced_testcase;

/**
 * Tests video source validation.
 *
 * @covers \mod_videodiscussion\player
 */
final class player_test extends advanced_testcase {
    /**
     * Provider-specific URLs must match the selected source.
     *
     * @return void
     */
    public function test_validate_source(): void {
        $this->assertTrue(player::validate_source('youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertFalse(player::validate_source('youtube', 'https://vimeo.com/123456'));
        $this->assertTrue(player::validate_source('vimeo', 'https://vimeo.com/123456'));
        $this->assertFalse(player::validate_source('vimeo', 'https://example.com/video.mp4'));
        $this->assertTrue(player::validate_source('url', 'https://example.com/video.mp4'));
        $this->assertFalse(player::validate_source('url', 'javascript:alert(1)'));
    }
}
