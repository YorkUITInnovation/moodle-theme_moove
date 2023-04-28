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
 * A drawer based layout for the moove theme.
 *
 * @package    theme_moove
 * @copyright  2022 Willian Mano {@link https://conecti.me}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $USER, $OUTPUT, $SITE, $PAGE;

// Get the profile userid.
$courseid = optional_param('course', 1, PARAM_INT);
$userid = optional_param('id', $USER->id, PARAM_INT);
$userid = $userid ?: $USER->id;

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$userimg = new \user_picture($user);
$userimg->size = 100;

$context = context_course::instance(SITEID);

$extraclasses = [];
$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $secondary = $PAGE->secondarynav;

    if ($secondary->get_children_key_list()) {
        $tablistnav = $PAGE->has_tablist_secondary_navigation();
        $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
        $secondarynavigation = $moremenu->export_for_template($OUTPUT);
        $extraclasses[] = 'has-secondarynavigation';
    }

    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

// Prepare description file area
$user_context = \context_user::instance($user->id);
$text = $user->description;
$file = 'pluginfile.php';
$filearea = 'profile';
$component = 'user';
$itemid = 0;

$description = file_rewrite_pluginfile_urls($text, $file, $user_context->id, $component, $filearea, $itemid);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'userpicture' => $userimg->get_url($PAGE),
    'userfullname' => fullname($user),
    'headerbuttons' => \theme_moove\util\extras::get_mypublic_headerbuttons($context, $user),
    'editprofileurl' => \theme_moove\util\extras::get_mypublic_editprofile_url($user, $courseid),
    'userdescription' => $description
];

$themesettings = new \theme_moove\util\settings();

$templatecontext = array_merge($templatecontext, $themesettings->footer());

echo $OUTPUT->render_from_template('theme_moove/mypublic', $templatecontext);
