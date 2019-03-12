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
 * Course Discuss block helper functions and callbacks.
 *
 * @package block_activity_discuss
 * @copyright 2018 Manoj Solanki (Coventry University) [inititial block_course_discuss module]
 * @copyright 2019 NHS Leadership Academy [forked block_activity_discuss and changes]
 * @author Tony Blacker
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/forum/lib.php');

// These are required for library function called block_activity_discuss_time_ago, to get time elapsed since a post was made.
define( 'TIMEBEFORE_NOW',         'now' );
define( 'TIMEBEFORE_MINUTE',      '{num} minute ago' );
define( 'TIMEBEFORE_MINUTES',     '{num} minutes ago' );
define( 'TIMEBEFORE_HOUR',        '{num} hour ago' );
define( 'TIMEBEFORE_HOURS',       '{num} hours ago' );
define( 'TIMEBEFORE_YESTERDAY',   'yesterday' );
define( 'TIMEBEFORE_FORMAT',      '%e %b' );
define( 'TIMEBEFORE_FORMAT_YEAR', '%e %b, %Y' );

/**
 * Get forum used for this course to store all discussions, if one exists. If a forum
 * hasn't been linked yet (which would be the first time the block is rendered on a relevant page),
 * then a link is created.
 *
 * This is also checks that a Forum activity at least exists in this course and returns an
 * error message if required.
 *
 * @param int $courseid Course id to check
 *
 * @return id Forum ID if it's found, otherwise false
 */
function block_activity_discuss_get_forum_id_for_course($courseid) {
    global $DB;

    $record = $DB->get_record('block_activity_discuss', array('courseid' => $courseid));

    $modinfo = get_fast_modinfo($courseid);
    $modfullnames = array();

    // Use this variable to store multiple forum activities, just incase. It's possible that
    // more than one forum activity exists in the course so need to handle this scenario.
    $forumsfoundarray = array();

    foreach ($modinfo->cms as $cm) {

        if ($cm->modname == 'forum') {
            $forumsfoundarray[] = $cm;
        }
    }

    // If no forums are found, return false.
    if (empty($forumsfoundarray)) {
        return false;
    }

    // We've found at least one forum. Now see if it matches anything in the block_activity_discuss record query done earlier.
    if (! empty($record)) {

        foreach ($forumsfoundarray as $cm) {
            $id = $cm->instance;
            if ($id == $record->forumid) {
                return $id;
            }
        }

        // If there was not a match, we have an orphan record that doesn't match any valid forumid. In this case, remove this
        // record and then fall through to the next part which creates a record.

        $result = $DB->delete_records('block_activity_discuss', array('courseid' => $courseid));

        // Future expansion. Log an event here?
    }

    // If we've reached here, we need to create a record for the first (hopefully only) forum found for the course.
    // We will choose the forum based on any search pattern from settings or with the lowest id.

    // Future expansion. Log an event here?

    $coursediscussdata = new \stdClass();

    $coursediscussdata->courseid = $courseid;
    $coursediscussdata->forumid = 100000; // Arbitrary high number for the loop to find the lowest id, if we need to.

    // If this is set in config, perform a pattern match check on a forum name.
    $config = get_config("block_activity_discuss");

    if (!empty ($config->forumnamepattern) ) {

        // The foruname pattern may contain a comma separated list of strings.  So we treat as an array until we get a match.
        $patternfieldvalues = explode(',', $config->forumnamepattern);

        $pattern = '/' . $config->forumnamepattern . '/i';

        $foundmatch = false;
        foreach ($patternfieldvalues as $fieldvalue) {

            // If we find empty strings (e.g. ,, in the string), continue to the next valid value.
            if (empty($fieldvalue)) {
                continue;
            }
            $pattern = '/' . $fieldvalue . '/i';
            foreach ($forumsfoundarray as $cm) {

                if (preg_match($pattern, $cm->name)) {

                    // Check that forum is of type for general use.
                    $forum = $DB->get_record('forum', array('id' => $cm->instance), '*', MUST_EXIST);
                    if ($forum->type == 'general') {
                        $coursediscussdata->forumid = $cm->instance;
                        $foundmatch = true;
                        break;
                    }
                }
            }

            if ($foundmatch) {
                break;
            }
        }
    }

    // If no forum assigned from previous check, then fall back to finding the forum with the lowest id.
    if ($coursediscussdata->forumid == 100000) {
        foreach ($forumsfoundarray as $cm) {
            if ($cm->instance < $coursediscussdata->forumid) {

                // Check that forum is of type for general use.
                $forum = $DB->get_record('forum', array('id' => $cm->instance), '*', MUST_EXIST);
                if ($forum->type == 'general') {
                    $coursediscussdata->forumid = $cm->instance;
                }

            }
        }

        // If still no forum assigned, because no general forum was found above, return false.
        if ($coursediscussdata->forumid == 100000) {
            return false;
        }
    }

    $result = $DB->insert_record('block_activity_discuss', $coursediscussdata);

    // If succesfully added record.
    if ($result > 0) {
        return $coursediscussdata->forumid;
    } else {
        return $result;
    }
}

