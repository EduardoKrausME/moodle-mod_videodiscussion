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
 * View event.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\event;

/**
 * Class course_module_viewed.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Method init.
     *
     * @return mixed Return value.
     */
    protected function init() {
        parent::init();
        $this->data['objecttable'] = 'videodiscussion';
    }

    /**
     * Method get_name.
     *
     * @return mixed Return value.
     */
    public static function get_name() {
        return get_string('eventcoursemoduleviewed', 'videodiscussion');
    }

    /**
     * Method get_objectid_mapping.
     *
     * @return mixed Return value.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'videodiscussion', 'restore' => 'videodiscussion'];
    }
}
