<?php

namespace Test;

use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\PdoObj;
use ByJG\Util\Uri;
use PHPUnit\Framework\TestCase;

class DbPdoUriTest extends TestCase
{

    /**
     * @dataProvider providerPdoConnectionString
     */
    public function testPdoConnectionString(Uri $uri, string $expected)
    {
        $dbPdoUri = new PdoObj($uri);
        $this->assertEquals($expected, $dbPdoUri->getConnStr());
    }

    public function providerPdoConnectionString()
    {
        return [
            [
                new Uri("mysql://user:pass@localhost/dbname"),
                "mysql:host=localhost;dbname=dbname",
                "user",
                "pass"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname"),
                "mysql:host=localhost;dbname=dbname;port=3306"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname?charset=utf8"),
                "mysql:host=localhost;dbname=dbname;port=3306;charset=utf8"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname?charset=utf8&other=1"),
                "mysql:host=localhost;dbname=dbname;port=3306;charset=utf8;other=1"
            ],
            [
                new Uri("pdo://mysql?host=localhost&dbname=dbname"),
                "mysql:host=localhost;dbname=dbname"
            ],
            // pdo://john:mypass@firebird?Database=DATABASE.GDE&DataSource=localhost&Port=3050
            [
                new Uri("pdo://mysql?host=localhost&port=3306&dbname=dbname"),
                "mysql:host=localhost;port=3306;dbname=dbname"
            ],
            [
                new Uri("pdo://user:pass@mysql?host=localhost&port=3306&dbname=dbname&charset=utf8"),
                "mysql:host=localhost;port=3306;dbname=dbname;charset=utf8"
            ],
            [
                new Uri("pdo://mysql?host=localhost&port=3306&dbname=dbname&charset=utf8&other=1"),
                "mysql:host=localhost;port=3306;dbname=dbname;charset=utf8;other=1"
            ],
            [
                new Uri("mysql://localhost/dbname?charset=utf8&other=1&" . DbPdoDriver::DONT_PARSE_PARAM . "=1"),
                "mysql:host=localhost;dbname=dbname;charset=utf8;other=1"
            ],
            [
                new Uri("sqlite:///path/to/db.sqlite"),
                "sqlite:/path/to/db.sqlite"
            ],
            [
                new Uri("pdo://mysql?" . DbPdoDriver::UNIX_SOCKET . "=/path/to/socket&dbname=dbname"),
                "mysql:unix_socket=/path/to/socket;dbname=dbname"
            ],
            [
                new Uri("mysql:///dbname?" . DbPdoDriver::UNIX_SOCKET . "=/path/to/socket"),
                "mysql:dbname=dbname;unix_socket=/path/to/socket"
            ],
            [
                new Uri("literal://sqlite?connection=:memory:"),
                "sqlite::memory:"
            ],
            [
                new Uri("literal://mysql?connection=" . urlencode("dbname=dbname;host=localhost")),
                "mysql:dbname=dbname;host=localhost"
            ],
        ];
    }

    /**
     * @dataProvider providerUriConnectionString
     */
    public function testUriFromPdoConnectionString(Uri $expected, string $connStr, string $user = "", string $pass = "")
    {
       $this->assertEquals($expected, PdoObj::getUriFromPdoConnStr($connStr, $user, $pass));
    }

    public function providerUriConnectionString()
    {
        return [
            [
                new Uri("mysql://user:pass@localhost/dbname"),
                "mysql:host=localhost;dbname=dbname;",
                "user",
                "pass"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname"),
                "mysql:host=localhost;dbname=dbname;port=3306;",
                "user",
                "pass"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname?charset=utf8"),
                "mysql:host=localhost;dbname=dbname;port=3306;charset=utf8",
                "user",
                "pass"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname?charset=utf8&other=1"),
                "mysql:host=localhost;dbname=dbname;port=3306;charset=utf8;other=1",
                "user",
                "pass"
            ],
            [
                new Uri("mysql://localhost/dbname"),
                "mysql:host=localhost;dbname=dbname"
            ],
            // pdo://john:mypass@firebird?Database=DATABASE.GDE&DataSource=localhost&Port=3050
            [
                new Uri("mysql://localhost:3306/dbname"),
                "mysql:host=localhost;port=3306;dbname=dbname"
            ],
            [
                new Uri("mysql://user:pass@localhost:3306/dbname?charset=utf8"),
                "mysql:host=localhost;port=3306;dbname=dbname;charset=utf8",
                "user",
                "pass"
            ],
            [
                new Uri("mysql://localhost:3306/dbname?charset=utf8&other=1"),
                "mysql:host=localhost;port=3306;dbname=dbname;charset=utf8;other=1"
            ],
            [
                new Uri("mysql://localhost/dbname?charset=utf8&other=1"),
                "mysql:host=localhost;dbname=dbname;charset=utf8;other=1"
            ],
            [
                new Uri("sqlite:///path/to/db.sqlite"),
                "sqlite:/path/to/db.sqlite"
            ],
            [
                new Uri("mysql:///dbname?" . DbPdoDriver::UNIX_SOCKET . "=/path/to/socket"),
                "mysql:unix_socket=/path/to/socket;dbname=dbname"
            ],
            [
                new Uri("mysql://localhost/dbname"),
                "mysql:dbname=dbname;host=localhost"
            ],
        ];
    }
}