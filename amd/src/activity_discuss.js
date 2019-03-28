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
 * Handles all events for replying to comments and creating discussions.  Uses the web services
 * provided by block_activity_discuss.
 *
 *
 * @module     block_activity_discuss/course_discuss
 * @class      course_discuss
 * @package    block_activity_discuss
 * @copyright  2018 Manoj Solanki (Coventry University)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.2
 */

define(['jquery', 'core/ajax'], function($, ajax) {

    return {

        init: function(langstrings) {

            // For max number of characters in a textarea input.
            $(document).ready(function() {
                var textMax = 1500;
                $('#block_activity_discuss_form_message_feedback').html(textMax + langstrings.charactersremainingtext);

                $('#block_activity_discuss_form_message').keyup(function() {
                    var textLength = $('#block_activity_discuss_form_message').val().length;
                    var textRemaining = textMax - textLength;

                    $('#block_activity_discuss_form_message_feedback').html(textRemaining + langstrings.charactersremainingtext);
                });
            });

            // Handler to create a new discussion with a new post. E.g. When first post is made on a page.
            $("#form-course-discuss-create-post").click(function(event) {

                event.preventDefault();

                var data = $('#course-discuss-discussion-form').serializeArray().reduce(function (obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});

                if (data.message == "") {
                    $("#comment_0").html('<h3 class="block_activity_discuss_error">' + langstrings.pleaseaddmessagetext + '</h3>');
                    return;
                }

                ajax.call([{
                    methodname: 'block_activity_discuss_create_discussion',
                    args: {'courseid': data.courseid, 'forumid': data.forumid,
                        'contextid': data.contextid, 'message': data.message,
                        'pageinternalid': data.pageinternalid, 'pagename': data.pagename},
                    done:

                        function() {

                            var result = arguments[0];

                            data.discussionid = result.discussionid;
                            data.subject = "";

                            if (result.eventaction == 'discussionnotcreated') {
                                $("#comment_0").html('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                                $("#comment_0").append(langstrings.errortitletext + ' "' + result.errormessage + '".<br>');
                            } else {

                                // Check if there were any warnings from the last call.
                                if (result.warnings) {
                                    $("#comment_0").html('<h3>' + langstrings.warninginternalerrortext + '</h3>');
                                    $("#comment_0").append(langstrings.warningtitletext + result.warning);
                                } else {

                                    // Before continuing, we need to reload the form, to make the hidden form variable
                                    // discussionid contain the new discussion id, as it will have been empty initially
                                    // (as no discussion existed). And also the button id contains an important value that
                                    // triggers the correct function for replies. This is all to allow replies immediately
                                    // after first posting (however unlikely).
                                    ajax.call([{
                                        methodname: 'block_activity_discuss_display_reply_form',
                                        args: {'courseid': data.courseid, 'forumid': data.forumid,
                                            'pageinternalid': data.pageinternalid, 'pagename': data.pagename,
                                            'discussionid': data.discussionid, 'parentpostid': 0
                                        },
                                        done:
                                            function() {

                                                var result = arguments[0];
                                                $("#block_activity_discuss_reply_container").html(result.html);

                                                // Post a reply now.
                                                result = postReply(data);

                                                result[0].done(function() {

                                                    var postresult = arguments[0];

                                                    if (postresult.eventaction == 'postnotcreated') {
                                                        $("#comment_0").append('<h3>' + langstrings.postcouldnotbeaddedtext +
                                                            '</h3>');
                                                        $("#comment_0").append(langstrings.errortitletext + ' "' +
                                                            postresult.errormessage + '".<br>');

                                                    } else {

                                                        $("#comment_0").append('<h3>' + langstrings.thankyouforpostingtext +
                                                            '</h3>');
                                                        $("#block_activity_discuss_form_message").val("");

                                                        $("#comment_0").fadeOut(3000, function() {
                                                            // Animation complete.
                                                            $("#comment_0").show();
                                                            $("#comment_0").text("");
                                                            location.reload();
                                                            // made the first post trigger a hard reload to display comment correctly
                                                            //displayDiscussion(data, data.discussionid);
                                                        });

                                                    }

                                                }).fail(function(ex) {
                                                    $("#comment_0").append('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                                                    $("#comment_0").append(langstrings.errortitletext + ' "' + ex.message +
                                                        '".<br>');
                                                });
                                            },

                                        fail: function(ex) {
                                            $("#comment_0").html('<h3>' + langstrings.discussioncreatedinternalerrortext + '</h3>');
                                            $("#comment_0").append(ex.message);
                                        },

                                    }]);
                                }
                            }
                        },

                    fail: function(ex) {
                        $("#comment_0").html('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                        $("#comment_0").append(langstrings.errortitletext + ' "' + ex.message + '".<br>');

                        $("#block_activity_discuss_form_message").val("");
                    }
                }]);

            });

            // Handle reply button click to parent post.
            $("#block_activity_discuss_reply_container").on("submit", "form", function(event) {

                event.preventDefault();

                var data = $('#course-discuss-discussion-form').serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});

                if (data.message == "") {
                    $("#comment_0").html('<h3 class="block_activity_discuss_error">' + langstrings.pleaseaddmessagetext + '</h3>');
                    return;
                }

                data.subject = "";
                data.parentpostid = 0;

                var result = postReply(data);

                result[0].done(function() {

                    var postresult = arguments[0];
                    if (postresult.eventaction == 'postnotcreated') {
                        $("#comment_0").append('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                        $("#comment_0").append(langstrings.errortitletext + ' "' + postresult.errormessage + '".<br>');

                    } else {
                        $("#comment_0").append('<h3>' + langstrings.thankyouforpostingtext + '</h3>');

                        $("#block_activity_discuss_form_message").val("");

                        $("#comment_0").fadeOut(3000, function() {
                            // Animation complete.
                            $("#comment_0").show();
                            $("#comment_0").text("");

                            displayDiscussion(data);
                        });
                    }

                }).fail(function(ex) {
                    $("#comment_0").append('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                    $("#comment_0").append(langstrings.errortitletext + ' "' + ex.message + '".<br>');
                });

            });

            // Handle click on reply button to display reply form.
            $(".course-discuss-feed-container").on("click", function(event) {

                if (event.target.getAttribute("class") == "block_activity_discuss_action_button") {
                    var postid = $(event.target).val();

                    // Get data from main reply form. E.g. discussion id, forum id etc.
                    var data = $('#course-discuss-discussion-form').serializeArray().reduce(function(obj, item) {
                        obj[item.name] = item.value;
                        return obj;
                    }, {});

                    ajax.call([{
                        methodname: 'block_activity_discuss_display_reply_form',
                        args: {'courseid': data.courseid, 'forumid': data.forumid,
                            'pageinternalid': data.pageinternalid, 'pagename': data.pagename, 'discussionid': data.discussionid,
                            'parentpostid': postid
                        },
                        done:

                            function() {
                                var result = arguments[0];
                                $("#course-discuss-reply-form-container_" + postid).html(result.html);
                                $("#course-discuss-reply-form-container_" + postid +
                                    " #block_activity_discuss_form_message").focus();
                            },

                        fail: function(ex) {
                            $("#comment_" + postid).html('<h3>' + langstrings.errordisplayreplyformtext + '</h3>');
                            $("#comment_" + postid).append(langstrings.errortitletext + ' "' + ex.message + '".<br>');
                        }
                    }]);

                }

            });

            // Handle submit button click on a comment reply form.
            $(".course-discuss-feed-container").on("submit", "form", function(event) {

                event.preventDefault();

                // Get form data and then post reply.
                var data = $(this).serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});

                if (data.message == "") {
                    $("#comment_" + data.parentpostid).html('<h3 class="block_activity_discuss_error">' +
                        langstrings.pleaseaddmessagetext + '</h3>');
                    return;
                }

                var result = postReply(data);

                result[0].done(function() {

                    var postresult = arguments[0];
                    if (postresult.eventaction == 'postnotcreated') {
                        $("#comment_" + data.parentpostid).append('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                        $("#comment_" + data.parentpostid).append(langstrings.errortitletext + ' "' + postresult.errormessage +
                            '".<br>');

                    } else {

                        $("#comment_" + data.parentpostid).append('<h3>' + langstrings.thankyouforpostingtext + '</h3>');

                        $("#course-discuss-reply-form-container_" + data.parentpostid).html("");

                        $("#comment_" + data.parentpostid).fadeOut(3000, function() {
                            // Animation complete.
                            $("#comment_" + data.parentpostid).show();
                            $("#comment_" + data.parentpostid).text("");

                            displayDiscussion(data, data.discussionid);
                        });

                    }

                }).fail(function(ex) {
                    $("#comment_" + data.parentpostid).append('<h3>' + langstrings.postcouldnotbeaddedtext + '</h3>');
                    $("#comment_" + data.parentpostid).append(langstrings.errortitletext + ' "' + ex.message + '".<br>');

                });

            });

            // Handle cancel button click on a comment reply form to close the form.
            $(".course-discuss-feed-container").on("reset", "form", function(event) {

                event.preventDefault();

                var data = $(this).serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});

                $("#course-discuss-reply-form-container_" + data.parentpostid).html("");

            });

            /**
             * Display the discussion.
             *
             * @method displayDiscussion
             * @param {Object} data Contains data required.
             * @return {Promise} jQuery Deferred object to fail or resolve
             */
            var displayDiscussion = function(data) {

                return ajax.call([{
                    methodname: 'block_activity_discuss_display_discussion',
                    args: {'courseid': data.courseid, 'forumid': data.forumid, 'discussionid': data.discussionid},
                    done: function() {
                        var result = arguments[0];

                        var displaycontent = "";
                        if (data.usermessage) {
                            displaycontent = data.usermessage;
                        }

                        displaycontent += result.html;
                        $(".course-discuss-feed-container").html(displaycontent);

                    },

                    fail: function(ex) {
                        $("#comment_0").html('<h3>' + langstrings.errordisplayingdiscussiontext + '</h3>');
                        $("#comment_0").append(langstrings.errortitletext + ' "' + ex.message + '".<br>');
                    }
                }]);

            };

            /**
             * Post a reply.
             *
             * @method postReply
             * @param  {Object} data Contains required data,
             * @return {Promise} jQuery Deferred object
             */
            var postReply = function(data) {

                $("#block_activity_discuss_reply_container .overlay_container").css('display', 'flex');
                return ajax.call([{
                    methodname: 'block_activity_discuss_create_post',
                    args: {'courseid': data.courseid, 'forumid': data.forumid, 'contextid': data.contextid,
                        'message': data.message, 'discussionid': data.discussionid, 'subject': data.subject,
                        'parentpostid': data.parentpostid},
                    done: function() {
                        $("#block_activity_discuss_reply_container .overlay_container").css('display', 'none');
                    },
                    fail: function() {
                        $("#block_activity_discuss_reply_container .overlay_container").css('display', 'none');
                    }
                }]);

            };

        }

    };
});
