# Changelog

## [Unreleased][unreleased]



## [2.4.10] - 2015-02-01

### Fixed
- [installation] Fix error on creating g_root_path in config file


## [2.4.9] - 2015-01-25

### Fixed
- [mail] Update to PhpMailer 5.2.9
- [mail] #791 Problems with mail since version 2.4.7 on some installations
- [mail] #795 Error on mail send because of Uppercase filenames
- [events] #794 Wrong room shown in event if it's not the first room
- [events] #793 Number of participants not shown if max participants is not set
- [user_management] #792 Incorrect Members assignment to the Organization for event participation
- [installation] #788 HTTPS not set in config.php


## [2.4.8] - 2014-10-25

### Fixed
- [system] #783 Unnecessary error log output leads to error on some servers
- [system] #778 password with '<' have lose this character
- [system] #785 Error in ie8 to ie11 when using calendar popup
- [profile] #787 Role leaders could not save hidden fields if they have the right to do so
- [organiztion] #744 Endlos-Loop when having wrong folder rights
- [lists] #784 Listname will not be shown in csv or pdf filename
- [lists] #599 Condition for field COUNTRY doesn't work
- [photos] #786 Wrong back navigation when downloading photos


## [2.4.7] - 2014-09-01

### Fixed
- [profile] #775 Dezimal 0 was cut in profile field of type numeric
- [events] #782 Event registrations were removed if event was edited
- [events] #776 Description of previous date was shown in rss feed
- [system] #779 Version check error was shown when saving organization preferences
- [system] #774 Wrong serverpath when admidio was installed in folder with name adm_
- [system] #781 DES-based hashes were not properly compared
- [downloads] #421 Long filenames disarranged the html layout


## [2.4.6] - 2014-06-28

### Added
- [Lists] Export role members to vCard (Developed by Stefan Weiß)

### Fixed
- [backup] #773 Database tables were deleted through Backup
- [lists] #771 PDF: When printing multiline columns than some rows were missing in output
- [user_management] #770 Users and role memberships were counted as edit by csv import also if they were not changed
- [system] #772 HtmLawed: Modifier /​e in preg_replace() deprecated in PHP 5.5


## [2.4.5] - 2014-04-05

### Added
- [Lists] Export lists to PDF (Developed by Stefan Weiß)
- [Events] New parameter to show only events where user participate
- [Mail] Update phpmailer from version 5.2.2 to 5.2.7

### Fixed
- [mail] #769 If you send a copy of mail than the list of all role Members was shown if user had no rights to view members
- [mail] #760 Problems with special chars in email subject resolved
- [mail] #764 Logged in user got same mail multiple times if BCC packages were small
- [system] #766 Organization of session had not be updated after login to different organization
- [system] #765 Undefined constant ROLE_LEADER_MEMBERS_ASSIGN after login was shown
- [system] #761 Maximal password length will not be set anymore (Please also update the login_form plugin)
- [registration] #763 Text in notification email of new registration was repeated
- [registration] #762 Error on sending email when you had delete a registration with no email address
- [events] #758 Wrong title was shown when editing calendars
- [user_management] #768 Error on page profile change history was shown in some cases


## [2.4.4] - 2014-01-11

### Added
- [Photos] Allow optional upload of original photo file on webspace (Developed by Henrik Muhs)
- [Photos] Allow optional download of photos and albums as zip file (Developed by Henrik Muhs)

### Fixed
- [events] #757 Ical export of specific calendar was not possible
- [events] #758 Wrong title when editing calendars
- [lists] #756 No results in my list if you had selected an entry of a dropdown field that consist of 2 words
- [lists] #380 Wrong sorting of a numeric profile field in lists
- [photos] #754 Photo Upload fails if image resize needs more than 50mb memory
- [profile] #755 No values of dropdown and radio button fields in field history
- [profile] #718 Problems with custom date format and the age calculation in profile
- [roles] #748 Several role dependencies of one role had assigned one member several times to that role
- [system] #750 Modifier /​p in preg_replace() was deprecated in PHP 5.5
- [system] #759 Check of valid database version failed


## [2.4.3] - 2013-10-20

### Fixed
- [roles] #752 Leaders had not edited there role members if preference was set
- [roles] #749 Role dependencies could not be assigned with PostgreSql
- [events] #747 Wrong Description of new/​edit link in mode old
- [events] #746 Missing edit link for dates in compact view
- [events] #743 Error in printview of dates if no participant was assigned
- [events] #742 Wrong sorting of old dates if calendar selection was changed
- [events] #731 Missing link for printview if user wasn't logged in
- [profile] #753 Only show role assignement dialog if user had the right to assign roles
- [mail] #745 Set copy flag in email could be misused as spam
- [plugins] #751 View problem in plugin sidebar_dates using compact view in settings


## [2.4.2] - 2013-06-28

### Added
- [Events] Add new viewmode "compact" to events
- [System] Support IPv6 addresses
- [Translation] Add new language "italian"

