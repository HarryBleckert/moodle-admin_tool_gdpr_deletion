/*
* @package    tool_gdpr_deletion
* @copyright  2021 onwards Harry.Bleckert@ash-berlin.eu for ASH Berlin
* @author     Harry.Bleckert@ash-berlin.eu

  script to GDPR - delete range of users with no login for more than defined days OR user account selected by user->id
    - defaults to special filtering when run with ASH Moodle: $ufilter .= " AND auth='ash_authsrv' AND deleted=1"
    - this was written fast and dirty but with sufficient testing
    - Script utilizes Moodle Core GDPR and privacy classes
    - configured to be used by Moodle Site Amins only

*
*/


Installation:
copy this plugin folder gdpr_deletion to admin/tool
