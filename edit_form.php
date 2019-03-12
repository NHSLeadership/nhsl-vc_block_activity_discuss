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
 * Form for editing specific activity_discuss instances.
 *
 * @package   block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Course Discuss edit form implementation class.
 *
 * @package block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_activity_discuss_edit_form extends block_edit_form {

    /**
     * Override specific definition to provide Course Discuss instance settings.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG;

    }
}
