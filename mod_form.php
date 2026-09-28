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

        $mform->addElement('html', '<h3>' . get_string('videosource', 'videodiscussion') . '</h3>');
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
            'accepted_types' => ['video'],
        ]);
        $mform->hideIf('videofile', 'videosource', 'neq', 'upload');

        $mform->addElement('html', '<h3>' . get_string('discussionpoints', 'videodiscussion') . '</h3>');
        $mform->addElement('advcheckbox', 'allowstudentthreads', get_string('allowstudentthreads', 'videodiscussion'));
        $mform->addHelpButton('allowstudentthreads', 'allowstudentthreads', 'videodiscussion');
        $mform->setDefault('allowstudentthreads', 1);
        $mform->addElement('advcheckbox', 'defaultrevealafterpost', get_string('defaultrevealafterpost', 'videodiscussion'));
        $mform->addHelpButton('defaultrevealafterpost', 'defaultrevealafterpost', 'videodiscussion');

        $editwindows = [
            0 => get_string('posteditwindowunlimited', 'videodiscussion'),
            300 => get_string('numminutes', '', 5),
            900 => get_string('numminutes', '', 15),
            1800 => get_string('numminutes', '', 30),
            3600 => get_string('numminutes', '', 60),
            7200 => get_string('numhours', '', 2),
            86400 => get_string('numdays', '', 1),
        ];
        $mform->addElement('select', 'posteditwindow', get_string('posteditwindow', 'videodiscussion'), $editwindows);
        $mform->setDefault('posteditwindow', 1800);
        $mform->addHelpButton('posteditwindow', 'posteditwindow', 'videodiscussion');

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
        parent::data_preprocessing($defaultvalues);

        $suffix = $this->get_suffix();
        $completionpercentel = 'completionpercent' . $suffix;
        $completionpercentenabledel = 'completionpercentenabled' . $suffix;
        $completionmandatoryel = 'completionmandatory' . $suffix;

        $defaultvalues[$completionpercentenabledel] = !empty($defaultvalues[$completionpercentel]) ? 1 : 0;
        if (empty($defaultvalues[$completionpercentel])) {
            $defaultvalues[$completionpercentel] = 90;
        }
        if (!isset($defaultvalues[$completionmandatoryel])) {
            $defaultvalues[$completionmandatoryel] = 0;
        }

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
        $suffix = $this->get_suffix();
        $completionpercentenabledel = 'completionpercentenabled' . $suffix;
        $completionpercentel = 'completionpercent' . $suffix;
        if (!empty($data[$completionpercentenabledel])
                && ((int)$data[$completionpercentel] < 1 || (int)$data[$completionpercentel] > 100)) {
            $errors[$completionpercentel] = get_string('invaliddata', 'error');
        }
        if ((float)$data['grade'] < 0) {
            $errors['grade'] = get_string('invaliddata', 'error');
        }
        foreach (['videofile'] as $field) {
            $draftid = (int)($data[$field] ?? 0);
            if ($draftid > 0) {
                $draftinfo = file_get_draft_area_info($draftid);
                if ((int)$draftinfo['filecount'] > 1) {
                    $errors[$field] = get_string('errormaxfiles', 'videodiscussion');
                }
            }
        }
        return $errors;
    }

    /**
     * Adds custom completion rules.
     *
     * @return array
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $group = [];
        $completionpercentenabledel = 'completionpercentenabled' . $suffix;
        $group[] =& $mform->createElement(
            'checkbox',
            $completionpercentenabledel,
            '',
            get_string('completionwatch', 'videodiscussion')
        );
        $completionpercentel = 'completionpercent' . $suffix;
        $group[] =& $mform->createElement('text', $completionpercentel, '', ['size' => 3]);
        $group[] =& $mform->createElement('static', '', '', '%');
        $mform->setType($completionpercentel, PARAM_INT);
        $completionpercentgroupel = 'completionpercentgroup' . $suffix;
        $mform->addGroup($group, $completionpercentgroupel, '', ' ', false);
        $mform->hideIf($completionpercentel, $completionpercentenabledel, 'notchecked');

        $completionmandatoryel = 'completionmandatory' . $suffix;
        $mform->addElement(
            'checkbox',
            $completionmandatoryel,
            '',
            get_string('completionmandatory', 'videodiscussion')
        );

        return [$completionpercentgroupel, $completionmandatoryel];
    }

    /**
     * Checks whether at least one custom completion rule is enabled.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return (!empty($data['completionpercentenabled' . $suffix])
                && (int)$data['completionpercent' . $suffix] > 0)
            || !empty($data['completionmandatory' . $suffix]);
    }

    /**
     * Normalises custom completion fields.
     *
     * @param stdClass $data
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix};
            $automatic = !empty($completion) && $completion == COMPLETION_TRACKING_AUTOMATIC;

            if (!$automatic || empty($data->{'completionpercentenabled' . $suffix})) {
                $data->{'completionpercent' . $suffix} = 0;
            }
            if (!$automatic) {
                $data->{'completionmandatory' . $suffix} = 0;
            }
        }
    }
}
