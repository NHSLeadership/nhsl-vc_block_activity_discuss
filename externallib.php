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
 * External API.
 *
 * This is used by the block_activity_discuss plugin itself in javascript to perform various functions such as
 * creating new posts and discussions for a relevant page (such as section, page, book).
 *
 * @package    block_activity_discuss
 * @copyright  2018 Manoj Solanki (Coventry University)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/blocks/activity_discuss/lib.php");

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

/**
 * External API class.
 *
 * @package    block_activity_discuss
 * @copyright  2018 Manoj Solanki (Coventry University)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class block_activity_discuss_external extends external_api {

    /**
     * Returns description of create_discussion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function create_discussion_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, ''),
                'forumid' => new external_value(PARAM_INT, 'Forum ID', VALUE_DEFAULT, ''),
                'contextid' => new external_value(PARAM_INT, 'Context ID', VALUE_DEFAULT, ''),
                'message' => new external_value(PARAM_TEXT, 'Message', VALUE_DEFAULT, ''),
                'pageinternalid' => new external_value(PARAM_INT, 'Page internal id (e.g. section id, page id)', VALUE_DEFAULT, ''),
                'pagename' => new external_value(PARAM_TEXT, 'Page name, e.g. "section", "page"', VALUE_DEFAULT, '')
            )
            );
    }

    /**
     * Returns description of create_discussion_returns() result value.
     *
     * @return \external_description
     */
    public static function create_discussion_returns() {

        return new external_single_structure(
            array(
                'eventaction'                     => new external_value(PARAM_TEXT, 'Event action status'),
                'discussionid'                    => new external_value(PARAM_INT, 'Discussion id'),
                'subject'                         => new external_value(PARAM_TEXT, 'Subject'),
                'warning'                         => new external_value(PARAM_RAW, 'Any warning messages'),
                'errormessage'                    => new external_value(PARAM_RAW, 'Any error messages')
            )
        );

    }

    /**
     * Create discussion.
     *
     * @param string $courseid
     * @param int    $forumid
     * @param int    $contextid
     * @param string $message
     * @param int    $pageinternalid (The internal ID reference, which is section ID, page cm ID or Book chapter ID)
     * @param string $pagename   "section", "book" or "page"
     * @param string $organisation
     * @return mixed
     */
    public static function create_discussion($courseid = '', $forumid = '', $contextid, $message, $pageinternalid, $pagename,
        $organisation = '') {

        global $PAGE, $DB, $OUTPUT;

        require_once(dirname(dirname(__DIR__)).'/config.php');

        // Clean parameters.
        $params = self::validate_parameters(self::create_discussion_parameters(), array(
            'courseid' => $courseid, 'forumid' => $forumid, 'contextid' => $contextid,
            'message' => $message, 'pageinternalid' => $pageinternalid, 'pagename' => $pagename
        ));

        // Get context and cm.
        list($context, $course, $cm) = get_context_info_array($contextid);

        // This isn't expected but cater for it.
        if (empty($cm)) {
            return array(
                'eventaction'      => 'discussionnotcreated',
                'discussionid'     => 0,
                'subject'          => 'N/A',
                'warning'          => '',
                'errormessage'     => 'Failed to find course module record with contextid of ' . $contextid
            );

        }
        $foruminstance = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);

        require_login($course, true, $cm);

        $discussiontitle = '';

        // Use this to store a link to section, page or book for use in the initial message (Issue #30 on BB).
        $pagelink = '#';

        $config = get_config('block_activity_discuss');

        // Get the relevant section, page or book chapter name to build the discussion title.
        if ($pagename == 'section') {

            if (!empty($config->initialpostlinktopage)) {
                $pagelink = new moodle_url('/course/view.php', array('id' => $courseid, 'sectionid' => $pageinternalid));
            }

            // Get the relevant section name.
            $record = $DB->get_record('course_sections', array(
                'course' => $courseid,
                'id' => $pageinternalid), '*');

            if (empty($record->name) || ($record->name == null)) {
                $discussiontitle = 'Section ' . $record->section;
            } else {
                $discussiontitle = $record->name;
            }
        } else if ($pagename == 'book') {

            // Get the relevant chapter and book name.
            $chapterrecord = $DB->get_record('book_chapters', array('id' => $pageinternalid));

            if ($chapterrecord) {
                $bookrecord = $DB->get_record('book', array('id' => $chapterrecord->bookid));
                if ($bookrecord) {
                    $discussiontitle = $bookrecord->name . ' - ' . $chapterrecord->title;

                    if (!empty($config->initialpostlinktopage)) {
                        // Add page link.  This can only be added when there is a book id and chapter id.
                        $pagelink = new moodle_url('/mod/book/view.php', array('b' => $bookrecord->id,
                                                   'chapterid' => $pageinternalid));
                    }
                } else {
                    $discussiontitle = $chapterrecord->title;
                }

            } else {
                $discussiontitle .= 'Chapter id ' . $pageinternalid;
            }

        } else if ($pagename == 'page') {

            if (!empty($config->initialpostlinktopage)) {
                $pagelink = new moodle_url('/mod/page/view.php', array('id' => $pageinternalid));
            }

            // Get the relevant page name.
            $modinfo = get_fast_modinfo($courseid);

            foreach ($modinfo->cms as $mod) {
                if ( ($mod->modname == 'page') &&
                    ($mod->id == $pageinternalid) ) {
                    $discussiontitle = $mod->name;
                }
            }

            if (empty ($discussiontitle)) {
                $discussiontitle = 'Page id ' . $pageinternalid;
            }

        }

        $options = array(
            'subject'       => 'Discuss ' . $discussiontitle,
            'message'       => 'Discuss ' . ($pagelink != '#' ? html_writer::link($pagelink, $discussiontitle) : $discussiontitle)
                               . ' here.'

        );

        // Create a discussion.
        $result = block_activity_discuss_add_discussion($foruminstance, $options['subject'], $options['message'], $cm, $course);

        if ($result['discussionid'] > 0) {

            // Discussion was added successfully, so we can a link to this discussion for this specific page.
            $blockcoursediscusslookupid = block_activity_discuss_link_discussion_to_pageinternalid($course->id, $pageinternalid,
                $pagename, $foruminstance->id, (int) $result['discussionid']);

        } else {
            return array(
                'eventaction'      => 'discussionnotcreated',
                'discussionid'     => 0,
                'subject'          => $options['subject'],
                'warning'          => '',
                'errormessage'     => $result['error']
            );
        }

        $notification = '';
        if (!$blockcoursediscusslookupid) {
            $notification .= 'Warning, link not created in block_activity_discuss table for discussion id '
                             . $result['discussionid'];
        }

        return array(
            'eventaction'      => 'discussioncreated',
            'discussionid'     => $result['discussionid'],
            'subject'          => $options['subject'],
            'warning'          => $notification,
            'errormessage'     => ''

        );

    }

    /**
     * Returns description of create_post() parameters.
     *
     * @return \external_function_parameters
     */
    public static function create_post_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course ID', VALUE_DEFAULT, ''),
                'forumid' => new external_value(PARAM_INT, 'Forum ID', VALUE_DEFAULT, ''),
                'contextid' => new external_value(PARAM_INT, 'Context ID', VALUE_DEFAULT, ''),
                'message' => new external_value(PARAM_TEXT, 'Message', VALUE_DEFAULT, ''),
                'discussionid' => new external_value(PARAM_INT, 'Discussion id', VALUE_DEFAULT, ''),
                'subject' => new external_value(PARAM_TEXT, 'Subject', VALUE_DEFAULT, ''),
                'parentpostid' => new external_value(PARAM_INT, 'Parent post id', VALUE_DEFAULT, 0),
            )
            );
    }

    /**
     * Create post for a discussion.
     *
     * @param string $courseid
     * @param string $forumid
     * @param int    $contextid
     * @param string $message
     * @param int    $discussionid
     * @param string $subject
     * @param int    $parentpostid
     * @param string $component
     * @return array
     */
    public static function create_post($courseid = '', $forumid = '', $contextid = 0,
        $message = '', $discussionid = 0, $subject = '', $parentpostid = 0, $component = 'block_activity_discuss') {

        global $PAGE, $DB;

        require_once(dirname(dirname(__DIR__)).'/config.php');

        // Clean parameters.
        $params = self::validate_parameters(self::create_post_parameters(), array(
            'courseid' => $courseid, 'forumid' => $forumid, 'contextid' => $contextid,
            'message' => $message, 'discussionid' => $discussionid,
            'subject' => $subject, 'parentpostid' => 0
        ));

        // Get context and cm.
        list($context, $course, $cm) = get_context_info_array($contextid);

        if (empty($cm)) {
            throw new coding_exception("Failed to find course module record with contextid of $contextid");
        }
        $foruminstance = $DB->get_record('forum', array('id' => $cm->instance), '*', MUST_EXIST);

        require_login($course, true, $cm);

        if ($parentpostid == 0) {
            $parent = $DB->get_record('forum_posts', array('discussion' => $discussionid, 'parent' => $parentpostid), '*',
                                      MUST_EXIST);
        } else {
            $parent = $DB->get_record('forum_posts', array('discussion' => $discussionid, 'id' => $parentpostid), '*',
                                      MUST_EXIST);
        }
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid, 'forum' => $forumid), '*',
                                      MUST_EXIST);

        $result = block_activity_discuss_add_discussion_post($parent->id, $subject, $message, $forum, $discussion);

        if ($result['postid'] > 0) {
            return array(
                'eventaction'      => 'postcreated',
                'postid'           => $result['postid'],
                'warning'          => $result['warnings'],
                'errormessage'     => ''
            );
        } else {
            return array(
                'eventaction'      => 'postnotcreated',
                'postid'           => $result['postid'],
                'warning'          => '',
                'errormessage'     => $result['error']
            );
        }
    }

    /**
     * Returns description of create_post_returns() result value.
     *
     * @return \external_description
     */
    public static function create_post_returns() {

        return new external_single_structure(
            array(
                 'eventaction'                     => new external_value(PARAM_TEXT, 'Event action status'),
                 'postid'                          => new external_value(PARAM_RAW, 'Post id'),
                 'warning'                         => new external_value(PARAM_RAW, 'Any warning messages'),
                 'errormessage'                    => new external_value(PARAM_RAW, 'Any error messages')
            )
            );
    }

    /**
     * Returns description of display_discussion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function display_discussion_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, ''),
                'forumid' => new external_value(PARAM_INT, 'Forum ID', VALUE_DEFAULT, ''),
                'discussionid' => new external_value(PARAM_INT, 'Discussion ID', VALUE_DEFAULT, ''),
                'allowreply' => new external_value(PARAM_INT, 'Allow reply. 0 for false, or any other value for true',
                                                   VALUE_DEFAULT, 0),

            )
            );
    }

    /**
     * Display a discussion.
     *
     * @param string $courseid
     * @param string $forumid
     * @param int    $discussionid
     * @param bool   $allowreply
     * @param string $component
     * @return mixed
     */
    public static function display_discussion($courseid, $forumid, $discussionid,
                                              $allowreply = false, $component = 'block_activity_discuss') {

        global $PAGE, $DB;

        require_once(dirname(dirname(__DIR__)).'/config.php');
        $PAGE->requires->js_call_amd('block_activity_discuss/activity_discuss', 'init');

        $params = self::validate_parameters(self::display_discussion_parameters(), array(
            'courseid' => $courseid, 'forumid' => $forumid,
            'discussionid' => $discussionid
        ));

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $forum = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forumid, $courseid, false, MUST_EXIST);

        require_login($course, true, $cm);

        // Call library function that gets the discussion in HTML format.
        $content = block_activity_discuss_display_discussion($cm, $course, $discussion, $forum, 0, 0, $allowreply);

        return array(
            'eventaction'      => 'discussionreturned',
            'html'             => $content,
            'warning'          => '',
            'errormessage'     => ''
        );

    }

    /**
     * Returns description of display_discussion_returns() result value.
     *
     * @return \external_description
     */
    public static function display_discussion_returns() {

        return new external_single_structure(
            array(
                'eventaction'                     => new external_value(PARAM_TEXT, 'Event action status'),
                'warning'                         => new external_value(PARAM_RAW, 'Any warning messages'),
                'html'                            => new external_value(PARAM_RAW, 'Returned HTML format new content'),
                'errormessage'                    => new external_value(PARAM_RAW, 'Any error messages')
            )
            );

    }

    /**
     * Returns description of display_reply_form() parameters.
     *
     * @return \external_function_parameters
     */
    public static function display_reply_form_parameters() {

        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, ''),
                'forumid' => new external_value(PARAM_INT, 'Forum ID', VALUE_DEFAULT, ''),
                'pageinternalid' => new external_value(PARAM_INT, 'Page internal id, e.g. sectionnum id, page id',
                                                       VALUE_DEFAULT, ''),
                'pagename' => new external_value(PARAM_TEXT, 'Page name, e.g. "section", "page"', VALUE_DEFAULT, ''),
                'discussionid' => new external_value(PARAM_INT, 'Discussion ID', VALUE_DEFAULT, ''),
                'parentpostid' => new external_value(PARAM_INT, 'Parent post ID', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Display a reply comment form for a specific post.
     *
     * @param int     $courseid
     * @param int     $forumid
     * @param int     $pageinternalid
     * @param string  $pagename
     * @param int     $discussionid
     * @param int     $parentpostid
     * @param string  $component
     * @return mixed
     */
    public static function display_reply_form($courseid, $forumid, $pageinternalid, $pagename, $discussionid,
                                              $parentpostid, $component = 'block_activity_discuss') {

        global $PAGE, $DB;

        require_once(dirname(dirname(__DIR__)).'/config.php');
        $PAGE->requires->js_call_amd('block_activity_discuss/activity_discuss', 'init');

        // Clean parameters.
        $params = self::validate_parameters(self::display_reply_form_parameters(), array(
           'courseid' => $courseid, 'forumid' => $forumid,
            'pageinternalid' => $pageinternalid, 'pagename' => $pagename, 'discussionid' => $discussionid,
            'parentpostid' => $parentpostid
        ));

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $forum = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forumid, $courseid, false, MUST_EXIST);

        require_login($course, true, $cm);

        $content = block_activity_discuss_display_new_comment_form($cm, $forumid, $pageinternalid, $pagename,
                                                                  'course-discuss-create-reply', $discussionid, $parentpostid);

        return array(
            'eventaction'      => 'formreturned',
            'html'             => $content,
            'warning'          => '',
            'errormessage'     => ''
        );

    }

    /**
     * Returns description of display_reply_form_returns() result value.
     *
     * @return \external_description
     */
    public static function display_reply_form_returns() {
        return new external_single_structure(
            array(
                'eventaction'                     => new external_value(PARAM_TEXT, 'Event action status'),
                'html'                            => new external_value(PARAM_RAW, 'Returned html'),
                'warning'                         => new external_value(PARAM_RAW, 'Any warning messages'),
                'errormessage'                    => new external_value(PARAM_RAW, 'Any error messages')
            )
            );
    }

}