/**
 *
 * Check if the block is allowed to display on the current page we're on and return the mod section, page or book.
 * E.g. section ID, page ID, chapter ID.  This refers to the ID in the database for the mod or section.
 * This basically checks against $allowedpages.
 *
 * @return array containing the pageinternalid and pagename, or false.
 *
 */
function block_activity_discuss_get_current_page_details() {
    global $COURSE, $PAGE;

    // This will store the kind of activity page type we find. E.g. It will get populated with 'section' or similar.
    $currentpage = '';
    $internalid = 0;

    // Check for section page.
    $currentsection = block_activity_discuss_get_current_sectionnum();
    if ($currentsection != false) {
       // $currentpage = 'section';
    } else {
        // Check for mods.
        // We expect $PAGE->url to exist.  It should!
        $currenturl = $PAGE->url;

        if ( stristr ($currenturl, "mod/page/view") ) {
            $currentpage = 'page';
        } else if ( stristr ($currenturl, "mod/book/view") ) {
            $currentpage = 'book';
        } else if ( stristr ($currenturl, "mod/scorm/view") ) {
            $currentpage = 'scorm';
        }

    }

    // Check allowed pages.
    if (in_array($currentpage, block_activity_discuss::$allowedpages)) {
        switch ($currentpage) {
            case 'section' :
                // Get the section ID (from course_sections table).
                $courseformat = course_get_format($COURSE);
                $currentsectioninfo = $courseformat->get_section($currentsection);
                $internalid = $currentsectioninfo->id;
                break;
            case 'page' :
                parse_str(parse_url(html_entity_decode($currenturl), PHP_URL_QUERY), $querydata);
                // Get the page ID.  This id is actually the cm ID (from course_modules table) for the page.
                $internalid = $querydata['id'];
                break;
            case 'book' :
                // Get the book chapter ID.  This is actually the ID from the book_chapters table.
                parse_str(parse_url(html_entity_decode($currenturl), PHP_URL_QUERY), $querydata);
                $internalid = $querydata['chapterid'];
                break;
            case 'scorm' :
                // Get the book chapter ID.  This is actually the ID from the book_chapters table.
                parse_str(parse_url(html_entity_decode($currenturl), PHP_URL_QUERY), $querydata);
                $internalid = $querydata['id'];
                break;
        }
    } else {
        return false;
    }

    return array ('pageinternalid' => $internalid, 'pagename' => $currentpage);
}


/**
 * Get discussion for the current activity or section page.
 *
 * This will look up the block_activity_discus_lookup table to try find a match for the
 * current page id (section, book or page). If found, return the row, otherwise false.
 *
 * @param int $courseid       Course id
 * @param int $pageinternalid Page id to check.  For example, internal id for section, page
 * @param int $pagename       Page name to check.  For example, "section", "page"
 *
 * @return array Row if it's found, otherwise false
 */
function block_activity_discuss_get_discussion_id_for_page($courseid, $pageinternalid, $pagename) {
    global $DB;

    $record = $DB->get_record('block_activity_discus_lookup', array(
        'courseid' => $courseid,
        'pageinternalid' => $pageinternalid,
        'pagename' => $pagename
    ));

    if (! empty($record)) {
        return $record->discussionid;
    }

    return false;
}

/**
 * Link discussion to a section.
 *
 * This will add a row in the block_activity_discus_lookup table for the relevant discussion and current internal page id.
 * This id is the section id, book chapter id or cmid for page presently.
 *
 * @param int $courseid       Course id
 * @param int $pageinternalid Page id to check.  For example, internal id for section, page
 * @param int $pagename       Page name to check.  For example, "section", "page"
 * @param int $forumid        Forum id
 * @param int $discussionid   Discussion id
 *
 * @return array Row id.
 */
