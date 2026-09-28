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
 * Video discussion thread event.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\event;

/**
 * thread deleted event.
 */
class thread_deleted extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'videodiscussion_threads';
    }

    public static function get_name() {
        return get_string('eventthreaddeleted', 'videodiscussion');
    }

    public function get_description() {
        return "The user with id '{$this->userid}' deleted video discussion thread '{$this->objectid}'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/videodiscussion/view.php', ['id' => $this->contextinstanceid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'videodiscussion_threads', 'restore' => 'videodiscussion_thread'];
    }
}
