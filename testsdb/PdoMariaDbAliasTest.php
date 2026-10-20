<?php

namespace TestDb;

/**
 * The MySQL tests through "mariadb://", an alias the driver declares in schema(). PdoObj took
 * the PDO driver from the scheme, so the alias asked for an extension "pdo_mariadb" that does not
 * exist.
 */
class PdoMariaDbAliasTest extends PdoMySqlTest
{
    protected string $scheme = 'mariadb';
}
