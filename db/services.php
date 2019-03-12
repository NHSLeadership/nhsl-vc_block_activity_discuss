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
 * Defines the web service calls utilised by the block to perform the required functions.
 *
 * @package    block_activity_discuss
 * @copyright  2018 Coventry University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Manoj Solanki (Coventry University)
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'block_activity_discuss_create_discussion' => array(
        'classname'   => 'block_activity_discuss_external',
        'methodname'  => 'create_discussion',
        'classpath'   => 'blocks/activity_discuss/externallib.php',
        'description' => 'Creates a discussion for a page in a forum.',
        'type'        => 'read',
        'ajax'          => true,
        'loginrequired' => true
    ),
    'block_activity_discuss_create_post' => array(
        'classname'   => 'block_activity_discuss_external',
        'methodname'  => 'create_post',
        'classpath'   => 'blocks/activity_discuss/externallib.php',
        'description' => 'Post a reply for a discussion.',
        'type'        => 'read',
        'ajax'          => true,
        'loginrequired' => true
    ),
    'block_activity_discuss_display_discussion' => array(
        'classname'   => 'block_activity_discuss_external',
        'methodname'  => 'display_discussion',
        'classpath'   => 'blocks/activity_discuss/externallib.php',
        'description' => 'Display a discussion.',
        'type'        => 'read',
        'ajax'          => true,
        'loginrequired' => true
    ),
    'block_activity_discuss_display_reply_form' => array(
        'classname'   => 'block_activity_discuss_external',
        'methodname'  => 'display_reply_form',
        'classpath'   => 'blocks/activity_discuss/externallib.php',
        'description' => 'Display a reply form for a post.',
        'type'        => 'read',
        'ajax'          => true,
        'loginrequired' => true
    )
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
    'Activity Discuss Block' => array(
        'functions' => array ('block_activity_discuss_create_discussion', 'block_activity_discuss_create_post',
            'block_activity_discuss_display_discussion', 'block_activity_discuss_display_reply_form'),
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);
