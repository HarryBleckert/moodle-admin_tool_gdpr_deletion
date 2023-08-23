<?php
/*
 * @package    tool_gdpr_deletion
 * @copyright  2021 onwards Harry.Bleckert@ash-berlin.eu for ASH Berlin
 * @author     Harry.Bleckert@ash-berlin.eu

	script to GDPR - delete range of ASH LDAP Moodle users with deleted=1 and no login for more than defined days OR user account selected by user->id
	- this was written fast and dirty but with sufficient testing
	- Script utilizes Moodle Core GDPR and privacy classes

 *
*/


//require_once("../../../config.php");
defined('MOODLE_INTERNAL') || die();
global $CFG, $PAGE, $ADMIN;


//if ($hassiteconfig) {
	/*
    // Add own category for plugin's  and subplugins' settings.
    $ADMIN->add('users', new admin_category('tool_gdpr_deletion', get_string('pluginname', 'tool_gdpr_deletion')));
    // Add entry for own settings.
    $ADMIN->add('tool_gdpr_deletion', new admin_externalpage('tool_gdpr_deletion',
        get_string('pluginname', 'tool_gdpr_deletion'),
        "$CFG->wwwroot/$CFG->admin/tool/gdpr_deletion/index.php"
    ));
	*/
//}

$ADMIN->add('accounts', new admin_externalpage('tool_gdpr_deletion',
        get_string('pluginname', 'tool_gdpr_deletion'),
        "$CFG->wwwroot/$CFG->admin/tool/gdpr_deletion/index.php", "moodle/user:update"
    ));