function block_activity_discuss_link_discussion_to_pageinternalid($courseid, $pageinternalid, $pagename, $forumid, $discussionid) {
    global $DB;

    // First check if there is an existing row.  There shouldn't be! If there is, update that row.
    $record = $DB->get_record('block_activity_discus_lookup', array(
        'courseid' => $courseid,
        'pageinternalid' => $pageinternalid,
        'pagename' => $pagename
    ));

    // If existing record found, this means we have an orphan link.  Reuse this.
    if (! empty($record)) {
        $record->courseid = $courseid;

        $record->pageinternalid = $pageinternalid;
        $record->pagename = $pagename;
        $record->forumid = $forumid;
        $record->discussionid = $discussionid;
        $record->timecreated = time();

        $result = $DB->update_record('block_activity_discus_lookup', $record);
        return $result;
    } else {

        // Now add the record.
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->pageinternalid = $pageinternalid;
        $record->pagename = $pagename;

        $record->forumid = $forumid;
        $record->discussionid = $discussionid;
        $record->timecreated = time();
        $lastinsertid = $DB->insert_record('block_activity_discus_lookup', $record, false);

        return $lastinsertid;

    }

}

/**
 * Get discussion for the page.
 *
 * @param object   $cm              Relevant course module
 * @param object   $course          Course object
 * @param object   $discussion      Discussion object
 * @param object   $forum           Forum object
 * @param int      $pageinternalid  Page id to check.  For example, internal id for section, page
 * @param int      $pagename        Page name to check.  For example, "section", "page"
 * @param int      $allowreply      Optional param to allow displaying of a comment form before discussions
 *
 * @return string Return discussion content.
 */
function block_activity_discuss_display_discussion($cm, $course, $discussion, $forum, $pageinternalid, $pagename, $allowreply = 0) {
    global $CFG, $PAGE, $OUTPUT;

    $content = '';

    // Get the discussion.
    $sort = "p.created DESC";
    $forumtracked = false;

    $parent = $discussion->firstpost;
    $post = forum_get_post_full($parent);

    $modcontext = context_module::instance($cm->id);

    $posts = forum_get_all_discussion_posts($discussion->id, $sort, $forumtracked);
    $post = $posts[$post->id];

    if (!empty($posts)) {



        ob_start();

        echo '<div class="course-discuss-feed-container">';
        echo '<ul id="block_activity_discuss_feed" class="block_activity_discuss_feed">';
        block_activity_discuss_get_posts_nested($course, $cm, $forum, $discussion, $post, $forumtracked, $posts);
        echo '</ul>';
        echo '</div>';
        $postsresult = ob_get_contents();

        ob_end_clean();

        $content .= $postsresult;
        // First display comment form for reply, if allowed. This is really only
        // used for displaying the top comment form along with the discussion.
        if ($allowreply) {
            $content .= '<div id="block_activity_discuss_reply_container">';
            $content .= block_activity_discuss_display_new_comment_form($cm, $forum->id, $pageinternalid, $pagename,
                'course-discuss-create-reply', $discussion->id, $parent);

            // This is added here on the basis it's the only occurance of comment_0 on the page.
            $content .= '</div><div id="comment_0"></div>';
        }

        // Get all posts in a nested fashion.
    }

    return $content;
}

/**
 * Process all posts for a discussion and return in HTML.
 *
 * @param stdClass $course          Relevant course
 * @param stdClass $cm              Relevant course module
 * @param stdClass $forum           Forum object
 * @param stdClass $discussion      Discussion object
 * @param stdClass $parent          Parent post object
 * @param bool     $forumtracked    Is forum tracked
 * @param array    $posts           array of post objects
 *
 * @return string Return discussion content in HTML.
 */
