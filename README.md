# Admidio

Admidio is a free open source user management system for websites of
organizations and groups. The system has a flexible role model so that
it’s possible to reflect the structure and permissions of your organization.
You can create an individual profile for your members by adding or removing
fields. Additional to these functions the system contains several modules
like member lists, event manager, guestbook, photo album or download area.

## Table of contents

- [Features](#features)
- [Installation](#installation)
- [Update](#update)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [Donation](#donation)
- [Copyright and License](#copyright-and-license)
- [Credits](#credits)

## Features

- create roles that reflects the structure of your organization
- add custom fields to the user profiles
- create individual membership lists of your roles
- publish all your events online and let the members participate
- create photo albums and let the users send ecards
- export all lists to csv, excel or pdf
- import users from csv
- send html mails to your roles

## Installation

You can install Admidio on your webspace if the script language [PHP](https://secure.php.net/) in version 5.3.7
or higher is available. Admidio also needs a [MySQL](https://www.mysql.com/) (version 5.0 or higher) or
[PostgreSQL](http://www.postgresql.org/) (version 9.0 or higher) database to run.

For a successful installation please follow our [online instructions](http://www.admidio.org/dokuwiki/doku.php?id=en:2.0:installation).
There we describe step by step the installation process.

## Update

Please follow our [online instructions](http://www.admidio.org/dokuwiki/doku.php?id=en:2.0:update) for a successful
update to a new version of Admidio.

Short update description:

- Delete the folder adm_program of the previous version.
- Copy folder adm_program from the new version to this place.
- Call the index.php in your Admidio folder and start the update.
- Update all installed plugins in the folder adm_plugins.

If you perform an update from version 2.x to version 3.x please read our [special update instructions](http://www.admidio.org/dokuwiki/doku.php?id=en:2.0:update_von_2.x_auf_3.x).

## Contributing

There are several ways how you can contribute to Admidio.

The easiest way to support us will be [our forum](http://forum.admidio.org). There you can help other
users with your knowledge and answer questions. Try to reproduce errors
that other users report or give hints to some problems.

Another way is [our documentation](http://admidio.org/dokuwiki/doku.php?id=en:2.0:index). We want to deliver a helpful documentation to
our users. But there is a lot work to do. You see our current state in the wiki.
Just ask us for write permissions in the wiki and you can start.

We always need persons who [translate our software Admidio](http://admidio.org/dokuwiki/doku.php?id=en:entwickler:uebersetzen) into another
language or just update an existing language to the current version.
We also need people you translate [our documentation](http://admidio.org/dokuwiki/doku.php?id=en:2.0:index) into english.

If you have knowledge in PHP programming and know something about HTML, CSS and
JavaScript then you can start to help us to improve the software Admidio.
You will find our software at GitHub. The handling with GitHub and branches
is described in [our wiki](http://admidio.org/dokuwiki/doku.php?id=en:entwickler:fehlerkorrekturen_in_mehreren_versionen).

So if you find yourself in one of the above points then we invite you
to join our team and help to improve Admidio to one of the best free
membership software.

## Changelog

Please visit our [changelog](http://admidio.org/index.php?page=changelog) for detail information about the bugfixes and enhancements in each version.

## Donation

If you like the software and our project then we are happy if you [donate
some money](http://www.admidio.org/index.php?page=donate) to the project.

## Copyright and License

Admidio is release under the [GNU General Public License 2](https://github.com/Admidio/admidio/blob/master/LICENSE.txt). You are
free to use, modify and distribute this software, as long as the copyright header
within the html page and source code is left intact. If you want to support
us we are happy if you don't remove the link to Admidio within the login
dialog.

## Credits

Admidio contains several scripts, icons and modules of other projects.
We want to thank the people behind these projects for contributing
and sharing great software.

- [Admidio Team](https://github.com/Admidio/admidio/graphs/contributors): The core developers of this project
- [Icon Bibliothek famfamfam, Silk Icons](http://www.famfamfam.com/): Icon Bibliothek
- [Tango Desktop Project](http://tango.freedesktop.org/): Icon Bibliothek Tango
- [Bootstrap](https://getbootstrap.com/): HTML, CSS and JS framework
- [jQuery](https://jquery.com/): JavaScript-Library
- [CKEditor](http://ckeditor.com/): Javascript-Editor
- [PHPMailer](https://github.com/PHPMailer/PHPMailer): Email sending library for PHP
- [Datatables](https://www.datatables.net/): Table plugin for jQuery
- [Select2](https://select2.github.io/): jQuery replacement for select boxes
- [jQuery-File-Upload](https://blueimp.github.io/jQuery-File-Upload/): jQuery file upload plugin
- [Bootstrap-Datepicker](https://github.com/eternicode/bootstrap-datepicker): Datepicker for bootstrap 3
- [Lightbox](https://ashleydw.github.io/lightbox/): Lightbox for bootstrap 3
- [Moment](http://momentjs.com/): Parse, validate, manipulate, and display dates in JavaScript
- [James Heinrich](http://www.silisoftware.com/): backupDB
- [htmLawed](http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/): PHP code to purify & filter HTML
- [phpass](http://www.openwall.com/phpass/): Portable PHP password hashing framework
- [Html5Shiv](https://github.com/aFarkas/html5shiv): Enable Html5 for older browsers
- [Respond](https://github.com/scottjehl/Respond): Polyfill for min/max-width CSS3 Media Queries

Copyright (c) 2004 - 2015 The Admidio Team
