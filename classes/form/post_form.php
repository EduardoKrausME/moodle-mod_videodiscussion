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
 * Post edit form.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Form for editing one response.
 */
class post_form extends moodleform {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'id', $custom['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'postid', $custom['postid']);
        $mform->setType('postid', PARAM_INT);

        $mform->addElement('textarea', 'message', get_string('message', 'videodiscussion'), [
            'rows' => 6,
            'cols' => 70,
        ]);
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', null, 'required', null, 'client');

        $this->add_action_buttons();
    }

    /**
     * Validates the response.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string)($data['message'] ?? '')) === '') {
            $errors['message'] = get_string('errorposting', 'videodiscussion');
        }
        return $errors;
    }
}
