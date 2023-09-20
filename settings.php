<?php

defined('MOODLE_INTERNAL') || die();

$settings =  new admin_settingpage('local_cwk_add_files_mgr', get_string('pluginname', 'local_cwk_add_files_mgr'));
$localexits = $ADMIN->locate('localplugins');

if (!is_null($localexits)) {

    $ADMIN->add('localplugins', $settings);

    /*
    * ----------------------
    * Max files upload settings
    * ----------------------
    */
    $settings->add(new admin_setting_configtext('local_cwk_add_files_mgr/maxfiles',
            get_string('maxfiles', 'local_cwk_add_files_mgr'), 
            get_string('maxfiles_desc', 'local_cwk_add_files_mgr'),
            20, PARAM_INT));
}