<?php

/**
 * @package    tool_gdpr_deletion
 * @copyright  2021 onwards Harry.Bleckert@ash-berlin.eu for ASH Berlin
 * @author     Harry.Bleckert@ash-berlin.eu
 *
 *
 * script to GDPR - delete range of ASH LDAP Moodle users with deleted=1 and no login for more than defined days OR user account
 *         selected by user->id
 * - this was written fast and dirty but with sufficient testing
 * - Script utilizes Moodle Core GDPR and privacy classes
 */

if (!defined('REQUIRE_SESSION_LOCK')) {
    define('REQUIRE_SESSION_LOCK', false);
}
if (!defined('NO_OUTPUT_BUFFERING')) {
    define('NO_OUTPUT_BUFFERING', true);
}
require_once("../../../config.php");
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/adminlib.php');
global $DB, $USER, $CFG;

/*
$DB->set_debug(false);
$CFG->debugdisplay = 0;
$CFG->debug = 0;
*/
//require_login(null,false);
ini_set("output_buffering", 350);

$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 0, PARAM_INT);
$lastaccess = optional_param('lastaccess', 720, PARAM_INT);
$dryrun = optional_param('dryrun', "", PARAM_ALPHANUM);
$modulname = "GDPR user deletion for ASH";
$thisURL = "/admin/tool/gdpr_deletion/";
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$errMsg = "";

$PAGE->set_context($context);
//$PAGE->set_pagelayout('popup'); // popup, standard, incourse
$PAGE->set_url($thisURL, array('userid' => $userid, 'limit' => $limit));
$this_url = new moodle_url($thisURL, array());
$PAGE->navbar->add($modulname, $this_url);
//$PAGE->navbar->add(format_string($modulname));

$PAGE->set_title($modulname);
$PAGE->set_heading($modulname);

echo $OUTPUT->header();
echo $OUTPUT->heading($modulname);

if (!is_siteadmin()) {
    echo $OUTPUT->notification("Only Moodle Adminstrators can access this plugin!");
    echo $OUTPUT->footer();
    exit;
}
if (!$limit and !$userid) {
    echo $OUTPUT->notification("Your need to specify 'userid' for single GDPR deletion or 'limit' fo number of deletions!");

    $buttonStyle = "";
    ?>
    <form style="line-break:inline;" method="POST">
        User by ID: <input name="userid" type="text" size="8" style="<?php echo $buttonStyle; ?>"
                           value="<?php echo $userid; ?>"><br><b>OR</b><br>
        Maximum number of deletions <input name="limit" type="text" size="5" style="<?php echo $buttonStyle; ?>"
                                           value="<?php echo $limit; ?>">
        with <input name="lastaccess" type="text" size="3" style="<?php echo $buttonStyle; ?>" value="<?php echo $lastaccess; ?>">
        Days since last access<br>
        <input name="dryrun" type="submit" style="<?php echo $buttonStyle; ?>" value="Start DryRun">
        <input type="submit" style="<?php echo $buttonStyle; ?>" value="Start Deletion">
    </form>
    <?php
    echo $OUTPUT->footer();
    exit;
}

/* no settings form yet 
$content = '';
$mform = new \tool_cleanupusers\subplugin_select_form();
if ($formdata = $mform->get_data()) {
    $arraydata = get_object_vars($formdata);
    if ($mform->is_validated()) {
        set_config('cleanupusers_subplugin', $arraydata['subplugin'], 'tool_cleanupusers');
        $content = 'You successfully submitted the subplugin.';
    }
}
$mform->display();
$config = get_config('tool_gdpr_deletion');  //, 'cleanupusers_subplugin');
*/

$accesslimit = time() - ($lastaccess * 24 * 60 * 60);  // Default: 720 days
// only records which are marked as deleted but not deleted by Moodle Core GDPR
//deleted=1 AND username NOT LIKE '%@% AND email LIKE '%@%' AND lastaccess < $accesslimit
$ufilter = $limitN = "";
if ($userid) {
    $ufilter = "id = $userid";
    $limitN = "LIMIT 1";
} else {
    $ufilter = "auth='ash_authsrv' AND deleted=1 AND lastaccess <= $accesslimit AND timecreated <= $accesslimit";
    $limitN = "LIMIT $limit";
    if (isset($CFG->ash) and $CFG->ash) {
        $ufilter .= " AND auth='ash_authsrv' AND deleted=1";
    }
}
$sql = "SELECT * FROM {user} WHERE $ufilter AND username NOT LIKE '%@%' AND username NOT ILIKE 'unknown%' 
				AND (email LIKE '%@%' OR email ILIKE '%unknown%' OR LENGTH(username)<27) ORDER BY id ASC $limitN";
$users = $DB->get_records_sql($sql);
$started = time();
$hits = count($users);
ini_set("output_buffering", 2048);
print "\n<hr><b>Found $hits ASH user accounts " . ($dryrun ? "ready for GDPR deletion (DryRun)" : "to be GDPR deleted") .
        "!</b><br>\nQuery: $sql<br><br>\n";
