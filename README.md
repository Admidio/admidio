# Admidio: Open-Source Membership Management

Admidio is a free, open-source membership management system for clubs, associations, groups, and organizations. It provides a flexible and self-hosted solution for managing members, organizing groups and roles, and controlling access to information. With customizable profile fields and a powerful permissions system, Admidio adapts to the individual structure and needs of your organization.

Beyond membership management, Admidio offers a wide range of features, including customizable member lists, event management, online registration, inventory management, messaging and email communication, discussion forums, photo galleries, and document and file management. As a Progressive Web App (PWA), Admidio can also be installed on mobile and desktop devices.

Admidio is easy to integrate into existing websites or use as a standalone application. Since it is self-hosted, you retain full control over your data without licensing fees.

Explore Admidio's features in our [online demo](https://www.admidio.org/demo) .

![Admidio Logo](https://www.admidio.org/images/mainpage_flying_icons.png)

[![GitHub issues](https://img.shields.io/github/issues/Admidio/admidio)](https://github.com/Admidio/admidio/issues)
[![GitHub forks](https://img.shields.io/github/forks/Admidio/admidio)](https://github.com/Admidio/admidio/network)
[![GitHub stars](https://img.shields.io/github/stars/Admidio/admidio)](https://github.com/Admidio/admidio/stargazers)
![GitHub top language](https://img.shields.io/github/languages/top/admidio/admidio)
[![GitHub license](https://img.shields.io/github/license/Admidio/admidio)](https://github.com/Admidio/admidio/blob/master/LICENSE)

**Supported Languages:** Bulgarian, Chinese, Czech, Danish, Dutch, English, Estonian, Finnish, French, German, Greek, Hungarian, Indonesian, Italian, Norwegian Bokmål, Polish, Portuguese (Brazil), Portuguese (Portugal), Romanian, Russian, Spanish, Swedish, Tamil, Thai, Ukrainian.

## Key Features

- **Flexible Roles and Groups**: Organize members into roles and groups that reflect your organization's structure.
- **Powerful Permission Management**: Control access to member profiles, modules, and features through role-based permissions.
- **Customizable Member Profiles**: Create and manage individual member profiles with customizable profile fields.
- **Relationship Management**: Define relationships between members, such as spouses or parents and children.
- **Custom Member Lists**: Create, filter, sort, and export member lists with configurable fields.
- **Online Registration**: Allow new members to register online and manage their registration requests.
- **Event Management**: Plan events, manage registrations and participants, and organize schedules and rooms.
- **Inventory Management**: Manage your organization's inventory, lend items to members, and track borrowing history.
- **Messaging and Email**: Send messages and HTML emails to individual members, roles, or groups.
- **Announcements**: Publish news and important information to keep your members informed.
- **Discussion Forum**: Create discussion topics, exchange comments, and organize conversations into categories.
- **Document and File Management**: Organize and share documents and files with role-based access permissions.
- **Photo Galleries and E-Cards**: Create photo albums, share images, and send personalized e-cards.
- **Progressive Web App (PWA)**: Install Admidio on mobile and desktop devices for convenient access.
- **And Much More**: Extend Admidio with plugins and customize it to meet your organization's needs.

## Installation

To install Admidio on your web server, ensure you have PHP 8.2 or higher and either a MariaDB (version 10 or higher), MySQL (version 5.0 or higher) or PostgreSQL (version 11.0 or higher) database available. Follow our [online installation instructions](https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:installation) for a successful setup.

## Update

Keep your Admidio installation up to date by following our [online update instructions](https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:update). Here's a brief update overview if you don't have custom themes installed:

1. Delete all folders and files except "adm_my_files" and "adm_plugins" of the previous version.
2. Copy all folders except "adm_my_files" from the new version.
3. Update the "adm_plugins" folder with the newly delivered plugins.
4. Access the "index.php" in your Admidio folder to initiate the update.

## Docker

Admidio is also available for Docker environments. You can create and use your own Docker image using our Dockerfile. Alternatively, you can use our prebuilt images from Dockerhub. Start an Admidio Docker container with the following command:

```bash
docker run --detach -it --name "Admidio" \
  -p 8080:8080 \
  --restart="unless-stopped" \
  -v "Admidio-files:/opt/app-root/src/adm_my_files" \
  -v "Admidio-plugins:/opt/app-root/src/adm_plugins" \
  -v "Admidio-themes:/opt/app-root/src/themes" \
  -e ADMIDIO_DB_HOST="admidio-mariadb:3306" \
  -e ADMIDIO_DB_NAME="admidio" \
  -e ADMIDIO_DB_USER="admidio" \
  -e ADMIDIO_DB_PASSWORD="my_VerySecureAdmidioUserPassword.01" \
  -e ADMIDIO_ROOT_PATH="https://www.mydomain.at/admidio" \
  admidio/admidio:latest
```

For detailed instructions on using Admidio in a Docker environment, see [README-Docker.md](https://github.com/Admidio/admidio/blob/master/README-Docker.md).

## One-Click Deploy

[![Deploy on RepoCloud](https://d16t0pc4846x52.cloudfront.net/deploylobe.svg)](https://repocloud.io/details/Admidio/)

## Contributing

There are numerous ways to contribute to Admidio:

- **Forum Support**: Share your knowledge and help fellow users in the [forum](https://forum.admidio.org).
- **Documentation**: Contribute to our [documentation](https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:index) and improve user resources.
- **Translations**: Translate Admidio into other languages or update existing translations. You can also translate our [documentation](https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:index) into English.
- **Development**: If you have PHP programming skills and knowledge of HTML, CSS, and JavaScript, you can help improve Admidio. Find our software on GitHub and learn about contributing in our [wiki](https://www.admidio.org/dokuwiki/doku.php?id=en:entwickler:fehlerkorrekturen_in_mehreren_versionen).

If you identify with any of the above roles, we invite you to join our team and contribute to making Admidio one of the best free membership software solutions.

## Support Admidio

Admidio is developed and maintained by dedicated volunteers and is completely free to use. We are committed to keeping Admidio free and open source for everyone.

If you find Admidio useful, please consider supporting the project with a donation. Your contribution helps us cover hosting costs, provide development tools and software, and continue improving Admidio.

Every donation, no matter the amount, is greatly appreciated. You can also choose to support individual developers directly.

**[Support Admidio with a donation](https://www.admidio.org/donate.php)**

Thank you for supporting Admidio and helping us keep the project growing!
