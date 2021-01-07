# local image build command:
#   docker build --rm --force-rm --no-cache -f Dockerfile -t yourUsername/admidio:v4.0.3 .


# Docker Container Example:
# docker run --name Admidio \
#   --memory="1024m" --cpu-quota="80000" --cpuset-cpus="0-1" \
#   -p 8095:8080 \
#   --restart=unless-stopped \
#   -v Admidio-files:/opt/app-root/src/adm_my_files \
#   -v Admidio-themes:/opt/app-root/src/adm_themes \
#   -v Admidio-plugins:/opt/app-root/src/adm_plugins \
#   --link Admidio-MariaDB:mysql \
#   admidio/admidio:v4.0.3


# Docker Compose Example with local build (docker-compose.yaml):
# version: '3.9'
#
# services:
#   admidio:
#     build: .
#     image: yourUsername/admidio:v4.0.3
#     restart: unless-stopped
#     container_name: Admidio
#     ports:
#       - "8080:8080"
#     volumes:
#       - "Admidio-files:/opt/app-root/src/adm_my_files"
#       - "Admidio-themes:/opt/app-root/src/adm_themes"
#       - "Admidio-plugins:/opt/app-root/src/adm_plugins"


# Docker Compose Example (docker-compose.yaml):
# version: '3.9'
#
# services:
#   mysql:
#     restart: always
#     image: mariadb:10.5.8
#     contaner_name: Admidio-MariaDB
#     volumes:
#       - "Admidio-MariaDB-confd:/etc/mysql/conf.d"
#       - "Admidio-MariaDB-data:/var/lib/mysql"
#     ports:
#       - 3306:3306
#     environment:
#       - MYSQL_ROOT_PASSWORD=secret-password
#       # mit diesen 3 zusätzlichen Zeilen wird eine Datenbank und Benutzer erstellt.
#       - MYSQL_DATABASE=admidio
#       - MYSQL_USER=admidio
#       - MYSQL_PASSWORD=secret-password
#
#   admidio:
#     restart: always
#     image: admidio/admidio:v4.0.3
#     contaner_name: Admidio
#     depends_on:
#       - mysql
#     volumes:
#       - Admidio-files:/opt/app-root/src/adm_my_files
#       - Admidio-themes:/opt/app-root/src/adm_themes
#       - Admidio-plugins:/opt/app-root/src/adm_plugins
#     ports:
#       - 8080:8080
#     environment:
#       - ADMIDIO_DB_HOST: "mysql:3306"
#       - ADMIDIO_DB_NAME: "admidio"
#       - ADMIDIO_DB_USER: "admidio"
#       - ADMIDIO_DB_PASSWORD: "secret-password"
#       # ServerName for Apache
#       - HOSTNAME: "admidio.osbg.at"


# https://catalog.redhat.com/software/containers/ubi8/php-74/5f521244e05bbcd88f128b63
FROM registry.access.redhat.com/ubi8/php-74:latest
MAINTAINER Stefan Schatz


# set arguments and enviroments
ARG TZ="Europe/Vienna"
ENV TZ="${TZ}"
ENV APACHECONF="/etc/httpd/conf.d"
ENV PROV="provision"

# ubi8 configuration
# HTTPD_START_SERVERS: The StartServers directive sets the number of child server processes created on startup. / Default: 8
ARG HTTPD_START_SERVERS="8"
ENV HTTPD_START_SERVERS="${HTTPD_START_SERVERS}"
# DOCUMENTROOT: Path that defines the DocumentRoot for your application (ie. /public) / Default: /
ARG DOCUMENTROOT="/"
ENV DOCUMENTROOT="${DOCUMENTROOT}"
# PHP_MEMORY_LIMIT: Memory Limit / Default: 128M
ARG PHP_MEMORY_LIMIT="128M"
ENV PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT}"
# ERROR_REPORTING: Informs PHP of which errors, warnings and notices you would like it to take action for / Default: E_ALL & ~E_NOTICE
ARG ERROR_REPORTING="E_ALL & ~E_NOTICE"
ENV ERROR_REPORTING="${ERROR_REPORTING}"
# DISPLAY_ERRORS: Controls whether or not and where PHP will output errors, notices and warnings / Default: ON
ARG DISPLAY_ERRORS="OFF"
ENV DISPLAY_ERRORS="${DISPLAY_ERRORS}"
# DISPLAY_STARTUP_ERRORS: Cause display errors which occur during PHP's startup sequence to be handled separately from display errors / Default: OFF
ARG DISPLAY_STARTUP_ERRORS="OFF"
ENV DISPLAY_STARTUP_ERRORS="${DISPLAY_STARTUP_ERRORS}"
# TRACK_ERRORS: Store the last error/warning message in $php_errormsg (boolean) / Default: OFF
ARG TRACK_ERRORS="OFF"
ENV TRACK_ERRORS="${TRACK_ERRORS}"
# HTML_ERRORS: Link errors to documentation related to the erro / Default: ON
ARG HTML_ERRORS="ON"
ENV HTML_ERRORS="${HTML_ERRORS}"


USER root

# install package updates and required dependencies
RUN yum update -y --setopt=tsflags=nodocs && \
    yum reinstall -y tzdata && \
    yum -y clean all --enablerepo='*'

COPY . .

RUN sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 16G/g" /etc/php.ini
RUN sed -i "s/post_max_size = 8M/post_max_size = 16G/g" /etc/php.ini
RUN sed -i "s/#ServerName.*/ServerName \$\{HOSTNAME\}/g" /etc/httpd/conf/httpd.conf

RUN chown -R default:root .

VOLUME ["/opt/app-root/src/adm_my_files", "/opt/app-root/src/adm_themes", "/opt/app-root/src/adm_plugins"]

# TODO: add HEALTHCHECK

USER default
CMD ["/usr/libexec/s2i/run"]
