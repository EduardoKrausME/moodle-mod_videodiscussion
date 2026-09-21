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
 * Activity settings form.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Class mod_videodiscussion_mod_form.
 */
class mod_videodiscussion_mod_form extends moodleform_mod {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'videosettings', get_string('videosource', 'videodiscussion'));
        $sources = [
            'url' => get_string('sourceurl', 'videodiscussion'),
            'upload' => get_string('sourceupload', 'videodiscussion'),
            'youtube' => get_string('sourceyoutube', 'videodiscussion'),
            'vimeo' => get_string('sourcevimeo', 'videodiscussion'),
        ];
        $mform->addElement('select', 'videosource', get_string('videosource', 'videodiscussion'), $sources);
        $mform->setDefault('videosource', 'url');

        $mform->addElement('url', 'videourl',
            get_string('videourl', 'videodiscussion'), ['size' => '70'], ['usefilepicker' => false]);
        $mform->setType('videourl', PARAM_URL);
        $mform->hideIf('videourl', 'videosource', 'eq', 'upload');

        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videodiscussion'), null, [
            'subdirs' => false,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]);
        $mform->hideIf('videofile', 'videosource', 'neq', 'upload');

        $mform->addElement('header', 'discussionoptions', get_string('discussionpoints', 'videodiscussion'));
        $mform->addElement('advcheckbox', 'allowstudentthreads', get_string('allowstudentthreads', 'videodiscussion'));
        $mform->addHelpButton('allowstudentthreads', 'allowstudentthreads', 'videodiscussion');
        $mform->setDefault('allowstudentthreads', 1);
        $mform->addElement('advcheckbox', 'defaultrevealafterpost', get_string('defaultrevealafterpost', 'videodiscussion'));
        $mform->addHelpButton('defaultrevealafterpost', 'defaultrevealafterpost', 'videodiscussion');

        $mform->addElement('text', 'completionpercent', get_string('completionpercent', 'videodiscussion'), ['size' => 4]);
        $mform->setType('completionpercent', PARAM_INT);
        $mform->setDefault('completionpercent', 90);
        $mform->addHelpButton('completionpercent', 'completionpercent', 'videodiscussion');

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'videodiscussion'), ['size' => 5]);
        $mform->setType('grade', PARAM_FLOAT);
        $mform->setDefault('grade', 100);
        $mform->addHelpButton('grade', 'maximumgrade', 'videodiscussion');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Prepares existing file data.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if ($this->current && $this->current->id && $this->context) {
            $draftitemid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_videodiscussion',
                'video',
                0,
                ['subdirs' => false, 'maxfiles' => 1]
            );
            $defaultvalues['videofile'] = $draftitemid;
        }
    }

    /**
     * Validates settings.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (in_array($data['videosource'], ['url', 'youtube', 'vimeo'], true) && empty($data['videourl'])) {
            $errors['videourl'] = get_string('invalidvideo', 'videodiscussion');
        }
        if ($data['videosource'] === 'upload') {
            $draftid = (int)($data['videofile'] ?? 0);
            $draftinfo = $draftid ? file_get_draft_area_info($draftid) : null;
            if (!$draftinfo || empty($draftinfo['filecount'])) {
                $errors['videofile'] = get_string('invalidvideo', 'videodiscussion');
            }
        }
        if ((int)$data['completionpercent'] < 1 || (int)$data['completionpercent'] > 100) {
            $errors['completionpercent'] = get_string('invaliddata', 'error');
        }
        if ((float)$data['grade'] < 0) {
            $errors['grade'] = get_string('invaliddata', 'error');
        }
        return $errors;
    }
}
