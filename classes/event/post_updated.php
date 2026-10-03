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
 * Video discussion post event.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\event;

/**
 * post updated event.
 */
class post_updated extends \core\event\base {
    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'videodiscussion_posts';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpostupdated', 'videodiscussion');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' updated video discussion post '{$this->objectid}'.";
    }

    /**
     * Return the event URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/videodiscussion/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Return the object ID mapping used during restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'videodiscussion_posts', 'restore' => 'videodiscussion_post'];
    }

    /**
     * Return the additional mappings used during restore.
     *
     * @return array
     */
    protected function get_other_mapping() {
        return ['threadid' => ['db' => 'videodiscussion_threads', 'restore' => 'videodiscussion_thread']];
    }
}
