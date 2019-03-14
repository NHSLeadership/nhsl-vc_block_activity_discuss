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
 * Moodle Course Discuss block.
 *
 * Allows discussion for various types of course page.
 * These are currently sections, pages and book chapters. The main tasks are described below.
 *
 * - Finds a forum of type "general" to be utilised for discussions when the block is first rendered. This is stored
 *   using the table block_activity_discuss to link the forum id with a course.
 * - Displays a form allowing a user to post and reply to posts.
 * - Creates a new discussion when a user first creates a post on a page.  A row is created in the
 *   table block_activity_discus_lookup. It uses the columns pageinternalid, pagename and discussionid
 *   in block_activity_discus_lookup to keep a link between a discussion and a page.
 *   and a page. The pageinternalid is either the section id (from course_sections), chapter id (from book_chapters) and
 *   page id (from course_modules).
 * - If a discussion is deleted in the forum manually, the block will re-use the existing link in block_activity_discus_lookup
 *  and overwrite it with the new discussion id.
 * - If a forum is deleted, it will remove the link in block_course_discus for it and try to find another forum to use.
 *
 *
 * @package block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/externallib.php');

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Course status block implementation class.
 *
 * @package   block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_activity_discuss extends block_base {

    /** @var array The types of pages the block is allowed to display on */
    public static $allowedpages = array ('section', 'page', 'book', 'scorm', 'assign', 'lesson', 'workshop', 'folder', 'resource');

    /**
     * Adds title to block instance.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_activity_discuss');
    }

    /**
     * Set up any configuration data.
     *
     * The is called immediatly after init().
     */
    public function specialization() {
        $config = get_config("block_activity_discuss");

        // Use the title as defined in plugin settings, if one exists.
        if (!empty($config->title)) {
            $this->title = $config->title;
        } else {
            $this->title = 'Course Discuss';
        }
    }

    /**
     * Gets Javascript that may be required for navigation.
     */
    public function get_required_javascript() {
        parent::get_required_javascript();

        // Get language strings that will be used for error messages.
        $requiredparams = array ('charactersremainingtext' => get_string('charactersremainingtext', 'block_activity_discuss'),
                             'pleaseaddmessagetext' => get_string('pleaseaddmessagetext', 'block_activity_discuss'),
                             'postcouldnotbeaddedtext' => get_string('postcouldnotbeaddedtext', 'block_activity_discuss'),
                             'warninginternalerrortext' => get_string('warninginternalerrortext', 'block_activity_discuss'),
                             'warningtitletext' => get_string('warningtitletext', 'block_activity_discuss'),
                             'thankyouforpostingtext' => get_string('thankyouforpostingtext', 'block_activity_discuss'),
                             'errortitletext' => get_string('errortitletext', 'block_activity_discuss'),
                             'errordisplayreplyformtext' => get_string('errordisplayreplyformtext', 'block_activity_discuss'),
                             'discussioncreatedinternalerrortext' => get_string('discussioncreatedinternalerrortext',
                                                                                'block_activity_discuss'),
                             'errordisplayingdiscussiontext' => get_string('errordisplayingdiscussiontext', 'block_activity_discuss')
        );

        $this->page->requires->js_call_amd('block_activity_discuss/activity_discuss', 'init', array ($requiredparams));
    }

    /**
     * Which page types this block may appear on.  This currently is set to allow it to display
     * in any course page and course format.
     */
    public function applicable_formats() {
        return array('site-index' => true, 'course-view-*' => true);
    }

    /**
     * Get block instance content.
     */
    public function get_content() {
        global $CFG, $COURSE, $USER, $OUTPUT, $PAGE, $ME, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        // Find a relevant ID (e.g. section ID, page ID etc).  Otherwise
        // do not display this page.
        $pagedetails = block_activity_discuss_get_current_page_details();
        if (!$pagedetails) {
            return $this->content;
        }

        $config = get_config("block_activity_discuss");

        $this->content = new stdClass();
        $this->content->text = '';

        // Find a valid discussion forum for the course.
        $forumid = block_activity_discuss_get_forum_id_for_course($COURSE->id);

        if (!$forumid) {

            // This means no forum has been created in this course.  Optionally print a message to highlight this.
            if (!empty($config->displayerrornoforumexists)) {
                if (isset($config->displayerrornoforumexistsmessage)) {
                    $this->content->text .= $config->displayerrornoforumexistsmessage;
                }
            }
            return $this->content->text;
        }

        $this->content->text .= '<section id="block_activity_discuss_posts_container">' .
                                '<div class="block_activity_discuss_posts_container_content">' .
                                '<div class="header block_activity_discuss_header_section">' .
                                '<h3 class="headline block_activity_discuss_headline_primary' .
                                '">' . get_string('blockheadertitle', 'block_activity_discuss') . '</h3>';

        // Tagline, if set.
        if (!empty($config->blockheadertitletagline)) {
            $this->content->text .= '<div class="block_activity_discuss_header_tagline">' . $config->blockheadertitletagline .
                                    '</div>';
        }

        $this->content->text .= '</div>';

        // Find the relevant discussion from the forum for this section.
        $discussionid = block_activity_discuss_get_discussion_id_for_page($COURSE->id, $pagedetails['pageinternalid'],
                        $pagedetails['pagename']);

        $cm = get_coursemodule_from_instance('forum', $forumid, $COURSE->id);

        if (!$discussionid) {

            // Display form for a new discussion.
            $discussionid = 0;
            $parentpostid = 0;
            $this->content->text .= '<div id="block_activity_discuss_reply_container">';
            $this->content->text .= block_activity_discuss_display_new_comment_form($cm, $forumid,
                                    $pagedetails['pageinternalid'], $pagedetails['pagename'], 'course-discuss-create-post',
                                    $discussionid, $parentpostid);
            $this->content->text .= '</div><div id="comment_0"></div><div class="course-discuss-feed-container"></div>';

        } else {

            // If we found a discussion id then try to retrieve record for it.
            $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', IGNORE_MISSING);

            if (!$discussion) {

                // If we're here, this probably means the discussion was deleted.  Meaning the row created
                // in block_activity_discus_lookup that references the old discussion id is now invalid.  This row will be
                // updated with a valid discussion id once a new comment is posted.
                // I.e. when the library function block_activity_discuss_link_discussion_to_section is called.

                // Just display form for a new discussion.
                $discussionid = 0;
                $parentpostid = 0;
                $this->content->text .= '<div id="block_activity_discuss_reply_container">';

                $this->content->text .= block_activity_discuss_display_new_comment_form($cm, $forumid,
                                        $pagedetails['pageinternalid'], $pagedetails['pagename'], 'course-discuss-create-post',
                                        $discussionid, $parentpostid);
                $this->content->text .= '</div><div id="comment_0"></div><div class="course-discuss-feed-container"></div>';

            } else {

                // Display the discussion.
                $course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
                $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

                $allowreply = true;

                $this->content->text .= block_activity_discuss_display_discussion($cm, $course, $discussion, $forum,
                                        $pagedetails['pageinternalid'], $pagedetails['pagename'], $allowreply);
            }
        }

        $this->content->text .= '</div></section>';   // End section and div.

        return $this->content;
    }

    /**
     * Allows multiple instances of the block.
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Sets block header to be hidden or visible
     *
     * @return bool if true then header will be visible.
     */
    public function hide_header() {
        $config = get_config("block_activity_discuss");

        // If title in settings is empty, hide header.
        if (!empty($config->title)) {
            return false;
        } else {
            return true;
        }
    }

}
