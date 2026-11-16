#!/usr/bin/env bash

# set -euf -o pipefail
set -e  # If a command fails, set -e will make the whole script exit
set -u  # Treat unset variables as an error, and immediately exit.
set -f  # Disable filename expansion (globbing) upon seeing *, ?, etc.
set -o pipefail  # causes a pipeline (for example, curl -s https://sipb.mit.edu/ | grep foo) to produce a failure return code if any command errors.


_init () {
    scheme="http://"
    address="localhost:8080"
    resource="/dockerscripts/healthcheck.sh"
    config="${ADMIDIO_CONFIG:-/opt/app-root/src/adm_my_files/config.php}"
    start=$(stat -c "%Y" /proc/1)
}

healthcheck_main () {
    # In distributed environment like Swarm, traffic is routed
    # to a container only when it reports a `healthy` status. So, we exit
    # with 0 to ensure healthy status till distributed Minio starts (120s).
    #
    # Refer: https://github.com/moby/moby/pull/28938#issuecomment-301753272
    if [ $(( $(date +%s) - start )) -lt 30 ]; then
        exit 0
    else
        # If the optional health endpoint is enabled (also in config.php, which is not the case on a fresh
        # container that is installed via the web installer), it also checks the database and the data folder.
        # Otherwise the static file is requested as before.
        if [ "${ADMIDIO_HEALTH_ENDPOINT:-}" = "1" ] && grep -qsE '^\$gHealthEndpoint[[:space:]]*=[[:space:]]*true' "${config}"; then
            # No --location: a redirect is a misconfiguration and must not receive the token.
            # The token is passed via stdin so that it doesn't show up in the process list.
            if [ "${ADMIDIO_HEALTH_TOKEN:-}" != "" ]; then
                http_response=$(printf 'X-Health-Token: %s\n' "${ADMIDIO_HEALTH_TOKEN}" | curl --noproxy "*" -s -k -H @- -o /dev/null -w "%{http_code}" -X GET ${scheme}${address}/system/health.php)
            else
                http_response=$(curl --noproxy "*" -s -k -o /dev/null -w "%{http_code}" -X GET ${scheme}${address}/system/health.php)
            fi
        else
            # Get the http response code
            http_response=$(curl --noproxy "*" -s -k --location -o /dev/null -w "%{http_code}" -X GET ${scheme}${address}${resource})
        fi

        # If http_response is 200 - server is up.
        if [ "${http_response}" != "200" ]; then
            exit 1
        fi
    fi
}

_init && healthcheck_main
