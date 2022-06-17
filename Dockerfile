# local image build command:
#   IMAGE_NAME="yourUsername/admidio:v4.0.4" ./hooks/build


# https://catalog.redhat.com/software/containers/ubi8/php-74/5f521244e05bbcd88f128b63
FROM registry.access.redhat.com/ubi8/php-74:latest
MAINTAINER Stefan Schatz

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

USER root

# install package updates and required dependencies
RUN yum update -y --allowerasing --skip-broken --nobest --setopt=tsflags=nodocs && \
    yum install -y postfix && \
    yum reinstall -y tzdata && \
    yum remove -y nodejs nodejs-docs npm && \
    yum autoremove -y && \
    rm -rf /usr/lib/node_modules && \
    yum -y clean all --enablerepo='*'

COPY . .
RUN mkdir provisioning && cp -a adm_plugins adm_themes adm_my_files provisioning/ && rm -rf adm_plugins adm_themes adm_my_files

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
      rm -rf /opt/app-root/src/.cache/trivy ; \
    fi


VOLUME ["/opt/app-root/src/adm_my_files", "/opt/app-root/src/adm_themes", "/opt/app-root/src/adm_plugins"]

HEALTHCHECK --interval=30s --timeout=5s CMD "/opt/app-root/src/dockerscripts/healthcheck.sh"

CMD ["/opt/app-root/src/dockerscripts/startup.sh"]
