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
 * Version details
 *
 * @package   block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * Based on block_course_discuss plugin version 1.5 release 2019021404 and extended to include scorm packages and to exclude non-activity pages
 *
 */

defined('MOODLE_INTERNAL') || die();

// Recommended since 2.0.2 (MDL-26035). Required since 3.0 (MDL-48494).
$plugin->component = 'block_activity_discuss';

// YYYYMMDDHH (year, month, day, 24-hr time).
$plugin->version = 2019032801;

// YYYYMMDDHH (This is the release version for Moodle 3.6.2).
$plugin->requires = 2018120302.08;

$plugin->maturity = MATURITY_STABLE;
$plugin->release = "1";
