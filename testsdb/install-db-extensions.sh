#!/bin/sh
# Installs, on the byjg/php images (Alpine), the database extensions that are not Alpine
# packages, so every test in testsdb/ can run. See docs/installing-extensions.md for what each
# step is for.
#
#   install-db-extensions.sh [oracle] [sqlsrv]
#
#   oracle   oci8 and pdo_oci, with Oracle Instant Client
#   sqlsrv   pdo_sqlsrv, with Microsoft ODBC Driver 18
#
# Without arguments it installs both, which is how it runs as a shellscript.download
# post-install script:
#
#   load.sh php-docker -- 8.5 --postinstall testsdb/install-db-extensions.sh
#
# Runs as root inside the image. PHP_VARIANT (php85) comes from the image.
#
# ENV LD_LIBRARY_PATH=/opt/oracle/instantclient
# ENV NLS_LANG=.AL32UTF8

set -eu

IC_URL=https://download.oracle.com/otn_software/linux/instantclient
MSODBC_URL=https://download.microsoft.com/download/fae28b9a-d880-42fd-9b98-d779f0fdd77f/msodbcsql18_18.5.1.1-1_amd64.apk
BUILD_DEPS="${PHP_VARIANT}-dev ${PHP_VARIANT}-pear build-base unixodbc-dev unzip"

install_oracle() {
    # Instant Client is built for glibc; gcompat is the compatibility layer for musl
    apk add --no-cache libaio libnsl gcompat
    ln -sf /usr/lib/libnsl.so.3 /usr/lib/libnsl.so.1

    mkdir -p /opt/oracle
    cd /opt/oracle
    curl -fsSL -o basic.zip "$IC_URL/instantclient-basiclite-linuxx64.zip"
    curl -fsSL -o sdk.zip "$IC_URL/instantclient-sdk-linuxx64.zip"
    unzip -q -o basic.zip
    unzip -q -o sdk.zip
    rm -rf basic.zip sdk.zip META-INF
    ln -sfn "$(ls -d /opt/oracle/instantclient_*)" /opt/oracle/instantclient

    echo "instantclient,/opt/oracle/instantclient" | pecl install oci8
    echo "instantclient,/opt/oracle/instantclient" | pecl install pdo_oci
    echo "extension=oci8" > "/etc/${PHP_VARIANT}/conf.d/50_oci8.ini"
    echo "extension=pdo_oci" > "/etc/${PHP_VARIANT}/conf.d/51_pdo_oci.ini"

    EXTENSIONS="$EXTENSIONS oci8 PDO_OCI"
}

install_sqlsrv() {
    apk add --no-cache unixodbc
    curl -fsSL -o /tmp/msodbcsql18.apk "$MSODBC_URL"
    apk add --allow-untrusted /tmp/msodbcsql18.apk
    rm -f /tmp/msodbcsql18.apk

    pecl install pdo_sqlsrv
    echo "extension=pdo_sqlsrv" > "/etc/${PHP_VARIANT}/conf.d/52_pdo_sqlsrv.ini"

    EXTENSIONS="$EXTENSIONS pdo_sqlsrv"
}

[ $# -gt 0 ] || set -- oracle sqlsrv

for target in "$@"; do
    case "$target" in
        oracle|sqlsrv) ;;
        *) echo "Unknown target '$target'. Use: oracle, sqlsrv" >&2; exit 1 ;;
    esac
done

EXTENSIONS=""
apk add --no-cache curl
apk add --no-cache $BUILD_DEPS

for target in "$@"; do
    "install_$target"
done

apk del $BUILD_DEPS
rm -rf /tmp/pear

# Fail the install if an extension did not load
for ext in $EXTENSIONS; do
    LD_LIBRARY_PATH=/opt/oracle/instantclient php -m | grep -qix "$ext" || { echo "Extension not loaded: $ext" >&2; exit 1; }
done