### Fixed
- [events] #722 Future events were labelled as past events
- [events] #734 ical export showed only the last 10 entries
- [events] #720 Date range filter and calendar selection hasn´t work in combination
- [events] #721 Event participation was possible even if a limit is set
- [events] #723 Flag "Agree to this appointment" could not be set later
- [events] #724 Flag "Agree to this appointment" could not be removed
- [events] #727 Copy event: Visibility would not be duplicated
- [events] #729 If the event creator was assigned within the event dialog or later in the event list there was no visual difference
- [profile] #739 Couldn't delete profile fields if log for that field exists
- [profile] #738 Couldn't stop a membership in profile dialog
- [profile] #730 Delete old undefined gender values from database
- [profile] #736 Leading zero would be cut in text fields
- [profile] #737 Leading zero would not be removed in numeric fields
- [roles] #725 Changed membership of current user was not noticed in actual session
- [roles] #732 Error when creating a new user and roles should be assigned
- [system] #735 Replace readfile with file_get_contents
- [system] #741 Result type from fetch_array was not compatible with PostgreSql
- [user_management] #733 Multiple role assignements to users through import
- [ecards] #728 Notice: split() is deprecated in ecards


## [2.4.1] - 2013-04-24

### Fixed
- [mail] #712 Wrong Reply-To address when send mail
- [ecards] #713 After sending ecard nothing happens and edit dialog is still there
- [ecards] #719 Close ecard window if ecard was successfully send
- [lists] #717 Long profilefield and condition values do not fit in content for own lists
- [lists] #716 Condition for Checkboxes are not saved in own lists
- [lists] #701 Problems with javascript when using quotes in category names
- [events] #715 Wrong list title of events if date of event was changed
- [events] #697 Extended functions tab should be reseted in date module
- [user_management] #714 Error in navigation stack when navigate to profile history
- [system] #711 2 Admidio-Systems in 1 Browser mix their session data


## [2.4.0] - 2013-04-02

### Added, Changed
- [roles] #475 New role preference to auto assign role at registration
- [roles] #466 Save editor and date when assign or edit a membership
- [roles] #370 Role leaders should edit members of their roles
- [roles] #462 Show future role memberships in profile
- [roles] #463 Allow multiple memberships to one role
- [roles] #464 Leaders should add or remove members to their role
- [roles] #476 New role preference to assign default list configuration
- [mail] #482 Use phpmailer to send emails with Admidio
- [mail] #443 Send mail with SMTP
- [mail] #483 Replace Notification function with email class
- [mail] #491 Review assignment of email and system email settings
- [mail] #493 Request delivery confirmation
- [events] #444 Filter event periods on surface
- [events] #458 Highlight dates
- [events] #456 Printview for dates
- [events] #477 Show number of leaders in date module
- [profile] #478 Show more role membership of other organizations if a user has more rights
- [profile] #469 Save profile field changes of every field
- [profile] #457 Revision of non deletable user fields
- [lists] #471 Show options of Dropdown fields in own lists
- [lists] #459 Better error handling when parsing mylist conditions
- [lists] #474 New conditions to select users by EMPTY, NOT EMPTY, NOT XYZ
- [lists] #176 MyLists: Add conditions for empty fields
- [registration] #37 Send Email if registration is rejected
- [registration] #460 Specify organisation at registration
- [user_management] #189 Search for loginname in user management
- [user_management] #492 Show all members should be optional deactivated
- [system] #490 Show 'deleted user' in create and change info, if the user was deleted
- [system] #495 New preference: Disable Created and Edited notice or show first and last name or username
- [system] #261 Add author to RSS-Feed and other improvements
- [system] #486 Replace all $_REQUEST with $_POST for a consistant access
- [system] #489 Add system user to database and assign him to new user id fields in existing tables
- [system] #485 Rewrite language class to use it with sessions
- [system] #488 Rewrite session class to store objects in class
- [system] #472 Outsource queries from modules in classes
- [system] #465 New PHP class to display the module menu

### Fixed
- [system] #633 Index structure in some tables was not recommendable
- [system] #689 When data was send on Ajax then UTF8 encoding was not enforced
- [system] #690 Session database field was to short for some systems
- [system] #708 Error when navigate back after successful login
- [system] #709 Some modules do not have "thead" element in tables
- [events] #686 Maximum number of participants was not updated in infobox
- [events] #695 All dates were shown as "all day" in notification eMail
- [events] #696 ical export wasn't declared as utf8
- [events] #700 ical whole day events are ending to early
- [downloads] #706 Revison of file name extension handling
- [guestbook] #703 Wrong comment count was shown when deleting the last comment of an entry
- [lists] #691 Error when an exact age was selected in the conditions
- [lists] #692 Wrong date was shown when search for greater or minor age
- [lists] #693 Error was shown when searching for different ages and combine them with AND /​ OR
- [mail] #705 Selectbox for writing mail to former members won't show up when no admin
- [mail] #707 Mail text does not show role membership status of recipients
- [organization] #687 After organization preferences was saved always the default organization was set
- [organization] #702 Wrong organization was assigned when using auto login
- [profile] #684 Remove Windows Live from installer
- [registration] #680 Wrong page was shown when create a new user from registration and don't had right to edit users
- [registration] #685 Country was set to default if registration was accepted
