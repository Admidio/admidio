#!/usr/bin/env bash

# set -euf -o pipefail
# set -e  # If a command fails, set -e will make the whole script exit
# set -u  # Treat unset variables as an error, and immediately exit.
set -f  # Disable filename expansion (globbing) upon seeing *, ?, etc.
set -o pipefail  # causes a pipeline (for example, curl -s https://sipb.mit.edu/ | grep foo) to produce a failure return code if any command errors.
# set -x


echo "[INFO ] run cron service for php session cleanup (service cron start)"
service cron start


# check for existing admidio directories in docker volumes
for dir in "adm_plugins" "install" "libs" "src" "themes" "adm_my_files" "adm_program" ; do
    if [ ! -d "${dir}" -o "$(find "${dir}" -maxdepth 0 -type d -empty 2>/dev/null)" != "" ]; then
        echo "[INFO ] provisioning missing directory ${dir}"
        cp -a "provisioning/${dir}" .
    fi
done

# fix permissions for docker filesystem volumes
echo "[INFO ] set filesystem permissions (chown -R www-data:root .)"
chown -R www-data:root .

# configure apache
echo "[INFO ] configure Listen port in /etc/apache2/ports.conf"
sed -i "s/^Listen 80$/Listen 8080/g" /etc/apache2/ports.conf
echo "[INFO ] configure VirtualHost port in /etc/apache2/sites-available/000-default.conf"
sed -i "s/^<VirtualHost \*:80>$/<VirtualHost *:8080>/g" /etc/apache2/sites-available/000-default.conf
if [ "${ADMIDIO_ROOT_PATH}" != "" ]; then
    ADMIDIO_HOSTNAME=$(awk -F/ '{print $3}' <<<"${ADMIDIO_ROOT_PATH}")
    echo "[INFO ] configure ServerName in /etc/apache2/sites-available/000-default.conf"
    sed -i "s/[^#]ServerName.+/ServerName ${ADMIDIO_HOSTNAME}/g" /etc/apache2/sites-available/000-default.conf
    sed -i "s/#ServerName www.example.com/ServerName ${ADMIDIO_HOSTNAME}/g" /etc/apache2/sites-available/000-default.conf
fi


# configure postfix
if [ "${ADMIDIO_MAIL_RELAYHOST}" != "" ]; then
    echo "[INFO ] configure postfix for ADMIDIO_MAIL_RELAYHOST=${ADMIDIO_MAIL_RELAYHOST}"
    postconf -e "inet_interfaces = all"
    postconf -e "relayhost = ${ADMIDIO_MAIL_RELAYHOST}"
fi

if [ "$(sysctl --values net.ipv6.conf.all.disable_ipv6 2>/dev/null)" == "1" ]; then
    echo "[INFO ] configure postfix set inet_protocols to ipv4 since ipv6 is disabled (net.ipv6.conf.all.disable_ipv6=1)"
    postconf -e "inet_protocols = ipv4"
fi

# start postfix to allow mail delivery
echo "[INFO ] execute postfix start"
postfix start
echo "[INFO ] execute postfix status"
postfix status
echo "[INFO ] execute mailq"
mailq




# generate admidio config.php
ADMIDIO_CONFIG_TEMPLATE="/opt/app-root/src/provisioning/adm_my_files/config_example.php"
ADMIDIO_CONFIG="/opt/app-root/src/adm_my_files/config.php"
ADMIDIO_IMAGE_VERSION="/opt/app-root/src/.admidio_image_version"
ADMIDIO_INSTALLED_VERSION="/opt/app-root/src/adm_my_files/.admidio_installed_version"


# if nessary environment variables exist, copy config template to config file
if ! ( [ -z "${ADMIDIO_DB_HOST}" ] || ( [ -z "${ADMIDIO_DB_PORT}" ] && [ -z "${ADMIDIO_DB_HOST##*:}" ] ) || [ -z "${ADMIDIO_DB_NAME}" ] || [ -z "${ADMIDIO_DB_USER}" ] || [ -z "${ADMIDIO_DB_PASSWORD}" ] ) ; then
    echo "[INFO ] generate admidio config.php file"
    cp --preserve=mode,ownership,timestamps "${ADMIDIO_CONFIG_TEMPLATE}" "${ADMIDIO_CONFIG}"
fi

