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
 * This file contains language strings used for the Course discuss block
 *
 * @package block_activity_discuss
 * @copyright 2018 Manoj Solanki
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Course Activity comments';
$string['blocktitle'] = 'Course Activity comments';

$string['blockheadertitle'] = 'Comments';
$string['blockheadertitletagline'] = 'Tagline to display below header title';
$string['blockheadertitletaglinedesc'] = 'Optional tagline to display below the header title.';

$string['activity_discuss:addinstance'] = 'Add a Course activity comments block';
$string['activity_discuss:myaddinstance'] = 'Add a Course activity comments block to the My Moodle page.';

$string['title'] = 'Course activity comments';
$string['titledesc'] = 'Title for Course activity comments block.  Leave empty to not show one (default).';

$string['displayerrornoforumexists'] = 'Display Error if no valid Forum of type "General" exists';
$string['displayerrornoforumexistsdesc'] = 'Display error message if no Forum of type "Standard forum for general use" exists in the course.';

$string['displayerrornoforumexistsmessage'] = 'Error message to display if no Forum exists';
$string['displayerrornoforumexistsmessagedesc'] = 'Error message to display if no Forum exists in the course.';
$string['displayerrornoforumexistsmessagetext'] = 'No Forum activity exists in this course.  Please create one of type "Standard forum for general use" for use by this block.';

$string['forumnamepattern'] = 'Forum name pattern for Forum to use for discussion';
$string['forumnamepatterndesc'] = 'Choose a forum name pattern if desired, to assist in how to choose which forum is used for discussions in a module.
Pattern will look for a case insensitve match. E.g. By using "General Discussion", it would match "General Discussion" and "This is a General Discussion forum".
This can contain a comma separated list of potential matches.';

$string['useridforposts'] = 'User id for discussion';
$string['useridforpostsdesc'] = 'Enter the user id of a user that will post the initial discussion. This should be populated, otherwise the author of the discussion will be the
                                 first user who comments.';
$string['privacy:metadata'] = 'The Activity Discussion block only stores existing course and forum data.';

$string['charactersremainingtext'] = ' characters remaining';
$string['pleaseaddmessagetext'] = 'Please add a message';
$string['postcouldnotbeaddedtext'] = 'Your post could not be added';
$string['warninginternalerrortext'] = 'Warning, possible internal error when adding discussion';
$string['warningtitletext'] = 'Warnings: ';
$string['thankyouforpostingtext'] = 'Thanks for your comment. Loading...';
$string['errortitletext'] = 'Error message: ';
$string['errordisplayreplyformtext'] = 'Error displaying reply form';
$string['discussioncreatedinternalerrortext'] = 'Discussion created but possible internal error.';
$string['errordisplayingdiscussiontext'] = 'Error displaying discussion';

$string['initialpostlinktopage'] = 'Link to relevant page in initial post';
$string['initialpostlinktopagedesc'] = 'During creation of initial post on a page, when referencing the page, make it a link. This allows going directly to the page when viewing the discussion in a forum page.
 E.g. Discuss "Section 1", where "Section 1" can be a link to the section page.';

$string['threadlinktext'] = 'View thread in Forum';
$string['threadsendingtext'] = 'Sending message...';
$string['editlinktext'] = 'Edit post';

$string['allowedit'] = 'Show Edit link for a post';
$string['alloweditdesc'] = 'Show Edit link for a post if user has permission to edit and edit time (from main settings) has not been exceeded.';

$string['showviewthread'] = 'Show View Thread link';
$string['showviewthreaddesc'] = 'Show View Thread link near top of posts when a discussion has been started.';
