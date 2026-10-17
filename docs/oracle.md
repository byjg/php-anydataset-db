---
sidebar_position: 23
---

# Driver: Oracle

There are two drivers to connect to Oracle.

- **OCI8** (`oci8://`): uses the `oci8` extension directly, not PDO.
- **PDO OCI** (`oci://` or `oracle://`): uses the `pdo_oci` extension.

Both need Oracle Instant Client. See
[Installing the PHP database extensions](installing-extensions.md#oracle-oci8-and-pdo-oci).

## OCI8

```text
    oci8://user:pass@server:port/serviceName?parameters
```

The `parameters` can be:

* `protocol`=TCP (default)
* `codepage`=UTF8 (default)
* `conntype`=default|persistent|new
* `session_mode`=OCI_DEFAULT|OCI_SYSDBA|OCI_SYSOPER

### conntype

* If conntype = default will call the `oci_connect()` command;
* If conntype = new will call the `oci_new_connect()` command;
* If conntype = persistent will call the `oci_pconnect()` command;

### session_mode

The `OCI_DEFAULT`, `OCI_SYSDBA` AND `OCI_SYSOPER` are the PHP Constants 
and they are `0`, `2` and `4` respectively;

## PDO OCI

```text
    oci://user:pass@server:port/serviceName?parameters
```

`oracle://` is an alias of `oci://`.

The `parameters` can be:

* `protocol`=TCP (default)
* `codepage`=AL32UTF8 (default), sent as the `charset` of the PDO connection string

:::caution Character set
`pdo_oci` may ignore the `charset` of the connection string and return `?` for every non-ASCII
character. Set `NLS_LANG=.AL32UTF8` in the environment of the PHP process; it cannot be set
with `putenv()`. See
[Installing the PHP database extensions](installing-extensions.md#oracle-oci8-and-pdo-oci).
:::

## Notes

* A trailing `;` is removed from the statement, because Oracle rejects it.
* The port defaults to `1521`.