if ($userid and !$hits) {
    $user = $DB->get_record_sql("SELECT * FROM {user} WHERE $ufilter");
    if (isset($user->id)) {
        print "<b>User selected for Deletion is already GDPR deleted:</b><br>$user->id - $user->username - $user->firstname $user->lastname" .
                " - $user->email - Deleted: $user->deleted - Lastaccess: " .
                gmdate("Y-m-d", $user->lastaccess) . " - created: " . gmdate("Y-m-d", $user->timecreated) . "<br>\n";
    } else {
        print "<b style=\\";
        echo $OUTPUT->footer();
        exit;
    }
}
$cnt = $errCnt = 0;
@ob_flush();
@ob_end_flush();
@flush();
@ob_start();
foreach ($users as $user) {
    $cnt++; //if ( $cnt>=80) { break;}
    $msg = $cnt .
            ". $user->username ($user->id) - $user->firstname $user->lastname - $user->email - Deleted: $user->deleted - Lastaccess: " .
            gmdate("Y-m-d", $user->lastaccess) . " - created: " . gmdate("Y-m-d", $user->timecreated) . "<br>\n";
    print $msg;
    print "<script>window.scrollTo(0,document.body.scrollHeight);</script>\n";
    if ($user) {
        if ($user->id == $USER->id) { // Self deletion attempt.
            echo $OUTPUT->notification("You tried to delete your own user account. This is not permitted!");
            continue;
        } else if (is_siteadmin($user)) // can't delete a siteadmin account
        {
            echo $OUTPUT->notification("You tried to delete an administrator account. This is not permitted!");
            continue;
        }
    }
    if (!$dryrun or ($cnt / 6) == round($cnt / 6, 0)) {
        print "\n<script>window.scrollTo(0,document.body.scrollHeight);</script>\n";
        @ob_flush();
        @ob_end_flush();
        @flush();
        @ob_start();
    }
    if (!$dryrun) {
        set_time_limit(180);
        if ($user->deleted) {
            $DB->execute("UPDATE {user} SET deleted=0,suspended=0 WHERE id=$user->id");
        }
        if (!($user = \core_user::get_user($user->id))) {
            $errCnt++;
            $errMsg .= $cnt . ". " . $msg . ob_get_clean() . "\n";
            continue;
        }
        // start the GDPR deletion process
        // need to undelete else Moodle gets the hickups

        //\core\session\manager::init_empty_session();
        //\core\session\manager::set_user($user);
        $manager = new \core_privacy\manager();
        $manager->set_observer(new \tool_dataprivacy\manager_observer());

        $approvedlist = new \core_privacy\local\request\contextlist_collection($user->id);

        //$trace = new text_progress_trace();
        //$contextlists = $manager->get_contexts_for_userid($user->id,$trace);
        $contextlists = $manager->get_contexts_for_userid($user->id, false);

        foreach ($contextlists as $contextlist) {
            @$approvedlist->add_contextlist(new \core_privacy\local\request\approved_contextlist(
                    $user,
                    $contextlist->get_component(),
                    $contextlist->get_contextids()
            ));
        }
        \core\session\manager::kill_user_sessions($user->id); // Do we really need this??
        $manager->delete_data_for_user($approvedlist);
        if (!delete_user($user)) {
            $errCnt++;
            $errMsg .= $cnt . ". " . $msg . ob_get_clean() . "\n";
        } else {
            $DB->execute("UPDATE {user} SET deleted=1,suspended=1, timemodified=" . time() . " WHERE id=$user->id");
            // for ASH handled by script cleanup_ash_enrol.php - removing all users which are deleted + suspended
            if (isset($CFG->ash) and stristr($CFG->dirroot, "moodle_production") and !empty($user->username)) {
                $dbuser = 'moodleuser';
                $dbpass = 'p3NN3';
                $host = 'localhost';
                $enrol_db = 'moodle_ash_enrol';
                $enrol = new PDO("pgsql:host=$host;dbname=$enrol_db", $dbuser, $dbpass);
                // save to archive before deleting
                $query = "INSERT INTO ash_enrolments_old SELECT * FROM ash_enrolments WHERE username='" . $user->username .
                        "' AND username NOT IN(SELECT username FROM ash_enrolments_old);";
                $enrol->query($query);
                $enrol->query("DELETE FROM ash_enrolments WHERE username='$user->username'");
            }
        }
        $DB->execute("UPDATE {user} SET deleted=1,suspended=1 WHERE id=$user->id");
        @ob_get_clean();
        @ob_start();
    }
}
$endtime = time();
$elapsed = $endtime - $started;

// last message
print "\n<br><hr><b>Processing started at " . date("H:i:s", $started) . ", completed at " . date("H:i:s", $endtime)
        . ".</b> Time elapsed : " . (round($elapsed / 60, 0)) . " minutes and " . ($elapsed % 60) . " seconds.<br>\n"
        . ($cnt - $errCnt) . " user accounts " . ($dryrun ? "would be GDPR deleted with these settings" : "were GDPR deleted")
        . ($errCnt ? " ($errCnt errors)" : "") . ".<br>\n";
if ($errCnt) {
    print "Errors: " . $errMsg;
}
print "\n<script>window.scrollTo(0,document.body.scrollHeight);</script>\n";
@ob_flush();@ob_end_flush();@flush();
echo $OUTPUT->footer();

