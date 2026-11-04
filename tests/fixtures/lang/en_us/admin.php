<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'admin', language 'en_us', version '5.2'.
 *
 * @package     admin
 * @category    string
 * @copyright   1999 Martin Dougiamas and contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allowframembedding_help'] = 'If enabled, this site may be embedded in a frame in a remote system, as recommended when using the \'Publish as LTI tool\' enrollment plugin. Otherwise, it is recommended to leave frame embedding disabled for security reasons. Please note that for the mobile app this setting is ignored and frame embedding is always allowed.';
$string['backgroundcolour'] = 'Transparent color';
$string['cliunknowoption'] = 'Unrecognized options:
  {$a}
Please use --help option.';
$string['composernotoptimised'] = 'Moodle is running in production mode whilst the Composer autoloader is not optimized. You may wish to run "composer install --no-dev --classmap-authoritative" to optimize the autoloader.';
$string['composeroptimisedindevmode'] = 'The Composer autoloader is currently running optimized whilst Moodle is in developer mode. This can cause issues in some cases. You may wish to run "composer install" without additional arguments.';
$string['configprofilesforenrolledusersonly'] = 'To prevent misuse by spammers, profile descriptions of users who are not yet enrolled in any course are hidden. New users must enroll in at least one course before they can add a profile description.';
$string['configsessioncookie'] = 'This setting customizes the name of the cookie used for Moodle sessions. This is optional, and only useful to avoid cookies being confused when there is more than one copy of Moodle running within the same web site.';
$string['configsessioncookiedomain'] = 'This allows you to change the domain that the Moodle cookies are available from. This is useful for Moodle customizations (e.g. authentication or enrollment plugins) that need to share Moodle session information with a web application on another subdomain. <strong>WARNING: it is strongly recommended to leave this setting at the default (empty) - an incorrect value will prevent all logins to the site.</strong>';
$string['configsitedefaultlicense'] = 'Default site license';
$string['configsitedefaultlicensehelp'] = 'The default license for publishing content on this site';
$string['configstatsuserthreshold'] = 'Show course categories in the navigation bar and navigation blocks. This does not occur with courses the user is currently enrolled in, they will still be listed under mycourses without categories.
';
$string['configyuicomboloading'] = 'This option enables combined file loading optimization for YUI libraries. It should be enabled for performance reasons.';
$string['coursecolor'] = 'Color {$a}';
$string['coursecolorheading_desc'] = 'Any courses without a course image set in the course settings are displayed on the My courses page with a patterned course card. The colors used in the pattern may be specified below.';
$string['coursecolorsettings'] = 'Course card colors';
$string['courselistshortnames_desc'] = 'If enabled, course short names will be displayed in addition to full names in course lists. If required, extended course names may be customised by editing the \'courseextendednamedisplay\' language string using the language customization feature.';
$string['creatornewroleid_help'] = '';
$string['datarootsecurityerror'] = '<p><strong>SECURITY WARNING!</strong></p><p>Your dataroot directory is in the wrong location and is exposed to the web. This means that all your private files are available to anyone in the world, and some of them could be used by a cracker to obtain unauthorized administrative access to your site!</p>
<p>You <em>must</em> move dataroot directory ({$a}) to a new location that is not within your public web directory, and update the <code>$CFG->dataroot</code> setting in your config.php accordingly.</p>';
$string['editorbackgroundcolor'] = 'Background color';
$string['enabledashboard_help'] = 'The Dashboard shows Timeline, Calendar and Recently accessed items by default. You can set a different default Dashboard for everyone and allow users to customize their own Dashboard. If disabled, you need to set \'Start page for users\' to a value other than Dashboard.';
$string['enableglobalsearch_desc'] = 'If enabled, data will be indexed and synchronized by a scheduled task.';
$string['enroladminnewcourse'] = 'Auto-enroll admin in new courses';
$string['enrolinstancedefaults'] = 'Enrollment instance defaults';
$string['enrolinstancedefaults_desc'] = 'Default enrollment settings in new courses.';
$string['enrolmultipleusers'] = 'Enroll the users';
$string['groupenrolmentkeypolicy'] = 'Group enrollment key policy';
$string['groupenrolmentkeypolicy_desc'] = 'If enabled, group enrollment keys will be checked against the password policy as specified in the settings above.';
$string['guestroleid_help'] = 'This role is automatically assigned to the guest user. It is also temporarily assigned to not enrolled users that enter the course via guest enrollment plugin.';
$string['helpweekenddays'] = 'Which days of the week are treated as "weekend" and shown with a different color?';
$string['licensesettings'] = 'License settings';
$string['manageqbehaviours'] = 'Manage question behaviors';
$string['profilesforenrolledusersonly'] = 'Profiles for enrolled users only
';
$string['questionbehaviours'] = 'Question behaviors';
$string['rememberuserlicensepref'] = 'Remember user license preference';
$string['rememberuserlicensepref_help'] = 'If enabled, the last license selected by the user is preselected when uploading a file in the file picker. Otherwise, the default site license is preselected.';
$string['restorernewroleid_help'] = 'If the user does not already have the permission to manage the newly restored course, the user is automatically assigned this role and enrolled if necessary. Select "None" if you do not want restorers to be able to manage every restored course.';
$string['riskconfig'] = 'Users could change site configuration and behavior';
$string['updatenotificationfooter'] = 'Your Moodle site {$a->siteurl} is configured to automatically check for available updates. You are receiving this message as the administrator of the site. You can disable automatic checks for available updates in Site administration / Server / Update notifications or customize the delivery of this message via your preferences page.';
