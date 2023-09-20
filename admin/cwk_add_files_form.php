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
 * CWK Add File form
 *
 * @package     local
 * @subpackage  cwk_add_files_mgr
 * @copyright   2023 Onwards CoSector
 * @author      Delvon Forrester <delvon.forrester@esparanza.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/filelib.php');

class cwk_add_files_form extends moodleform
{
    function definition() {
        $mform =& $this->_form;

        $maxfiles = get_config('local_cwk_add_files_mgr', 'maxfiles') ?: 20;

        $filemanager_options = array();
        $filemanager_options['accepted_types'] = '*';
        $filemanager_options['maxbytes'] = 0;
        $filemanager_options['maxfiles'] = $maxfiles;

        $mform->addElement('filemanager', 'files', get_string('addfiles',
                'local_cwk_add_files_mgr'), null, $filemanager_options);

        //-------------------------------------------------------
        $this->add_action_buttons();
    }

    /*function data_preprocessing(&$default_values) {
        $draftitemid = file_get_submitted_draft_itemid('files');
        $context = context_system::instance();
        file_prepare_draft_area($draftitemid, $context->id, 'local_cwk_add_files_mgr', 'introattachment', 0, array('subdirs'=>0));
        $default_values['files'] = $draftitemid;
    }*/

    function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['files'], 'sortorder, id', false)) {
            $errors['files'] = get_string('required', 'local_cwk_add_files_mgr');
            return $errors;
        }
    }
}
