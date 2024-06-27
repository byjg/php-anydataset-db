# Driver: Oracle

The Oracle Driver don't use the PHP PDO Driver. Instead, uses the OCI library.

The Oracle Connection String has the following format:


```text
    oci8://user:pass@server:port/serviceName?parameters
```

The `parameters` can be:

* `codepage`=UTF8
* `conntype`=default|persistent|new
* `session_mode`=OCI_DEFAULT|OCI_SYSDBA|OCI_SYSOPER

## conntype

* If conntype = default will call the `oci_connect()` command;
* If conntype = new will call the `oci_new_connect()` command;
* If conntype = persistent will call the `oci_pconnect()` command;

## session_mode

The `OCI_DEFAULT`, `OCI_SYSDBA` AND `OCI_SYSOPER` are the PHP Constants 
and they are `0`, `2` and `4` respectively;