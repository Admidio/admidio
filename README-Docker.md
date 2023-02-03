# Admidio

Admidio is a free open source user management system for websites of
organizations and groups. The system has a flexible role model so that
it’s possible to reflect the structure and permissions of your organization.
You can create an individual profile for your members by adding or removing
fields. Additional to these functions the system contains several modules
like member lists, event manager, guestbook, photo album or a documents & files area.

![logo](https://www.admidio.org/images/mainpage_flying_icons.png)


# Supported tags and respective `Dockerfile` links
![GitHub release (latest by date)](https://img.shields.io/github/v/release/admidio/admidio?label=current%20release&style=plastic)
![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/admidio/admidio?label=latest%20tag&style=plastic)

![Docker Cloud Automated build](https://img.shields.io/docker/cloud/automated/admidio/admidio?style=plastic)
![Docker Cloud Build Status](https://img.shields.io/docker/cloud/build/admidio/admidio?style=plastic)
![Docker Image Size (tag)](https://img.shields.io/docker/image-size/admidio/admidio/latest?style=plastic)
![MicroBadger Layers (tag)](https://img.shields.io/microbadger/layers/admidio/admidio/latest?style=plastic)
![Docker Pulls](https://img.shields.io/docker/pulls/admidio/admidio?style=plastic)

-	[`latest`](https://github.com/Admidio/admidio/blob/master/Dockerfile)
-	[`branch-v4.2`](https://github.com/Admidio/admidio/blob/v4.2/Dockerfile)
- `vN.N.N` (e.g.: `v4.2.0`)
- see https://hub.docker.com/r/admidio/admidio/tags for all available tags

You can find the releasenotes on Github [admidio/releases](https://github.com/Admidio/admidio/releases) and the Docker images on Dockerhub [admidio/admidio](https://hub.docker.com/r/admidio/admidio/).

# How to use this image

## Start a `mariadb` server instance (optional)
Starting a mariadb instance. The admidio database and admidio user will be created automatically.

```bash
docker run --detach -it --name "Admidio-MariaDB" \
  -p 3306:3306 \
  --restart="unless-stopped" \
  -v "Admidio-MariaDB-confd:/etc/mysql/conf.d" \
  -v "Admidio-MariaDB-data:/var/lib/mysql" \
  -e MYSQL_DATABASE="admidio" \
  -e MYSQL_ROOT_PASSWORD="my_VerySecureRootPassword.01" \
  -e MYSQL_USER="admidio" \
  -e MYSQL_PASSWORD="my_VerySecureAdmidioUserPassword.01" \
  mariadb:latest
```

## Start an `admidio` server instance
Starting a admidio instance is quite simple:

```bash
docker run --detach -it --name "Admidio" \
  -p 8080:8080 \
  --restart="unless-stopped" \
  -v "Admidio-files:/opt/app-root/src/adm_my_files" \
  -v "Admidio-themes:/opt/app-root/src/adm_themes" \
  -v "Admidio-plugins:/opt/app-root/src/adm_plugins" \
  --link "Admidio-MariaDB:db" \
  -e ADMIDIO_DB_HOST="db:3306" \
  -e ADMIDIO_DB_NAME="admidio" \
  -e ADMIDIO_DB_USER="admidio" \
  -e ADMIDIO_DB_PASSWORD="my_VerySecureAdmidioUserPassword.01" \
  -e ADMIDIO_ROOT_PATH="https://www.mydomain.at/admidio" \
  admidio/admidio:latest
```

... where `Admidio` is the name you want to assign to your container.

## Start an `admidio` server instance with advanced options
```bash
docker run --detach -it --name "Admidio" \
  --memory="1024m" \
  -p 8080:8080 \
  --restart="unless-stopped" \
  -v "Admidio-files:/opt/app-root/src/adm_my_files" \
  -v "Admidio-themes:/opt/app-root/src/adm_themes" \
  -v "Admidio-plugins:/opt/app-root/src/adm_plugins" \
  --link "Admidio-MariaDB:db" \
  -e ADMIDIO_DB_TYPE="mysql" \
  -e ADMIDIO_DB_HOST="db:3306" \
  -e ADMIDIO_DB_NAME="admidio" \
  -e ADMIDIO_DB_TABLE_PRAEFIX="adm" \
  -e ADMIDIO_DB_USER="admidio" \
  -e ADMIDIO_DB_PASSWORD="my_VerySecureAdmidioUserPassword.01" \
  -e ADMIDIO_MAIL_RELAYHOST="hostname.domain.at:25" \
  -e ADMIDIO_LOGIN_FOR_UPDATE="1" \
  -e ADMIDIO_ORGANISATION="ADMIDIO" \
  -e ADMIDIO_PASSWORD_HASH_ALGORITHM="DEFAULT" \
  -e ADMIDIO_ROOT_PATH="https://www.mydomain.at/admidio" \
  -e TZ="Europe/Vienna" \
  admidio/admidio:latest
```

## General docker options
* **`--restart="unless-stopped"`:** Always start container, except when it was stopped.
* **`--name "Admidio"`:** Docker container name
* **`--memory="1024m"`:** limit ram usage of docker container to 1GB
* **`-p 8080:8080`:** published port (see https://docs.docker.com/config/containers/container-networking/#published-ports)
* **admidio/admidio:latest:** Image name with version tag. We recommend a special version tag to be used instead of latest (e.g.: `admidio/admidio:v4.2.0`)

## Database container link
* **`--link "Admidio-MariaDB:db"`:** connect to docker database server instance.  
`Admidio-MariaDB` = name of the mariadb docker container \
`db` = internal hostname of the database server

See https://docs.docker.com/network/links/. \
Use [MariaDB](https://hub.docker.com/_/mariadb/) or [PostgreSQL](https://hub.docker.com/_/postgres/) images to start database container.


## Volumes
* **`-v "Admidio-files:/opt/app-root/src/adm_my_files"`:** admidio config files and data uploads
* **`-v "Admidio-themes:/opt/app-root/src/adm_themes"`:** admidio themes
* **`-v "Admidio-plugins:/opt/app-root/src/adm_plugins"`:** admidio plugins

See https://docs.docker.com/storage/volumes/ for detailed information.

## Environment Variables
When you start the `admidio` image, you can adjust the configuration of the Admidio instance by passing one or more environment variables on the `docker run` command line.

### `ADMIDIO_DB_TYPE`
Admidio supports in addition to MySQL also PostgreSQL databases. This variable specifies which database engine is to be used.

Possible values: mysql or postgresql

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#gdbtype

### `ADMIDIO_DB_HOST`
A combination of hostname and port of database server.
e.g.: `localhost:3306`

**Database Host:** The name of the database server. This is specified by the hoster. For a local device it could be `localhost` or `127.0.0.1`.

Possible values: `default by Host`

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_adm_srv

**Database Port:** Optionally the port to the database can be specified here. If this parameter is not set, the default port of the database (MySQL: `3306`, PostgreSQL: `5432`) is used.
Possible values: `Specification from the webhoster`

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_adm_port

### `ADMIDIO_DB_NAME`
Name of the database in which the tables Admidio been created. This is specified by the hoster.

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_adm_db

### `ADMIDIO_DB_TABLE_PRAEFIX`
All tables of Admidio in the database get a unified prefix, which is defined here. If you want to set up another Admidio installation in the same database, so each Admidio installation must have its own prefix. About the indication stored here knows Admidio belong which tables to this installation with this configuration file.

Possible values: `adm` or any combination

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_tbl_praefix

### `ADMIDIO_DB_USER`
Name of the user who can log onto the database. This is specified by the hoster.

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_adm_usr

### `ADMIDIO_DB_PASSWORD`
Password of the user who can log on to the database. This is specified by the hoster.

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_adm_pw

### `ADMIDIO_MAIL_RELAYHOST`
Postfix `relayhost` parameter to support mail delivery.
Possible value: `hostname.domain.at:port` (e.g.: hostname.domain.at:25)


### `ADMIDIO_LOGIN_FOR_UPDATE`
This flag ensures that a database update can only be carried out on a new Admidio version, if an administrator has given his credentials in the update script. This function is always active in the default and can be disabled in the config.php via this parameter. For security reasons, we recommend that this feature should always be enabled. In a test systems it is useful to disable the function.

Possible values: `0` (disabled) or `1` (enabled)

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#gloginforupdate

### `ADMIDIO_ORGANISATION`
The abbreviation of the default organization. Are managed through Admidio multiple organizations, each organization could have their own Web space and this variable controls which organization is managed through this Webspace. If several organizations over a Webspace controlled, so this variable indicates the organization, which is pre-selected at login.

Possible values: `Value from table field adm_organizations::org shortname`

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_organization

### `ADMIDIO_PASSWORD_HASH_ALGORITHM`
Optionally, the encryption algorithm for the user passwords can be stored here. This should always point to DEFAULT . Then the best algorithm, supported by all current PHP installations, is taken. SHA512 is not automatically available on all servers, so this option should only be selected if it is ensured that the Admidio version will always run on a server that supports this encryption. The encodings ARGON2ID and ARGON2I are only available from Admidio 4.

Possible values: `​DEFAULT`,​ `​BCRYPT`,​ `​SHA512`, `ARGON2ID`, `ARGON2I​`

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#gpasswordhashalgorithm

### `ADMIDIO_ROOT_PATH`
URL of the current Admidio installation. The URL must not end with a slash or backslash. If the Admidio folder moved, so these variables must be adapted accordingly.

Possible values: https://www.admidio.org/demo or the URL to your Admidio installation
Required version: from 1.0

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#g_root_path

### `TZ`
Specifying the time zone that will be used for this organization. The value corresponds to one of PHP support Time Zone. The time zone is set once during installation and should not thereafter be changed. Must the time zone be nevertheless adjusted later, so the times already recorded are not adjusted to the new time zone.

Possible values: `Europe/Vienna`

see https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:konfigurationsdatei_config.php#gtimezone


## Admidio update

run following steps to update to a newer admidio version.

* get latest version from dockerhub
```bash
docker pull admidio/admidio:latest
```
* stop current running container
```bash
docker stop Admidio
```
* delete existing container
```bash
docker rm Admidio
```
* use the same start command as last
```bash
docker run --detach -it --name "Admidio" \
  -p 8080:8080 \
  --restart="unless-stopped" \
  -v "Admidio-files:/opt/app-root/src/adm_my_files" \
  -v "Admidio-themes:/opt/app-root/src/adm_themes" \
  -v "Admidio-plugins:/opt/app-root/src/adm_plugins" \
  --link "Admidio-MariaDB:db" \
  -e ADMIDIO_DB_HOST="db:3306" \
  -e ADMIDIO_DB_NAME="admidio" \
  -e ADMIDIO_DB_USER="admidio" \
  -e ADMIDIO_DB_PASSWORD="my_VerySecureAdmidioUserPassword.01" \
  -e ADMIDIO_ROOT_PATH="https://www.mydomain.at/admidio" \
  admidio/admidio:latest
```
* look at admidio update page and follow the instructions for the specified version [Admidio Wiki Update](https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:update).


# Docker Compose usage

## Docker Compose Example (docker-compose.yaml)
```yaml
version: '3.9'

services:
  db:
    restart: unless-stopped
    image: mariadb:latest
    container_name: Admidio-MariaDB
    volumes:
      - "Admidio_MariaDB_config:/etc/mysql/conf.d"
      - "Admidio_MariaDB_data:/var/lib/mysql"
    # ports:
    #   - 3306:3306
    environment:
      - MYSQL_ROOT_PASSWORD=password
      - MYSQL_DATABASE=admidio
      - MYSQL_USER=admidio
      - MYSQL_PASSWORD=password

  admidio:
    restart: unless-stopped
    image: admidio/admidio:latest
    container_name: Admidio
    depends_on:
      - db
    volumes:
      - Admidio_files:/opt/app-root/src/adm_my_files
      - Admidio_themes:/opt/app-root/src/adm_themes
      - Admidio_plugins:/opt/app-root/src/adm_plugins
    ports:
      - 8084:8080
    environment:
      - ADMIDIO_DB_TYPE=mysql
      - ADMIDIO_DB_HOST=db:3306
      - ADMIDIO_DB_NAME=admidio
      - ADMIDIO_DB_USER=admidio
      - ADMIDIO_DB_PASSWORD=password
      #- ADMIDIO_DB_TABLE_PRAEFIX=adm
      #- ADMIDIO_MAIL_RELAYHOST=hostname.domain.at:25
      #- ADMIDIO_LOGIN_FOR_UPDATE=1
      #- ADMIDIO_ORGANISATION=TEST02
      #- ADMIDIO_PASSWORD_HASH_ALGORITHM=DEFAULT
      - ADMIDIO_ROOT_PATH=http://localhost:8084
      - TZ=Europe/Vienna

volumes:
  Admidio_MariaDB_config:
  Admidio_MariaDB_data:
  Admidio_files:
  Admidio_themes:
  Admidio_plugins:
```

## Docker Compose Example with local build (docker-compose.yaml)
We also deliver a Dockerfile and you can easily build and use your own docker image.

```yaml
version: '3.9'

services:
  db:
    restart: unless-stopped
    image: mariadb:latest
    container_name: Admidio-MariaDB
    volumes:
      - "Admidio_MariaDB_config:/etc/mysql/conf.d"
      - "Admidio_MariaDB_data:/var/lib/mysql"
    # ports:
    #   - 3306:3306
    environment:
      - MYSQL_ROOT_PASSWORD=password
      - MYSQL_DATABASE=admidio
      - MYSQL_USER=admidio
      - MYSQL_PASSWORD=password

  admidio:
    build: .
    image: yourUsername/admidio:latest
    container_name: Admidio
    restart: unless-stopped
    depends_on:
      - db
    volumes:
      - Admidio_files:/opt/app-root/src/adm_my_files
      - Admidio_themes:/opt/app-root/src/adm_themes
      - Admidio_plugins:/opt/app-root/src/adm_plugins
    ports:
      - 8084:8080
    environment:
      - ADMIDIO_DB_TYPE=mysql
      - ADMIDIO_DB_HOST=db:3306
      - ADMIDIO_DB_NAME=admidio
      - ADMIDIO_DB_USER=admidio
      - ADMIDIO_DB_PASSWORD=password
      #- ADMIDIO_DB_TABLE_PRAEFIX=adm
      #- ADMIDIO_MAIL_RELAYHOST=hostname.domain.at:25
      #- ADMIDIO_LOGIN_FOR_UPDATE=1
      #- ADMIDIO_ORGANISATION=TEST02
      #- ADMIDIO_PASSWORD_HASH_ALGORITHM=DEFAULT
      - ADMIDIO_ROOT_PATH=http://localhost:8084
      - TZ=Europe/Vienna

volumes:
  Admidio_MariaDB_config:
  Admidio_MariaDB_data:
  Admidio_files:
  Admidio_themes:
  Admidio_plugins:
```
