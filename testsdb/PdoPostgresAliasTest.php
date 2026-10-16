<?php

namespace TestDb;

/**
 * The PostgreSQL tests through "postgres://", an alias the driver declares in schema(). PdoObj took
 * the PDO driver from the scheme, so the alias asked for an extension "pdo_postgres" that does not
 * exist.
 */
class PdoPostgresAliasTest extends PdoPostgresTest
{
    protected string $scheme = 'postgres';
}
