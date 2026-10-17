---
sidebar_position: 27
---

# Installing the PHP database extensions

Each driver needs a PHP extension, and three of them need a vendor client library that no
Linux distribution ships. This page lists what each driver needs and how to install it.

| Driver (scheme)            | PHP extension | Extra client library          | Where it comes from |
|----------------------------|---------------|-------------------------------|---------------------|
| MySQL (`mysql`)            | `pdo_mysql`   | none                          | distribution package |
| PostgreSQL (`pgsql`)       | `pdo_pgsql`   | none                          | distribution package |
| SQLite (`sqlite`)          | `pdo_sqlite`  | none                          | distribution package |
| SQL Server (`dblib`)       | `pdo_dblib`   | FreeTDS (pulled in by the package) | distribution package |
| SQL Server (`sqlsrv`)      | `pdo_sqlsrv`  | Microsoft ODBC Driver 18      | Microsoft download + PECL |
| Oracle (`oci8`)            | `oci8`        | Oracle Instant Client         | Oracle download + PECL |
| Oracle (`oci`, `oracle`)   | `pdo_oci`     | Oracle Instant Client         | Oracle download + PECL |
| Cloudflare D1 (`d1`)       | none          | a PSR-18 HTTP client          | Composer |

Check what is loaded with:

```bash
php -m | grep -i -E "pdo|oci|sqlsrv"
```

:::info Tested with
Everything below was run on the `byjg/php:8.5-cli` image (Alpine Linux, x86_64, PHP 8.5) with
Oracle Instant Client 23 Basic Lite, `oci8` 3.4.1, `pdo_oci` 1.2.0, Microsoft ODBC Driver
18.5.1.1 and `pdo_sqlsrv` 5.13.3. In that image `$PHP_VARIANT` is `php85`. The same versions
work on PHP 8.3 and 8.4. On PHP 8.6 (release candidate) only `oci8` compiles: `pdo_oci` 1.2.0
and `pdo_sqlsrv` 5.13.3 fail to build against it.
:::

## The install script

[`testsdb/install-db-extensions.sh`](https://github.com/byjg/php-anydataset-db/blob/master/testsdb/install-db-extensions.sh)
installs the three extensions that are not distribution packages - `oci8`, `pdo_oci` and
`pdo_sqlsrv` - and is the reference for the exact commands. The sections below explain what it
does and why. It must run as `root`, and it stops with an error if one of the extensions does
not load. Pass `oracle` or `sqlsrv` to install only that one; without arguments it installs both.

There are three ways to run it:

**With the [shellscript.download](https://shellscript.download) PHP wrappers.** The script is
saved for that PHP version and runs again on every install or update of it:

```bash
load.sh php-docker -- 8.5 --postinstall testsdb/install-db-extensions.sh
```

It is copied to `~/.shellscript/php/8.5/postinstall.sh`; delete that file to stop running it.
Do not install the extensions by hand in the `byjg/php:<version>-cli-load` image: the installer
rebuilds it from `byjg/php:<version>-cli` on every run.

**In a `Dockerfile`.** The `byjg/php` images run as the user `app`, so switch to `root` for the
install:

```dockerfile
FROM byjg/php:8.5-cli
USER root
COPY testsdb/install-db-extensions.sh /tmp/install-db-extensions.sh
RUN sh /tmp/install-db-extensions.sh && rm /tmp/install-db-extensions.sh
USER app
ENV LD_LIBRARY_PATH=/opt/oracle/instantclient
ENV NLS_LANG=.AL32UTF8
```

**In a running container**, as `root`, followed by the two environment variables described in
the Oracle section:

```bash
sh testsdb/install-db-extensions.sh
```

## MySQL, PostgreSQL and SQLite

They are distribution packages and are already present in the `byjg/php` images:

```bash
apk add --no-cache ${PHP_VARIANT}-pdo_mysql ${PHP_VARIANT}-pdo_pgsql ${PHP_VARIANT}-pdo_sqlite
```

## SQL Server with Dblib (FreeTDS)

```bash
apk add --no-cache ${PHP_VARIANT}-pdo_dblib
```

## SQL Server with the Microsoft driver (SqlSrv)

Microsoft publishes the ODBC driver for Linux, including an Alpine package. The script:

1. downloads and installs **Microsoft ODBC Driver 18** and the `unixodbc` runtime;
2. compiles **`pdo_sqlsrv`** from PECL against it and enables it;
3. removes the build tools.

Specifics:

- ODBC Driver 18 encrypts by default and rejects a self-signed certificate. The `sqlsrv` driver
  of this library already sends `TrustServerCertificate=true`.
- The package is not signed for Alpine's repositories, hence `apk add --allow-untrusted`.
- The download URL in the script names one driver version. Check the Microsoft site for a
  newer one.

## Oracle (OCI8 and PDO OCI)

Both extensions are compiled from PECL against Oracle Instant Client. `pdo_oci` left the PHP
source tree in PHP 8.4, so it is a PECL package too. The script:

1. installs the libraries the Oracle client needs: `libaio`, `libnsl` and `gcompat`. Instant
   Client is built for glibc and Alpine uses musl, so `gcompat` provides the compatibility
   layer, and `libnsl.so.1` is a symlink to the version Alpine ships;
2. downloads **Oracle Instant Client** - the runtime ("basiclite") and the headers ("sdk") -
   into `/opt/oracle` and links it as `/opt/oracle/instantclient`;
3. compiles **`oci8`** and **`pdo_oci`** from PECL against it and enables them;
4. removes the build tools.

Two environment variables must be set **in the environment of the PHP process**, before PHP
starts:

```bash
export LD_LIBRARY_PATH=/opt/oracle/instantclient
export NLS_LANG=.AL32UTF8
```

The shellscript.download installer reads them from the `# ENV` lines of the script and sets
them in the image; in a `Dockerfile` they are the two `ENV` instructions above.

Specifics:

- **`LD_LIBRARY_PATH`** tells the loader where the Oracle libraries are. Without it PHP prints
  `Unable to load dynamic library 'oci8.so'`.
- **`NLS_LANG` is required by PDO OCI.** `pdo_oci` ignored the `charset` of the connection
  string in our tests and returned `?` for every non-ASCII character; it only honoured
  `NLS_LANG`. The OCI8 driver is not affected: it passes the character set to `oci_connect()`.
- **`NLS_LANG` cannot be set from PHP.** It is read when the extension is loaded, so
  `putenv()` and the `<env>` element of `phpunit.xml` are too late. Set it in the shell, the
  `Dockerfile` (`ENV`), the `environment:` of a compose service or the web server/FPM pool.
- **The format is `LANGUAGE_TERRITORY.CHARSET`.** To give only the character set keep the
  leading dot: `.AL32UTF8`. A bare `AL32UTF8` is read as a language name and the connection
  fails with `ORA-12735`.
- **Basic Lite** supports only Unicode and Western European character sets and English error
  messages. Use `instantclient-basic-linuxx64.zip` instead if you need another one.
- **Alpine is not a platform Oracle supports.** The `gcompat` layer worked for every test of
  this library, but for production prefer a glibc image (Debian, Oracle Linux) where no
  compatibility layer is needed.
- Oracle rejects a statement that ends with `;`. Both drivers strip it.