function block_activity_discuss_get_posts_nested($course, $cm, $forum, $discussion, $parent, $forumtracked, $posts) {
    global $USER, $CFG, $OUTPUT;

    // If this is set in config, perform a pattern match check on a forum name.
    static $config;
    $config = get_config("block_activity_discuss");

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {

            // User picture.
            $postuser = new stdClass();
            $postuserfields = explode(',', user_picture::fields());
            $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
            $postuser->id = $post->userid;

            $post->userpicture = $OUTPUT->user_picture($postuser, array('courseid' => $forum->course));

            $modcontext = context_module::instance($cm->id);

            // User name.
            $post->fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $modcontext));
            $post->userprofilelink = $CFG->wwwroot . '/user/view.php?id=' . $post->userid . '&amp;course=' . $forum->course;

            // Time since post.
            $post->timesince = block_activity_discuss_time_ago($post->modified);

            echo '<div class="block_activity_discuss_indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            $discussionpostsjson = new stdClass();
            $discussionpostsjson = $post;

            $editlink = '';
            $editpostallowed = false;

            // Edit link, if the user is allowed to post.
            $age = time() - $post->created;
            if (isloggedin()) {
                $ownpost = ($post->userid == $USER->id);
            } else {
                $ownpost = false;
            }

            if (!empty($config->allowedit)) {
                // This is always expected to find the discussion forum.
                if (!$post->parent && $forum->type == 'news' && $post->timestart > time()) {
                    $age = 0;
                }

                $modcontext = context_module::instance($cm->id);
                $canedit = has_capability('mod/forum:editanypost', $modcontext);

                if (($ownpost && $age < $CFG->maxeditingtime) || $canedit) {
                    $editlink = new moodle_url('/mod/forum/post.php', array('edit' => $post->id));
                    $editpostallowed = true;
                }

            }

            $post->editlink = $editlink;
            $post->editlinktext = get_string('editlinktext', 'block_activity_discuss');
            $post->editpostallowed = $editpostallowed;

            $post->showviewthread = false;

            echo $OUTPUT->render_from_template('block_activity_discuss/discussion-post', $discussionpostsjson);

            block_activity_discuss_get_posts_nested($course, $cm, $forum, $discussion, $post, $forumtracked, $posts);
            echo "</div>\n";
        }

    }

}

/**
 * Create new post in an existing discussion.  Take from forum externallib.php
 *
 * @param int     $postid      The post id we are going to reply to
 * @param string  $subject     New post subject
 * @param string  $message     New post message (only html format allowed)
 * @param array   $forum       Forum object
 * @param array   $discussion  Discussion object
 * @param array   $options     Optional settings
 *
 * @return array with the new post id or error message
 *
 */
function block_activity_discuss_add_discussion_post($postid, $subject, $message, $forum, $discussion, $options = array()) {
    global $DB, $CFG, $USER;

    if (!$parent = forum_get_post_full($postid)) {
        $result = array();
        $result['postid'] = 0;
        $result['error'] = 'Failed to add post. Invalid parent post id.';
        return $result;
    }

    if (!$discussion = $DB->get_record("forum_discussions", array("id" => $parent->discussion))) {
        $result = array();
        $result['postid'] = 0;
        $result['error'] = 'Failed to add post. Post does not belong to a valid discussion.';
        return $result;
    }

    // Request and permission validation.
    $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($forum, 'forum');

    $context = context_module::instance($cm->id);

    // Validate options.
    $options = array(
        'discussionsubscribe' => true,
        'inlineattachmentsid' => 0,
        'attachmentsid' => null
    );

    if (!forum_user_can_post($forum, $discussion, $USER, $cm, $course, $context)) {
        $result = array();
        $result['postid'] = 0;
        $result['error'] = 'Sorry, you are not permitted to post in this discussion.';
        return $result;
    }

    // Check if subject empty.  If so, use the parent post's subject. This is default behaviour as subject will probably be empty.
    if (empty($subject)) {
        $subject = "Re: " . $parent->subject;
    }

    // We add an arbitrary sleep to delay creation time of the new post by a second since this post is the second created when
    // a new discussion is started. This is to allow the discussion_feed block plugin to accurately identify new posts
    // since it relies on the discussion creation and last post created time.
    sleep(1);

    // Create the post.
    $post = new stdClass();
    $post->discussion = $discussion->id;
    $post->parent = $parent->id;
    $post->subject = $subject;
    $post->message = $message;
    $post->messageformat = FORMAT_HTML;   // Force formatting for now.
    $post->messagetrust = trusttext_trusted($context);
    $post->itemid = $options['inlineattachmentsid'];
    $post->attachments = $options['attachmentsid'];
    $post->deleted = 0;
    $fakemform = $post->attachments;
    $postid = forum_add_new_post($post, $fakemform);
    if ($postid) {

        $post->id = $postid;

        // Trigger events and completion.
        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );
        $event = \mod_forum\event\post_created::create($params);
        $event->add_record_snapshot('forum_posts', $post);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();

        // Update completion state.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
            ($forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $settings = new stdClass();
        $settings->discussionsubscribe = $options['discussionsubscribe'];
        forum_post_subscription($settings, $forum, $discussion);
    } else {
        $result = array();
        $result['postid'] = 0;
        $result['error'] = 'Failed to add post';
        return $result;
    }

    $result = array();
    $result['postid'] = $postid;
    return $result;
}

