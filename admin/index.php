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
 * Admin page for tabs 
 *
 * @package     local
 * @subpackage  cwk_add_files_mgr
 * @copyright   2023 Onwards CoSector
 * @author      Delvon Forrester <delvon.forrester@esparanza.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/cwk_add_files_form.php');
global $PAGE, $DB, $USER, $CFG, $OUTPUT;

$context = context_system::instance();
require_login();
require_capability('local/cwk_add_files_mgr:addfiles', $context);

$tab = optional_param('tab', '', PARAM_TEXT);
$subtab = optional_param('subtab', '', PARAM_TEXT);
$tabs = ['add'];
if (has_capability('local/cwk_add_files_mgr:deletefiles', $context)) {
    $tabs[] = 'delete';
}

// We remember the last tab the user was on and show them that by default.
if ($tab) {
    $SESSION->local_cwk_add_files_mgr_admin_tab = $tab;
} else if (isset($SESSION->local_cwk_add_files_mgr_admin_tab) && $SESSION->local_cwk_add_files_mgr_admin_tab) {
    $tab = $SESSION->local_cwk_add_files_mgr_admin_tab;
} else {
    $tab = $tabs[0];
}
if ($subtab) {
    $SESSION->local_cwk_add_files_mgr_admin_subtab = $subtab;
} else if (isset($SESSION->local_cwk_add_files_mgr_admin_subtab) && $SESSION->local_cwk_add_files_mgr_admin_subtab) {
    $subtab = $SESSION->local_cwk_add_files_mgr_admin_subtab;
} else {
    $subtab = '';
}


$courseformid = optional_param('cseid', 0, PARAM_INT);
$url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$renderer = $PAGE->get_renderer('core');
$PAGE->set_title('CWK add files manager');
$pageheading = get_string('pageheading', 'local_cwk_add_files_mgr');
$PAGE->set_heading($pageheading);