if [ -f "${ADMIDIO_CONFIG}" ]; then
    echo "[INFO ] configure admidio config.php file"

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
    sed -i "s/^\$g_adm_pw.*/\$g_adm_pw = '${ADMIDIO_DB_PASSWORD:-admidio}';/g" "${ADMIDIO_CONFIG}"

    # // URL to this Admidio installation
    # // Example: 'https://www.admidio.org/example'
    # $g_root_path = 'https://www.your-website.de/admidio';
    if [ "${ADMIDIO_ROOT_PATH}" != "" ]; then
        sed -i "s#^\$g_root_path.*#\$g_root_path = '${ADMIDIO_ROOT_PATH}';#g" "${ADMIDIO_CONFIG}"
    fi

    if [ "${ADMIDIO_ORGANISATION}" != "" ]; then
        if [ "$(egrep '^\$g_organization' ${ADMIDIO_CONFIG})" != "" ]; then
            sed -i "s/^\$g_organization.*/\$g_organization = '${ADMIDIO_ORGANISATION}';/g" "${ADMIDIO_CONFIG}"
        else
            echo "" >> "${ADMIDIO_CONFIG}"
            echo "# // Short description of the organization that is running Admidio" >> "${ADMIDIO_CONFIG}"
            echo "# // This short description must correspond to your input in the installation wizard !!!" >> "${ADMIDIO_CONFIG}"
            echo "# // Example: 'ADMIDIO'" >> "${ADMIDIO_CONFIG}"
            echo "# // Maximum of 10 characters !!!" >> "${ADMIDIO_CONFIG}"
            echo "\$g_organization = '${ADMIDIO_ORGANISATION}';" >> "${ADMIDIO_CONFIG}"
            echo "" >> "${ADMIDIO_CONFIG}"
        fi
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
else
    echo "[WARNING] admidio config.php file does not exist."
fi

if [ ! -f "${ADMIDIO_INSTALLED_VERSION}" ]; then
    echo "[INFO ] create ADMIDIO_INSTALLED_VERSION file (${ADMIDIO_INSTALLED_VERSION}) with admidio version ($(cat ${ADMIDIO_IMAGE_VERSION} 2>/dev/null))"
    echo -n "$(cat ${ADMIDIO_IMAGE_VERSION} 2>/dev/null)" > "${ADMIDIO_INSTALLED_VERSION}"
fi

if [ "$(cat ${ADMIDIO_INSTALLED_VERSION} 2>/dev/null)" != "$(cat ${ADMIDIO_IMAGE_VERSION} 2>/dev/null)" ]; then
    echo "[INFO ] update admidio installation to image version ($(cat ${ADMIDIO_IMAGE_VERSION} 2>/dev/null)) ..."
    echo "[DEBUG] rsync -a --delete provisioning/adm_program/ adm_program/"
    rsync -a --delete provisioning/adm_program/ adm_program/
    echo "[DEBUG] rsync -a --delete provisioning/install/ install/"
    rsync -a --delete provisioning/install/ install/
    echo "[DEBUG] rsync -a --delete provisioning/libs/ libs/"
    rsync -a --delete provisioning/libs/ libs/
    echo "[DEBUG] rsync -a --delete provisioning/src/ src/"
    rsync -a --delete provisioning/src/ src/
    echo "[DEBUG] rsync -a provisioning/adm_plugins/ adm_plugins/"
    rsync -a provisioning/adm_plugins/ adm_plugins/
    echo "[DEBUG] rsync -a provisioning/themes/ themes/"
    rsync -a provisioning/themes/ themes/
    echo "[DEBUG] rsync -a --delete provisioning/themes/simple/ themes/simple/"
    rsync -a --delete provisioning/themes/simple/ themes/simple/
    echo "[DEBUG] rsync -a --exclude=/config.php --exclude=/.admidio_installed --exclude=/.admidio_installed_version provisioning/adm_my_files/ adm_my_files/"
    rsync -a --exclude="/config.php" --exclude="/.admidio_installed" --exclude="/.admidio_installed_version" provisioning/adm_my_files/ adm_my_files/
    rm -f "${ADMIDIO_INSTALLED_VERSION}"
fi

# run apache with php enabled as user default
echo "[INFO ] run apache config test (apachectl configtest)"
apachectl configtest

echo "[INFO ] run apache with php enabled (apachectl -D FOREGROUND)"
apachectl -D FOREGROUND