/**
 * Add a new discussion into an existing forum.  Taken from the forum externallib.php
 *
 * @param object $forum    The forum instance
 * @param string $subject  The subject
 * @param string $message  The message (only html format allowed)
 * @param object $cm       Course module instance
 * @param object $course
 * @param int    $groupid  The user course group
 * @param array  $options  Optional settings
 *
 * @return array with the new discussion id or error message
 */
function block_activity_discuss_add_discussion($forum, $subject, $message, $cm, $course, $groupid = 0, $options = array()) {
    global $DB, $CFG, $USER;

    // Request and permission validation.
    list($course, $cm) = get_course_and_cm_from_instance($forum, 'forum');

    $context = context_module::instance($cm->id);

    // Validate options.
    $options = array(
        'discussionsubscribe' => true,
        'discussionpinned' => false,
        'inlineattachmentsid' => 0,
        'attachmentsid' => null
    );

    // Normalize group.
    if (!groups_get_activity_groupmode($cm)) {
        // Groups not supported, force to -1.
        $groupid = -1;
    } else {
        // Check if we receive the default or and empty value for groupid,
        // in this case, get the group for the user in the activity.
        if (empty($groupid)) {
            $groupid = groups_get_activity_group($cm);
        } else {
            // Here we rely in the group passed, forum_user_can_post_discussion will validate the group.
            $groupid = $groupid;
        }
    }

    $userforposts = $USER;

    $config = get_config("block_activity_discuss");

    if (!empty ($config->useridforposts) ) {
        $userrecord = $DB->get_record('user', array('id' => $config->useridforposts));
        if (!empty($userrecord)) {
            $userforposts = $userrecord;
        }
    }

    if (!forum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
        $result = array();
        $result['discussionid'] = 0;
        $result['error'] = 'Sorry, you are not permitted to post in this discussion.';
        return $result;
    }

    // Create the discussion.
    $discussion = new stdClass();
    $discussion->course = $course->id;
    $discussion->forum = $forum->id;
    $discussion->message = $message;
    $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
    $discussion->messagetrust = trusttext_trusted($context);
    $discussion->itemid = $options['inlineattachmentsid'];
    $discussion->groupid = $groupid;
    $discussion->mailnow = 0;
    $discussion->subject = $subject;
    $discussion->name = $discussion->subject;
    $discussion->timestart = 0;
    $discussion->timeend = 0;
    $discussion->attachments = $options['attachmentsid'];

    if (has_capability('mod/forum:pindiscussions', $context) && $options['discussionpinned']) {
        $discussion->pinned = FORUM_DISCUSSION_PINNED;
    } else {
        $discussion->pinned = FORUM_DISCUSSION_UNPINNED;
    }
    $fakemform = $options['attachmentsid'];
    $discussionid = forum_add_discussion($discussion, $fakemform, null, $userforposts->id);
    if ($discussionid > 0) {

        $discussion->id = $discussionid;

        // Trigger events and completion.
        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = \mod_forum\event\discussion_created::create($params);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();

        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
            ($forum->completiondiscussions || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $settings = new stdClass();
        $settings->discussionsubscribe = $options['discussionsubscribe'];
        forum_post_subscription($settings, $forum, $discussion);
    } else {
        $result = array();
        $result['discussionid'] = $discussionid;
        $result['error'] = 'Failed to add discussion';
        return $result;
    }

    $result = array();
    $result['discussionid'] = $discussionid;
    return $result;
}

/**
 * Get new comment form for section.
 *
 * @param object   $cm              Relevant course module
 * @param object   $forumid         Forum objects
 * @param int      $pageinternalid  Page id to check.  For example, internal id for section, page
 * @param string   $pagename        Page name to check.  For example, "section", "page"
 * @param int      $buttonid        Button id to be assigned to button in rendering
 * @param int      $discussionid    Discussion id
 * @param int      $parentpostid    Parent post id
 *
 * @return string Return discussion content.
 */
function block_activity_discuss_display_new_comment_form($cm, $forumid, $pageinternalid, $pagename,
                                                       $buttonid, $discussionid = 0, $parentpostid = 0) {
    global $DB, $OUTPUT;

    $forum = $DB->get_record('forum', array(
        'id' => $forumid
    ));

    $content = '';

    $context = context_module::instance($cm->id);

    $replyformdata = new stdClass();
    $replyformdata->forumid = $forum->id;
    $replyformdata->courseid = $cm->course;
    $replyformdata->pageinternalid = $pageinternalid;
    $replyformdata->pagename = $pagename;
    $replyformdata->discussionid = $discussionid;
    $replyformdata->contextid = $context->id;
    $replyformdata->buttonid = $buttonid;
    $replyformdata->parentpostid = $parentpostid;
    $replyformdata->replyplaceholder = 'Add your comment';

    $replyformdata->showviewthread = false;

    $config = get_config("block_activity_discuss");

    if ( (!empty($config->showviewthread)) &&
            ($discussionid > 0) ) {
        $replyformdata->showviewthread = true;

        $replyformdata->threadlink = new moodle_url('/mod/forum/discuss.php', ['d' => $discussionid]);
        $replyformdata->threadlinktext = get_string('threadlinktext', 'block_activity_discuss');
    }

    $content .= $OUTPUT->render_from_template('block_activity_discuss/discussion-reply-form', $replyformdata);

    return $content;
}

/**
 * Check if this is a course section page and return section number if necessary.
 *
 * @return int   Section number or return false.
 *
 */
function block_activity_discuss_get_current_sectionnum() {
    global $PAGE, $ME;

    $sectionnumber = false;

    // Check for general course page first.
    $url = null;

    // Check if $PAGE->url is set.  It should be, but also using a fallback.
    if ($PAGE->has_set_url()) {
        $url = $PAGE->url;
    } else if ($ME !== null) {
        $url = new moodle_url(str_ireplace('/index.php', '/', $ME));
    }

    // In practice, $url should always be valid.
    if ($url !== null) {
        // Check if this is the course view page.
        if (strstr ($url->raw_out(), 'course/view.php')) {

            // Get raw querystring params from URL.
            $getparams = http_build_query($_GET);

            // Check url paramaters.
            $urlparams = $url->params();

            if ((count ($urlparams) > 1) && (array_key_exists('section', $urlparams)) ||
                (strstr ($getparams, 'section=')) && (array_key_exists('section', $urlparams)) ) {
                    $sectionnumber = $urlparams['section'];
            }

            // If not found, this means the section page may have been accessed using sectionid in the URL params.
            if (!$sectionnumber) {

                if ((count ($urlparams) > 1) && (array_key_exists('sectionid', $urlparams)) ||
                        (strstr ($getparams, 'sectionid=')) && (array_key_exists('sectionid', $urlparams)) ) {
                    $sectionnum = $DB->get_field('course_sections', 'section', array('id' => $urlparams['section']), MUST_EXIST);
                    $sectionnumber = $sectionnum;
                }
            }

        }
    }

    return $sectionnumber;
}

/**
 * Get the time elapsed since $time.
 *
 * @param  int    $time  The time to evaluate (in epoch)
 *
 * @return string Formatted string of time elapsed
 *
 */
function block_activity_discuss_time_ago($time) {

    $out    = ''; // What we will print out.
    $now    = time();
    $diff   = $now - $time; // Difference between the current and the provided dates.

    if ($diff < 60) {

        // It happened now.
        return TIMEBEFORE_NOW;

    } else if ($diff < 3600) {

        // It happened X minutes ago.
        return str_replace( '{num}', ( $out = round( $diff / 60 ) ), $out == 1 ? TIMEBEFORE_MINUTE : TIMEBEFORE_MINUTES );

    } else if ($diff < 3600 * 24) {

        // It happened X hours ago.
        return str_replace( '{num}', ( $out = round( $diff / 3600 ) ), $out == 1 ? TIMEBEFORE_HOUR : TIMEBEFORE_HOURS );

    } else if ($diff < 3600 * 24 * 2) {

        // It happened yesterday.
        return TIMEBEFORE_YESTERDAY;

    } else {

        // Falling back on a usual date format as it happened later than yesterday.
        return strftime( date( 'Y', $time ) == date( 'Y' ) ? TIMEBEFORE_FORMAT : TIMEBEFORE_FORMAT_YEAR, $time );

    }
}