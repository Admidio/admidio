# local image build command:
#   IMAGE_NAME="yourUsername/admidio:v4.0.4" ./hooks/build

# Docker Container Example:
# docker run --name Admidio \
#   --memory="1024m" --cpu-quota="80000" --cpuset-cpus="0-1" \
#   -p 8095:8080 \
#   --restart=unless-stopped \
#   -v Admidio-files:/opt/app-root/src/adm_my_files \
#   -v Admidio-themes:/opt/app-root/src/adm_themes \
#   -v Admidio-plugins:/opt/app-root/src/adm_plugins \
#   --link Admidio-MariaDB:mysql \
#   admidio/admidio:v4.0.4


# Docker Compose Example with local build (docker-compose.yaml):
# version: '3.9'
#
# services:
#   admidio:
#     build: .
#     image: yourUsername/admidio:v4.0.4
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
#     image: admidio/admidio:v4.0.4
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

# Build-time metadata as defined at http://label-schema.org
ARG BUILD_DATE
ARG VCS_REF
ARG VERSION
LABEL org.label-schema.build-date="${BUILD_DATE}" \
      org.label-schema.name="Admidio" \
      org.label-schema.description="Admidio is a free open source user management system for websites of organizations and groups." \
      org.label-schema.url="https://www.admidio.org/" \
      org.label-schema.vcs-ref="${VCS_REF}" \
      org.label-schema.vcs-url="https://github.com/Admidio/admidio" \
      org.label-schema.vendor="Admidio" \
      org.label-schema.version="${VERSION}" \
      org.label-schema.schema-version="1.0"


# set arguments and enviroments
ARG TZ
ENV TZ="${TZ}"
ARG ADMIDIO_TRIVY_ENABLED="false"

USER root

# install package updates and required dependencies
RUN yum update -y --setopt=tsflags=nodocs && \
    yum install -y postfix && \
    yum reinstall -y tzdata && \
    yum -y clean all --enablerepo='*'

COPY . .

# configure php
# RUN sed -i "s/^upload_max_filesize = 2M/upload_max_filesize = 16G/g" /etc/php.ini
# RUN sed -i "s/^post_max_size = 8M/post_max_size = 16G/g" /etc/php.ini

RUN chown -R default:root .

# check for vulnerabilities in image with trivy (https://github.com/aquasecurity/trivy)
# search for vulnerability details: https://nvd.nist.gov/vuln/search
# --serverity UNKNOWN,LOW,MEDIUM,HIGH,CRITICAL
RUN set -euf -o pipefail ; \
    if [ "${ADMIDIO_TRIVY_ENABLED}" != "false" ] ; then \
      echo "scan image with trivy (trivy filesystem --exit-code 1 --severity CRITICAL --no-progress /)" && \
      curl -sfL https://raw.githubusercontent.com/aquasecurity/trivy/main/contrib/install.sh | sh -s -- -b /usr/local/bin && \
      trivy filesystem --exit-code 1 --severity CRITICAL --no-progress / && \
      echo "scan image with trivy (trivy filesystem --exit-code 1 --severity MEDIUM,HIGH --no-progress /)" && \
      trivy filesystem --exit-code 0 --severity MEDIUM,HIGH --no-progress / && \
      trivy --clear-cache --reset ; \
    fi


VOLUME ["/opt/app-root/src/adm_my_files", "/opt/app-root/src/adm_themes", "/opt/app-root/src/adm_plugins"]

HEALTHCHECK --interval=30s --timeout=5s CMD "/opt/app-root/src/dockerscripts/healthcheck.sh"

# USER default
# CMD ["/usr/libexec/s2i/run"]
CMD ["/opt/app-root/src/dockerscripts/startup.sh"]
