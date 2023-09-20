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
 * Library of functions for the plugin cwk_add_files_mgr
 * @package     local
 * @subpackage  cwk_add_files_mgr
 * @copyright   2023 Onwards CoSector
 * @author      Delvon Forrester <delvon.forrester@esparanza.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param \core_user\output\myprofile\tree $tree
 * @param $user
 * @param $iscurrentuser
 * @param $course
 * @return bool
 */
function local_cwk_add_files_mgr_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course){

    if(!$iscurrentuser){
        return false;
    }

    $context = context_system::instance();
    if(!has_capability('local/cwk_add_files_mgr:addfiles', $context) &&
            !has_capability('local/cwk_add_files_mgr:deletefiles', $context)){
        return false;
    }

    //add category for this module
    if (!array_key_exists('courseworkmanagement', $tree->__get('categories'))) {// add category if this doesn't exist
        $title = get_string('userprofilecategory', 'local_cwk_add_files_mgr');
        $category = new core_user\output\myprofile\category('courseworkmanagement', $title);
        $tree->add_category($category);
    }

    //add links to category
    $url = new moodle_url('/local/cwk_add_files_mgr/admin/index.php');
    $linktext = get_string('pluginname', 'local_cwk_add_files_mgr');
    $node = new core_user\output\myprofile\node('courseworkmanagement', 'cwkaddfilesmgr', $linktext, null, $url);
    $tree->add_node($node);

    return true;
}

function get_context_and_course_from_filename($filename) {
    global $DB;
    $ex = explode('-', $filename);
    $grpname = $ex[0];
    $newfilename = $ex[1];
    if ($grpname && $newfilename) {
        // Get the group id with this name.
        if ($group = $DB->get_record('groups', ['name' => $grpname], 'id,courseid', IGNORE_MULTIPLE)) {
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'coursework']);
            $sql = "SELECT id 
                FROM {course_modules} 
                WHERE module = :module
                AND course = :course
                AND availability LIKE '%{\"type\":\"group\",\"id\":{$group->id}}%'
                ORDER BY id desc limit 1";
            if ($cm = $DB->get_record_sql($sql, ['module' => $moduleid, 'course' => $group->courseid])) {
                $ctxid = $DB->get_field('context', 'id', ['contextlevel' => 70,
                    'instanceid' => $cm->id]);
                return [$group->courseid, $ctxid, $newfilename];
            }
        }
    }
    return [];
}
