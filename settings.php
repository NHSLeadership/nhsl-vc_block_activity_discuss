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
 *
 * Settings for Block course discuss.
 *
 * @package   block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configcheckbox('block_activity_discuss/displayerrornoforumexists',
        get_string('displayerrornoforumexists', 'block_activity_discuss'),
        get_string('displayerrornoforumexistsdesc', 'block_activity_discuss'), 0));

    $settings->add(new admin_setting_configtext('block_activity_discuss/displayerrornoforumexistsmessage',
        get_string('displayerrornoforumexistsmessage', 'block_activity_discuss'), get_string('displayerrornoforumexistsmessagedesc',
                   'block_activity_discuss'), '', PARAM_TEXT));

    // Heading for adding space between settings.
    $settings->add(new admin_setting_heading('temp1', '', "<br>"));

    $settings->add(new admin_setting_configtext('block_activity_discuss/forumnamepattern',
        get_string('forumnamepattern', 'block_activity_discuss'), get_string('forumnamepatterndesc', 'block_activity_discuss'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('block_activity_discuss/useridforposts',
                   get_string('useridforposts', 'block_activity_discuss'), get_string('useridforpostsdesc', 'block_activity_discuss'),
                   '', PARAM_TEXT));

    $settings->add(new admin_setting_configcheckbox('block_activity_discuss/initialpostlinktopage',
           get_string('initialpostlinktopage', 'block_activity_discuss'),
           get_string('initialpostlinktopagedesc', 'block_activity_discuss'), 0));

    $settings->add(new admin_setting_configtext('block_activity_discuss/blockheadertitletagline',
            get_string('blockheadertitletagline', 'block_activity_discuss'), get_string('blockheadertitletaglinedesc',
                    'block_activity_discuss'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configcheckbox('block_activity_discuss/allowedit',
            get_string('allowedit', 'block_activity_discuss'),
            get_string('alloweditdesc', 'block_activity_discuss'), 0));

    $settings->add(new admin_setting_configcheckbox('block_activity_discuss/showviewthread',
            get_string('showviewthread', 'block_activity_discuss'),
            get_string('showviewthreaddesc', 'block_activity_discuss'), 0));

}