# local image build command:
#   IMAGE_NAME="yourUsername/admidio:v4.3.0" ./hooks/build


# https://hub.docker.com/_/ubuntu/tags?name=noble
FROM ubuntu:24.04

# Build-time metadata as defined at http://label-schema.org
ARG ADMIDIO_BUILD_DATE
ARG ADMIDIO_VCS_REF
ARG ADMIDIO_VERSION
LABEL org.label-schema.build-date="${ADMIDIO_BUILD_DATE}" \
      org.label-schema.name="Admidio" \
      org.label-schema.description="Admidio is a free open source user management system for websites of organizations and groups." \
      org.label-schema.license="GPL-2.0" \
      org.label-schema.url="https://www.admidio.org/" \
      org.label-schema.vcs-ref="${ADMIDIO_VCS_REF}" \
      org.label-schema.vcs-url="https://github.com/Admidio/admidio" \
      org.label-schema.vendor="Admidio" \
      org.label-schema.version="${ADMIDIO_VERSION}" \
      org.label-schema.schema-version="1.0"

# set arguments and enviroments
ARG TZ
ENV TZ="${TZ}"
ENV APACHE_DOCUMENT_ROOT="/opt/app-root/src"

# Stop dpkg-reconfigure tzdata from prompting for input
ENV DEBIAN_FRONTEND=noninteractive

# install package updates and required dependencies
RUN apt-get update && \
    apt-get full-upgrade -y && \
    apt-get install -y --no-install-recommends \
        apache2 \
        ca-certificates \
        cron \
        curl \
        imagemagick \
        libapache2-mod-php8.3 \
        php-bcmath \
        php-bz2 \
        php-cli \
        php-common \
        php-curl \
        php-db \
        php-gd \
        php-gmp \
        php-igbinary \
        php-imagick \
        php-intl \
        php-json \
        php-ldap \
        php-mbstring \
        php-mysql \
        php-pear \
        php-pgsql \
        php-readline \
        php-soap \
        php-tidy \
        php-uploadprogress \
        php-xml \
        php-xmlrpc \
        php-zip \
        postfix \
        rsync \
        tzdata \
        && \
    apt-get -y autoremove && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    sed -ri -e 's|^ErrorLog .*$|ErrorLog /dev/stderr|' /etc/apache2/apache2.conf && \
    sed -ri -e '/^ErrorLog /a TransferLog /dev/stdout' /etc/apache2/apache2.conf && \
    sed -ri -e '/ErrorLog /d' -e '/CustomLog /d' /etc/apache2/sites-available/*.conf

COPY . ${APACHE_DOCUMENT_ROOT}/
WORKDIR ${APACHE_DOCUMENT_ROOT}

RUN mkdir ${APACHE_DOCUMENT_ROOT}/provisioning && \
    \
    cp -a ${APACHE_DOCUMENT_ROOT}/adm_my_files ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/adm_plugins ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/install ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/languages ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/libs ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/modules ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/rss ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/src ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/system ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    cp -a ${APACHE_DOCUMENT_ROOT}/themes ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    \
    rm -rf ${APACHE_DOCUMENT_ROOT}/adm_my_files && \
    rm -rf ${APACHE_DOCUMENT_ROOT}/adm_plugins && \
    rm -rf ${APACHE_DOCUMENT_ROOT}/themes && \
    \
    echo -n ${ADMIDIO_VERSION} > /opt/app-root/src/.admidio_image_version

VOLUME ["/opt/app-root/src/adm_my_files", "/opt/app-root/src/adm_plugins", "/opt/app-root/src/themes"]

# Latest release
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
# RUN composer update
RUN composer install

HEALTHCHECK --interval=30s --timeout=5s CMD "/opt/app-root/src/dockerscripts/healthcheck.sh"

EXPOSE 8080

CMD ["/opt/app-root/src/dockerscripts/startup.sh"]
