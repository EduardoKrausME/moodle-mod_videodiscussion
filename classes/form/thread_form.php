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
 * Teacher discussion point form.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion\form;

use moodleform;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");

/**
 * Class thread_form.
 */
class thread_form extends moodleform {

    /**
     * Method definition.
     *
     * @return mixed Return value.
     */
    public function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'id', $custom['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'threadid', $custom['threadid'] ?? 0);
        $mform->setType('threadid', PARAM_INT);
        $mform->addElement('text', 'timepointtext', get_string('timepoint', 'videodiscussion'), ['size' => 12]);
        $mform->setType('timepointtext', PARAM_TEXT);
        $mform->addRule('timepointtext', null, 'required', null, 'client');
        $mform->addHelpButton('timepointtext', 'timepoint', 'videodiscussion');
        $mform->addElement('text', 'subject', get_string('subject', 'videodiscussion'), ['size' => 60]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');
        $mform->addElement('editor', 'message_editor', get_string('message', 'videodiscussion'), null, ['maxfiles' => 0]);
        $mform->addRule('message_editor', null, 'required', null, 'client');
        $mform->addElement('advcheckbox', 'mandatory', get_string('mandatory', 'videodiscussion'));
        $mform->addElement('advcheckbox', 'requireownpost', get_string('requireownpost', 'videodiscussion'));
        $mform->setDefault('requireownpost', empty($custom['defaultrequireownpost']) ? 0 : 1);

        $groups = [0 => get_string('allgroups', 'videodiscussion')];
        foreach ($custom['groups'] as $group) {
            $groups[$group->id] = format_string($group->name);
        }
        $mform->addElement('select', 'groupid', get_string('group', 'videodiscussion'), $groups);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validates form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (\mod_videodiscussion\timecode::parse((string)$data['timepointtext']) === null) {
            $errors['timepointtext'] = get_string('invalidtimepoint', 'videodiscussion');
        }
        return $errors;
    }
}