// Data for mustache template.
$mustachedata = [
    'pageid' => 'local_cwk_add_files_mgr_admin_' . $tab,
    'pageheading' => $pageheading,
    'sesskey' => sesskey(),
    'tabs' => [],
    'siteadmin' => is_siteadmin() ? 1: 0
];
$url='';
foreach ($tabs as $key) {
    $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php', ['tab' => $key]);
    if ($key == 'delete' && !$subtab) {
        $url .= '&subtab=deleteu';
    }
    $mustachedata['tabs'][] = [
        'id' => $key,
        'title' => $key ? get_string($key, 'local_cwk_add_files_mgr') : '',
        'active' => $tab == $key ? 1 : 0,
        'url' => $url
    ];
}
if ($tab == 'delete' && !$subtab) {
    $subtab = 'deleteu';
}
switch ($tab) {
    case 'add':        
        $mform = new cwk_add_files_form();
        $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php', ['tab' => 'add']);

        if ($mform->is_cancelled()) {
            redirect($url, get_string('cancelled', 'local_cwk_add_files_mgr'));
        } else if ($data = $mform->get_data()) {
            $maxfiles = get_config('local_cwk_add_files_mgr', 'maxfiles') ?: 20;
            
            // Save the files to table to delete by upload but itemid might exist so change it.
            // We only want to ensure that all files saved now have a unique uploadid.
            $record = $data->files;
            $uploadexist = true;
            while ($uploadexist) {
                if ($upload = $DB->record_exists('local_cwk_add_files_mgr',
                        ['uploadid' => $record])) {
                    $record = $record + rand(1,100);
                    $uploadexist = true;
                } else {
                    $uploadexist = false;
                }                
            }

            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            $now = time();
            $fileerrors = '';
            if ($files = $fs->get_area_files($usercontext->id,
                'user', 'draft', $data->files, 'sortorder, id', false)) {
                foreach ($files as $k => $f) {
                    $fname = $f->get_filename();
                    if ($fname != '.') {
                        // Work out where this file will be stored.
                        $cidctx = get_context_and_course_from_filename($fname);
                        if (!empty($cidctx)) {
                            $newcontextid = $cidctx[1];
                            $newcourseid = $cidctx[0];
                            $newfilename = $cidctk[2];
                            // Check if any of the files exist for that coursework instance.
                            if ($fileexist = $fs->get_file($newcontextid, 'mod_coursework',
                                    'introattachment', 0, '/', $newfilename)) {
                                $fileerrors .= get_string('alreadyexist', 'local_cwk_add_files_mgr', $newfilename);
                                continue;
                            }

                            // Save draft files permanently in files table.
                            $filerecord = [
                                'contextid'    => $newcontextid,
                                'component'    => 'mod_coursework',
                                'filearea'     => 'introattachment',
                                'itemid'       => 0,
                                'filepath'     => '/',
                                'filename'     => $newfilename,
                                'timecreated'  => $now,
                                'timemodified' => $now,
                            ];
                            $newfile = $fs->create_file_from_storedfile($filerecord, $f);
                            // Create record for inserting in plugin table.
                            $file = new stdClass();
                            $file->uploadid = $record;
                            $file->courseid = $newcourseid;
                            $file->contextid = $newcontextid;
                            $file->fileid = $newfile->get_id();
                            $file->userid = $USER->id;
                            $file->timecreated = $now;
                            $DB->insert_record('local_cwk_add_files_mgr', $file);
                        } else {
                            $fileerrors .= get_string('cwknotexist', 'local_cwk_add_files_mgr', $fname);
                        }
                    }
                }                
                // Now delete the temp user draft files.
                $fs->delete_area_files($usercontext->id, 'user', 'draft', $data->files);
            }

            $message = $fileerrors ? $fileerrors : get_string('success', 'local_cwk_add_files_mgr');
            if ($fileerrors) {
                redirect($url, $message, null, \core\output\notification::NOTIFY_ERROR);
            } else {
                redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
            }
        } else {
            // This branch is executed if the form is submitted but the data doesn't
            // validate and the form should be redisplayed or on the first display of the form.

            // Set anydefault data (if any).
            //$mform->set_data(context_system::instance());

            // Display the form.
            $mustachedata['aform'] = 1;
            $mustachedata['form'] = $mform->render();
        }        
        break;
    case ($tab =='delete' && $subtab  == 'deletec') :
        $tab = 'deletec';
        $fileid = optional_param('fileid', '', PARAM_TEXT);
        if ($fileid) {
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($fileid);
            $fname = $file->get_filename();
            $file->delete();
            $DB->delete_records('local_cwk_add_files_mgr', ['fileid' => $fileid]);
            $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php');
            redirect($url, get_string('deleted_single', 'local_cwk_add_files_mgr', $fname),
                    null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $delcid = optional_param('delcid', '', PARAM_TEXT);
        if ($delcid) {
            $fs = get_file_storage();
            $delfiles = $DB->get_recordset('local_cwk_add_files_mgr',
                    ['courseid' => $delcid], $sort = '', 'contextid',);
            foreach ($delfiles as $f) {
                $fs->delete_area_files($f->contextid, 'mod_coursework','introattachment',0);
            }
            $delfiles->close();
            $DB ->delete_records('local_cwk_add_files_mgr',['courseid' => $delcid]);
            $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php');
            redirect($url, get_string('deleted', 'local_cwk_add_files_mgr'),
                    null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $courses = get_user_capability_course('local/cwk_add_files_mgr:deletefiles', null, true, $fieldsexceptid = '', $orderby = 'fullname');
        if (!$courses) {
            $courses = [];
        }
        $allcourses = $cses = [];
        foreach ($courses as $c) {
            if ($c->id == 1) continue;
            $cmods = get_course_mods($c->id);
            foreach ($cmods as $mod) {
                $select = '';
                if ($courseformid && $mod->course == $courseformid) {
                    $select = 'selected';
                }
                if ($mod->modname == 'coursework') {
                    $cses[$mod->course] = ['cid' => $mod->course, 'title' =>
                        get_course($mod->course)->fullname, 'selt' => $select];                    
                }
            }
        }
        if ($courseformid) {
            $headers = [
                'cwkid' => 'CMID',
                'cwkname' => 'CWK Name',
                'filename' => 'Filename',
                '' => '',
            ];
            $index = 1;
            foreach ($headers as $key => $string) {
                $newitem = [
                    'index' => $index,
                    'label' => $string,
                    'key' => $key
                ];
                $index++;
                $mustachedata['headers'][] = $newitem;
            }
            $cse = new stdClass();
            $cse->course = get_course($courseformid)->fullname;
            $cse->courseid = $courseformid;
            $cse->courseurl = new moodle_url('/course/view.php', ['id' => $courseformid]);

            $fs = get_file_storage();
            $ifiles = [];
            $fileids = $DB->get_recordset('local_cwk_add_files_mgr',
                        ['courseid' => $courseformid], $sort ='', 'contextid');
            foreach ($fileids as $ctx) {
                // Returns an array of `stored_file` instances.
                $ifiles[] = $fs->get_area_files($ctx->contextid, 'mod_coursework', 'introattachment', 0);
            }

            $moduleid = $DB->get_field('modules', 'id', ['name' => 'coursework']);
            foreach ($ifiles as $files) {
                foreach ($files as $file) {
                    if ($file->get_filename() == '.') continue;
                    $contextid = $file->get_contextid();            
                    $cmid = $DB->get_field('context', 'instanceid', ['id' => $contextid]);
                    $cm = $DB->get_record('course_modules', ['module' => $moduleid, 'id' => $cmid]);
                    $cwk = $DB->get_record('coursework', ['id' => $cm->instance], 'id,name');
                    $mod = new stdClass();
                    $mod->modid = $cm->id;
                    $mod->contextid = $contextid;
                    $mod->modname = $cwk->name;
                    $mod->cwkurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
                    $mod->filename = $file->get_filename();
                    $mod->fileid = $file->get_id();
                    $mod->fileurl = moodle_url::make_pluginfile_url(
                        $file->get_contextid(), $file->get_component(),
                        $file->get_filearea(), $file->get_itemid(), $file->get_filepath(),
                        $file->get_filename(), false // Do not force download of the file.
                    );
                    $cse->anyfiles = 1;
                    if (isset($mod)) {
                        $cse->mods[] = (array) $mod;
                    }
                }
            }
            $mustachedata['allmods'][] = (array) $cse;
        }
        $mustachedata['modulelist'] = array_values($cses);
        $mustachedata['coursename'] = 1;
        break;
    case ($tab =='delete' && $subtab  == 'deleteu') :
        $tab = 'deleteu';
        $fileid = optional_param('fileid', '', PARAM_TEXT);
        if ($fileid) {
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($fileid);
            $fname = $file->get_filename();
            $file->delete();
            $DB ->delete_records('local_cwk_add_files_mgr', ['fileid' => $fileid]);
            $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php');
            redirect($url, get_string('deleted_single', 'local_cwk_add_files_mgr', $fname),
                    null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $upid = optional_param('upid', '', PARAM_TEXT);
        if ($upid) {
            //Get the fileid of files in upload.
            $uplds = $DB->get_recordset('local_cwk_add_files_mgr',
                    ['uploadid' => $upid], $sort = '', 'fileid');
            $fs = get_file_storage();
            foreach ($uplds as $f) {
                $file = $fs->get_file_by_id($f->fileid);
                $file->delete();
            }
            $uplds->close();
            $DB ->delete_records('local_cwk_add_files_mgr', ['uploadid' => $upid]);
            $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php');
            redirect($url, get_string('deleted', 'local_cwk_add_files_mgr'),
                    null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $courses = $DB->get_recordset('local_cwk_add_files_mgr', [],$sort = 'id');
        $cses = [];
        $currentupload = '';
        $uploadformid = $courseformid;
        foreach ($courses as $c) {
            $select = '';
            $usr = $DB->get_record_sql("SELECT concat(firstname,' ',lastname)
                    as fullname FROM {user} WHERE id =" . $c->userid);                        
            $upld = $usr->fullname . ' - ' . userdate($c->timecreated);
            if ($uploadformid && $c->uploadid == $uploadformid) {
                $select = 'selected';
                $currentupload = $upld;
            }
            $cses[$c->uploadid] = ['cid' => $c->uploadid, 'title' =>
                $upld, 'selt' => $select];
        }
        $courses->close();
        if ($uploadformid) {
            $headers = [
                'cwkid' => 'CMID',
                'cwkname' => 'CWK Name',
                'course' => 'Course',
                'filename' => 'Filename',
                '' => '',
            ];
            $index = 1;
            foreach ($headers as $key => $string) {
                $newitem = [
                    'index' => $index,
                    'label' => $string,
                    'key' => $key
                ];
                $index++;
                $mustachedata['headers'][] = $newitem;
            }
            $cse = new stdClass();
            $cse->uploadtext = $currentupload;
            $cse->uploadid = $uploadformid;

            $fs = get_file_storage();
            $files = [];
            // Have to get files by ID here because there could be multiple files with same context.
            $fileids = $DB->get_recordset('local_cwk_add_files_mgr',
                    ['uploadid' => $uploadformid], $sort ='', 'fileid');            
            foreach ($fileids as $ctx) {
                // Returns an array of `stored_file` instances.
                $files[] = $fs->get_file_by_id($ctx->fileid);
            }
            $fileids->close();
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'coursework']);
            foreach ($files as $file) {
                if ($file->get_filename() == '.') continue;
                $contextid = $file->get_contextid();
                $cmid = $DB->get_field('context', 'instanceid', ['id' => $contextid]);
                $cm = $DB->get_record('course_modules', ['module' => $moduleid, 'id' => $cmid]);
                $cwk = $DB->get_record('coursework', ['id' => $cm->instance], 'id,name');
                $course = $DB->get_record('course', ['id' => $cm->course], 'id,fullname');
                $mod = new stdClass();
                $mod->modid = $cm->id;
                $mod->contextid = $contextid;
                $mod->modname = $cwk->name;
                $mod->cwkurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
                $mod->modname = $cwk->name;
                $mod->courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                $mod->course = $course->fullname;
                $mod->fileid = $file->get_id();
                $mod->filename = $file->get_filename();
                $mod->fileurl = moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(),
                    $file->get_filearea(), $file->get_itemid(), $file->get_filepath(),
                    $file->get_filename(), false // Do not force download of the file.
                );
                $cse->anyfiles = 1;
                if (isset($mod)) {
                    $cse->mods[] = (array) $mod;
                }
            }
            $mustachedata['allmods'][] = (array) $cse;
        }
        $mustachedata['modulelist'] = array_values($cses);
        $mustachedata['coursename'] = 0;
        break;
    default:
        $tab = 'add';
}

// Now that we have mustache data we can render the page.
echo $OUTPUT->header();
echo $renderer->render_from_template('local_cwk_add_files_mgr/' . $tab, $mustachedata);
echo $OUTPUT->footer();
