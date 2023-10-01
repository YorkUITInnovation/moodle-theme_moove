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
 * Theme functions.
 *
 * @package    theme_moove
 * @copyright 2017 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adjust brightness of a hex colou
 *
 * @param $color string Original hex colour
 * @param $percent float Adjustment factor
 * @return mixed|string New hex colour
 */
function adjustBrightness($color, $percent) {

    // Check if the color is in RGB format
    if (preg_match('/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/', $color, $matches)) {
        $red = $matches[1];
        $green = $matches[2];
        $blue = $matches[3];

        // Adjust the brightness
        $red = max(0, min(255, $red + ($red * $percent / 100)));
        $green = max(0, min(255, $green + ($green * $percent / 100)));
        $blue = max(0, min(255, $blue + ($blue * $percent / 100)));

        // Return the adjusted RGB color
        return "rgb($red, $green, $blue)";
    }

    // Check if the color is in hex format
    if (preg_match('/^#?([a-f0-9]{6})$/i', $color, $matches)) {
        $hex = $matches[1];

        // Convert hex to RGB
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        // Adjust the brightness
        $red = max(0, min(255, $red + ($red * $percent / 100)));
        $green = max(0, min(255, $green + ($green * $percent / 100)));
        $blue = max(0, min(255, $blue + ($blue * $percent / 100)));

        // Convert RGB to hex and return the adjusted color
        return '#' . sprintf('%02x', $red) . sprintf('%02x', $green) . sprintf('%02x', $blue);
    }

    // Return the original color if it doesn't match any format
    return $color;
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 * @throws dml_exception
 */
function theme_moove_get_pre_scss($theme)
{
    $primary = $theme->settings->brandcolor;
    $brandcolor = $theme->settings->brandcolor;

    // Config Variables
    $linkedVars = [
        "primary" =>  $primary, // Most buttons
        "brand-primary" => $brandcolor, // Font Coloured
        "secondary-menu-color" => $theme->settings->secondarymenucolor,  // Otherwise
        "brand-primary-2" => adjustBrightness($brandcolor, -10),
        "secondary-menu-color-2" => adjustBrightness($theme->settings->secondarymenucolor, -10),
    ];

    // Use SASS to calculate the values for us, since we cannot reliably
    // account for float point precision / rounding ourselves
    $exposedVars = array_merge($linkedVars, [
        "primary-2" => "lighten($primary, 50%)",
        "primary-3" => "lighten($primary, 40%)",
        "primary-4" => "rgba($primary, .75)"
    ]);

    $scss = theme_moove_create_sass_link_vars($theme, $linkedVars); //
    $scss .= theme_moove_create_sass_expose_vars("sass-var-expose", $exposedVars); // .sass-var-expose

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_moove_get_main_scss_content($theme)
{
    global $CFG, $USER;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();

    // Get Main
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_moove', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    // Moove Theme
    $moove = file_get_contents($CFG->dirroot . '/theme/moove/scss/default.scss');
    $vars = file_get_contents($CFG->dirroot . '/theme/moove/scss/moove/_variables.scss');

    # Combine CSS
    return "\n".join([$vars, $scss, $moove]);
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_moove_get_extra_scss($theme)
{
    $content = '';

    // Sets the login background image.
    $loginbgimgurl = $theme->setting_file_url('loginbgimg', 'loginbgimg');
    if (!empty($loginbgimgurl)) {
        $content .= 'body.pagelayout-login #page { ';
        $content .= "background-image: url('$loginbgimgurl'); background-size: cover;";
        $content .= ' }';
    }

    // Always return the background image with the scss when we have it.
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}


/**
 * Creates a class formatted as {var: value} to expose SASS variable values. This allows them to be
 * REPLACED with CSS vars that we have control over during runtime.
 *
 * @param $className
 * @param $config
 * @return string
 */
function theme_moove_create_sass_expose_vars($className, $config) {
    $scss = ".$className {\n";

    foreach ($config as $key => $value) {
        if (!$value) continue;

        $scss .= ($key . ": ". $value . ";\n");
    }

    return $scss . "}\n";
}

/**
 * Creates a root element with CSS variables based on config variables, linked in with the SASS exposure flow.
 *
 * @param $theme
 * @param $config
 * @return string
 */
function theme_moove_create_sass_link_vars($theme, $config) {
    $scss = ":root {\n";

    foreach ($config as $key => $value) {
        if (!$value) continue;

        $scss .= ("--" . $key . ": ". $value . "!important;\n");
    }

    return $scss . "}\n";
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_moove_get_precompiled_css()
{
    global $CFG;

    return file_get_contents($CFG->dirroot . '/theme/moove/style/moodle.css');
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return mixed
 */
function theme_moove_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array())
{
    $theme = theme_config::load('moove');

    if ($context->contextlevel == CONTEXT_SYSTEM &&
        ($filearea === 'logo' || $filearea === 'loginbgimg' || $filearea == 'favicon')) {
        $theme = theme_config::load('moove');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    if ($context->contextlevel == CONTEXT_SYSTEM && preg_match("/^sliderimage[1-9][0-9]?$/", $filearea) !== false) {
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'marketing1icon') {
        return $theme->setting_file_serve('marketing1icon', $args, $forcedownload, $options);
    }

    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'marketing2icon') {
        return $theme->setting_file_serve('marketing2icon', $args, $forcedownload, $options);
    }

    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'marketing3icon') {
        return $theme->setting_file_serve('marketing3icon', $args, $forcedownload, $options);
    }

    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'marketing4icon') {
        return $theme->setting_file_serve('marketing4icon', $args, $forcedownload, $options);
    }

    send_file_not_found();
}

function theme_moove_get_teachers()
{
    global $CFG, $DB, $PAGE, $OUTPUT;
    require_once($CFG->libdir . '/accesslib.php');
    require_once($CFG->libdir . '/filelib.php');

    $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
    $users = get_role_users($role->id, $PAGE->context);

    $teachers = [];
    $i = 0;
    foreach ($users as $u) {
        $user = $DB->get_record('user', ['id' => $u->id]);
        $user_picture = new user_picture($user);
        $moodle_url = $user_picture->get_url($PAGE);

        $teachers[$i] = new stdClass();
        $teachers[$i]->fullname = fullname($u);
        if ($user->maildisplay > 0){
            $teachers[$i]->email = $u->email;
        }
        $teachers[$i]->image = $moodle_url->out();
        $teachers[$i]->id = $u->id;
        $i++;
    }
    return $teachers;
}

function get_current_course_mods()
{
    global $CFG, $DB, $COURSE;

    $course_id = $COURSE->id;

    $modinfo = get_fast_modinfo($COURSE);
    $modfullnames = array();

    $archetypes = array();

    foreach ($modinfo->cms as $cm) {
        // Exclude activities that aren't visible or have no view link (e.g. label). Account for folder being displayed inline.
        if (!$cm->uservisible || (!$cm->has_view() && strcmp($cm->modname, 'folder') !== 0)) {
            continue;
        }
        if (array_key_exists($cm->modname, $modfullnames)) {
            continue;
        }
        if (!array_key_exists($cm->modname, $archetypes)) {
            $archetypes[$cm->modname] = plugin_supports('mod', $cm->modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
        }
        if ($archetypes[$cm->modname] == MOD_ARCHETYPE_RESOURCE) {
            if (!array_key_exists('resources', $modfullnames)) {
                $modfullnames['resources'] = get_string('resources');
            }
        } else {
            $modfullnames[$cm->modname] = $cm->modplural;
        }
    }

    core_collator::asort($modfullnames);
    $mod_names = [];
    $i = 0;
    foreach ($modfullnames as $modname => $modfullname) {
        if ($modname === 'resources') {
            $mod_names[$i]['name'] = $modname;
            $mod_names[$i]['fullname'] = $modfullname;
            $mod_names[$i]['courseid'] = $course_id;
            $mod_names[$i]['url'] = $CFG->wwwroot . '/course/resources.php?id=' . $course_id;
        } else {
            $mod_names[$i]['name'] = $modname;
            $mod_names[$i]['fullname'] = $modfullname;
            $mod_names[$i]['courseid'] = $course_id;
            $mod_names[$i]['url'] = $CFG->wwwroot . '/mod/' . $modname . '/index.php?id=' . $course_id;
        }
        $i++;
    }

    return $mod_names;
}

/**
 * Build secondary menu
 * @param $items array Taken from $secondary->get_tabs_array()
 * @return stdClass
 */
function theme_moove_build_secondary_menu($items)
{
    global $CFG, $COURSE;

    // Put tab items into one variable
    $tabs = $items[0][0];

    // Menus is the obkect that will be returned
    $menus = new stdClass();
    // Build primary menu
    $menu = [];
    $i = 0;
    for ($a = 0; $a < 5; $a++) {
        // Do not add course home because it is added on all pages.
        if (isset($tabs[$a]->id)) {
            $menu[$i]['id'] = $tabs[$a]->id;
            $menu[$i]['name'] = $tabs[$a]->title;
            $link = '';
            if (isset($tabs[$a]->link)) {
                if ($tabs[$a]->link instanceof \moodle_url) {
                    $link = $tabs[$a]->link->out();
                } elseif (isset($tabs[$a]->link->url) && ($tabs[$a]->link->url instanceof \moodle_url)) {
                    $link = $tabs[$a]->link->url->out();
                }
            } else {
                $link = '';
            }
            $menu[$i]['url'] = str_replace('&amp;', '&', $link);
            $menu[$i]['format'] = $COURSE->format;
            $menu[$i]['icon'] = theme_moove_get_menu_icon($tabs[$a]->id);
            $i++;
        }
    }
    // Loop through menu. If item 0 does not equal course home, add course home to the beginning of the menu.
    if ($menu[0]['id'] != 'coursehome') {
        $course_home = new stdClass();
        $course_home->id = 'coursehome';
        $course_home->name = get_string('course');
        $course_home->url = $CFG->wwwroot . '/course/view.php?id=' . $COURSE->id;
        $course_home->format = $COURSE->format;
        $course_home->icon = 'fa fa-bookmark';
        array_unshift($menu, $course_home);
    }

    // Build more menu
    $more_menu = [];
    $m = 0;
    // Start at 5 because more menu begins at element 5
    for ($b = 5; $b < count($tabs); $b++) {
        $more_menu[$m]['id'] = $tabs[$b]->id;
        $more_menu[$m]['name'] = $tabs[$b]->title;
        $link = '';
        if (isset($tabs[$b]->link)) {
            if ($tabs[$b]->link instanceof \moodle_url) {
                $link = $tabs[$b]->link->out();
            } elseif (isset($tabs[$b]->link->url) && ($tabs[$b]->link->url instanceof \moodle_url)) {
                $link = $tabs[$b]->link->url->out();
            }
        } else {
            $link = '';
        }
        $more_menu[$m]['url'] = str_replace('&amp;', '&', $link);;
        $m++;
    }
    // Add both arrays into menus object
    $menus->menu = $menu;
    $menus->more = $more_menu;

    return $menus;
}

function theme_moove_get_menu_icon($type)
{
    $icon = 'fa fa-circle-o';
    switch ($type) {
        case 'coursehome':
            $icon = 'fa fa-bookmark';
            break;
        case 'editsettings':
        case 'modedit':
            $icon = 'fa fa-sliders';
            break;
        case 'participants':
            $icon = 'fa fa-users';
            break;
        case 'grades':
        case 'advgrading':
            $icon = 'fa fa-font';
            break;
        case 'coursereports':
            $icon = 'fa fa-bar-chart';
            break;
        case 'filtermanage':
            $icon = 'fa fa-filter';
            break;
        case 'roleoverride':
        case 'mod_assign_useroverrides':
            $icon = 'fa fa-check-square-o';
            break;
        case 'backup':
            $icon = 'fa fa-download';
            break;
        case 'competencies':
            $icon = 'fa fa-lightbulb-o';
            break;
        default:
            $icon = 'fa fa-circle-o';
            break;
    }

    return $icon;
}
