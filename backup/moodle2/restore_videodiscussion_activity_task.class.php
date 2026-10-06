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
 * Restore task.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/videodiscussion/backup/moodle2/restore_videodiscussion_stepslib.php');

/**
 * Class restore_videodiscussion_activity_task.
 */
class restore_videodiscussion_activity_task extends restore_activity_task {

    /**
     * Method define_my_settings.
     *
     * @return mixed Return value.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Method define_my_steps.
     *
     * @return mixed Return value.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_videodiscussion_activity_structure_step('videodiscussion_structure', 'videodiscussion.xml'));
    }

    /**
     * Method define_decode_contents.
     *
     * @return mixed Return value.
     */
    public static function define_decode_contents(): array {
        return [new restore_decode_content('videodiscussion', ['intro'], 'videodiscussion')];
    }

    /**
     * Method define_decode_rules.
     *
     * @return mixed Return value.
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule(
                'VIDEODISCUSSIONVIEWBYID',
                '/mod/videodiscussion/view.php?id=$1',
                'course_module'
            ),
            new restore_decode_rule(
                'VIDEODISCUSSIONINDEX',
                '/mod/videodiscussion/index.php?id=$1',
                'course'
            ),
        ];
    }

    /**
     * Defines restore log mappings.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules(): array {
        return [];
    }
}
