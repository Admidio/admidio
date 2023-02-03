# local image build command:
#   IMAGE_NAME="yourUsername/admidio:v4.2.0" ./hooks/build


# https://hub.docker.com/_/php/tags?page=1&name=-apache-bullseye
FROM php:8.2.1-apache-bullseye

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
ARG ADMIDIO_TRIVY_ENABLED="false"
ENV APACHE_DOCUMENT_ROOT="/opt/app-root/src"


# install package updates and required dependencies
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        postfix \
        libpq-dev \
        zlib1g-dev \
        libfreetype6-dev \
        libjpeg-dev \
        libpng-dev \
        libzip-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql iconv zip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-enable gd


COPY . ${APACHE_DOCUMENT_ROOT}/
WORKDIR ${APACHE_DOCUMENT_ROOT}

RUN mkdir ${APACHE_DOCUMENT_ROOT}/provisioning && \
    cp -a ${APACHE_DOCUMENT_ROOT}/adm_plugins ${APACHE_DOCUMENT_ROOT}/adm_themes ${APACHE_DOCUMENT_ROOT}/adm_my_files ${APACHE_DOCUMENT_ROOT}/provisioning/ && \
    rm -rf ${APACHE_DOCUMENT_ROOT}/adm_plugins ${APACHE_DOCUMENT_ROOT}/adm_themes ${APACHE_DOCUMENT_ROOT}/adm_my_files


# check for vulnerabilities in image with trivy (https://github.com/aquasecurity/trivy)
# search for vulnerability details: https://nvd.nist.gov/vuln/search
# --serverity UNKNOWN,LOW,MEDIUM,HIGH,CRITICAL
RUN if [ "${ADMIDIO_TRIVY_ENABLED}" != "false" ] ; then \
      echo "scan image with trivy (trivy filesystem --exit-code 1 --severity CRITICAL --no-progress /)" && \
      curl -sfL https://raw.githubusercontent.com/aquasecurity/trivy/main/contrib/install.sh | sh -s -- -b /usr/local/bin && \
      trivy filesystem --exit-code 1 --severity CRITICAL --no-progress / && \
      echo "scan image with trivy (trivy filesystem --exit-code 1 --severity MEDIUM,HIGH --no-progress /)" && \
      trivy filesystem --exit-code 0 --severity MEDIUM,HIGH --no-progress / && \
      rm -rf /opt/app-root/src/.cache/trivy ; \
    fi


VOLUME ["/opt/app-root/src/adm_my_files", "/opt/app-root/src/adm_themes", "/opt/app-root/src/adm_plugins"]

HEALTHCHECK --interval=30s --timeout=5s CMD "/opt/app-root/src/dockerscripts/healthcheck.sh"

EXPOSE 8080

CMD ["/opt/app-root/src/dockerscripts/startup.sh"]
