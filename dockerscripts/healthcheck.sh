#!/usr/bin/env bash

# set -euf -o pipefail
set -e  # If a command fails, set -e will make the whole script exit
set -u  # Treat unset variables as an error, and immediately exit.
set -f  # Disable filename expansion (globbing) upon seeing *, ?, etc.
set -o pipefail  # causes a pipeline (for example, curl -s https://sipb.mit.edu/ | grep foo) to produce a failure return code if any command errors.


_init () {
    scheme="http://"
    address="localhost:8080"
    resource="/index.php"
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
        # Get the http response code
        http_response=$(curl --noproxy "*" -s -k --location -o /dev/null -w "%{http_code}" -X GET ${scheme}${address}${resource})

        # If http_response is 200 - server is up.
        if [ "${http_response}" != "200" ]; then
            exit 1
        fi
    fi
}

_init && healthcheck_main
