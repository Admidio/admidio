#!/usr/bin/env bash

# set -euf -o pipefail
set -e  # If a command fails, set -e will make the whole script exit
# set -u  # Treat unset variables as an error, and immediately exit.
set -f  # Disable filename expansion (globbing) upon seeing *, ?, etc.
set -o pipefail  # causes a pipeline (for example, curl -s https://sipb.mit.edu/ | grep foo) to produce a failure return code if any command errors.
# set -x

# TODO:
#   - write container config back to db to avoid duplicate configurations or get container config from db to avoid duplicate configurations
#   - redirect to /adm_program/installation after startup, also if conf.php already exists.
#     check for db connection and existing tables instead


# configure apache
echo "[INFO ] configure ServerName in /etc/httpd/conf/httpd.conf"
sed -i "s/#ServerName.*/ServerName \$\{HOSTNAME\}/g" /etc/httpd/conf/httpd.conf

# configure postfix
if [ "${ADMIDIO_MAIL_RELAYHOST}" != "" ]; then
    echo "[INFO ] configure postfix for ADMIDIO_MAIL_RELAYHOST=${ADMIDIO_MAIL_RELAYHOST}"
    if [ "$(sysctl --values net.ipv6.conf.all.disable_ipv6 2>/dev/null)" == "1" ]; then
        echo "[INFO ] configure postfix set inet_protocols to ipv4 since ipv6 is disabled (net.ipv6.conf.all.disable_ipv6=1)"
        postconf -e "inet_protocols = ipv4"
    fi
    postconf -e "inet_interfaces = all"
    postconf -e "relayhost = ${ADMIDIO_MAIL_RELAYHOST}"
fi

# start postfix to allow mail delivery
echo "[INFO ] execute postfix start"
postfix start
echo "[INFO ] execute postfix status"
postfix status
echo "[INFO ] execute mailq"
mailq




# generate admidio config.php
ADMIDIO_CONFIG_TEMPLATE="/opt/app-root/src/adm_my_files/config_example.php"
ADMIDIO_CONFIG="/opt/app-root/src/adm_my_files/config.php"
echo "[INFO ] generate admidio config.php file"
cp --preserve=mode,ownership,timestamps "${ADMIDIO_CONFIG_TEMPLATE}" "${ADMIDIO_CONFIG}"
# chown default.root "${ADMIDIO_CONFIG}"
# chmod 664 "${ADMIDIO_CONFIG}"


# Select your database system for example 'mysql' or 'pgsql'
# $gDbType = 'mysql';
sed -i "s/^\$gDbType.*/\$gDbType = '${ADMIDIO_DB_TYPE:-mysql}';/g" "${ADMIDIO_CONFIG}"

# // Table prefix for Admidio-Tables in database
# // Example: 'adm'
# $g_tbl_praefix = 'adm';
sed -i "s/^\$g_tbl_praefix.*/\$g_tbl_praefix = '${ADMIDIO_DB_TABLE_PRAEFIX:-adm}';/g" "${ADMIDIO_CONFIG}"
#
# // Access to the database of the MySQL-Server
# $g_adm_srv  = 'URL_to_your_MySQL-Server';    // Server
if [ "${ADMIDIO_DB_HOST}" != "" ]; then
    sed -i "s/^\$g_adm_srv.*/\$g_adm_srv = '${ADMIDIO_DB_HOST%%:*}';/g" "${ADMIDIO_CONFIG}"
else
    sed -i "s/^\$g_adm_srv.*/\$g_adm_srv = 'localhost';/g" "${ADMIDIO_CONFIG}"
fi

# $g_adm_port = null;                          // Port
if [ "${ADMIDIO_DB_PORT}" != "" ]; then
    sed -i "s/^\$g_adm_port.*/\$g_adm_port = ${ADMIDIO_DB_PORT};/g" "${ADMIDIO_CONFIG}"
elif [ "${ADMIDIO_DB_HOST}" != "" ]; then
    sed -i "s/^\$g_adm_port.*/\$g_adm_port = ${ADMIDIO_DB_HOST##*:};/g" "${ADMIDIO_CONFIG}"
else
    sed -i "s/^\$g_adm_port.*/\$g_adm_port = null;/g" "${ADMIDIO_CONFIG}"
fi
# $g_adm_db   = 'Databasename';                // Database
sed -i "s/^\$g_adm_db.*/\$g_adm_db = '${ADMIDIO_DB_NAME:-admidio}';/g" "${ADMIDIO_CONFIG}"
# $g_adm_usr  = 'Username';                    // User
sed -i "s/^\$g_adm_usr.*/\$g_adm_usr = '${ADMIDIO_DB_USER:-admidio}';/g" "${ADMIDIO_CONFIG}"
# $g_adm_pw   = 'Password';                    // Password
sed -i "s/^\$g_adm_pw.*/\$g_adm_pw = '${ADMIDIO_DB_PASSWORD:-admidio}';/g" "${ADMIDIO_CONFIG}"  # FIXME: where to get password?

# // URL to this Admidio installation
# // Example: 'https://www.admidio.org/example'
# $g_root_path = 'https://www.your-website.de/admidio';
if [ "${ADMIDIO_ROOT_PATH}" != "" ]; then
    sed -i "s#^\$g_root_path.*#\$g_root_path = '${ADMIDIO_ROOT_PATH}';#g" "${ADMIDIO_CONFIG}"
fi

# // Short description of the organization that is running Admidio
# // This short description must correspond to your input in the installation wizard !!!
# // Example: 'ADMIDIO'
# // Maximum of 10 characters !!!
# $g_organization = 'Shortcut';
if [ "${ADMIDIO_ORGANISATION}" != "" ]; then
    sed -i "s/^\$g_organization.*/\$g_organization = '${ADMIDIO_ORGANISATION}';/g" "${ADMIDIO_CONFIG}"
fi

# // The name of the timezone in which your organization is located.
# // This must be one of the strings that are defined here https://www.php.net/manual/en/timezones.php
# // Example: 'Europe/Berlin'
# $gTimezone = 'Europe/Berlin';
if [ "${TZ}" != "" ]; then
    sed -i "s#^\$gTimezone.*#\$gTimezone = '${TZ}';#g" "${ADMIDIO_CONFIG}"
fi

# // If this flag is set = 1 then you must enter your loginname and password
# // for an update of the Admidio database to a new version of Admidio.
# // For a more comfortable and easy update you can set this preference = 0.
# $gLoginForUpdate = 1;
sed -i "s/^\$gLoginForUpdate.*/\$gLoginForUpdate = ${ADMIDIO_LOGIN_FOR_UPDATE:-1};/g" "${ADMIDIO_CONFIG}"

# // Set the preferred password hashing algorithm.
# // Possible values are: 'DEFAULT', 'ARGON2ID', 'ARGON2I', 'BCRYPT', 'SHA512'
# $gPasswordHashAlgorithm = 'DEFAULT';
sed -i "s/^\$gPasswordHashAlgorithm.*/\$gPasswordHashAlgorithm = '${ADMIDIO_PASSWORD_HASH_ALGORITHM:-DEFAULT}';/g" "${ADMIDIO_CONFIG}"


# run apache with php enabled as user default
echo "[INFO ] run apache with php enabled (/usr/libexec/s2i/run)"
/usr/libexec/s2i/run
