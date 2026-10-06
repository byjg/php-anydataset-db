<?php

namespace TestDb;

use ByJG\AnyDataset\Db\Factory;
use PDOException;

class PdoOciTest extends Oci8Test
{
    protected function createInstance()
    {
        if (!extension_loaded('pdo_oci')) {
            $this->testSkipped = true;
            $this->markTestSkipped("PDO OCI extension is not loaded");
        }

        $this->escapeQuote = "''";

        $host = getenv('ORACLE_TEST_HOST');
        if (empty($host)) {
            $host = "127.0.0.1";
        }
        $password = getenv('ORACLE_PASSWORD');
        if (empty($password)) {
            $password = 'password';
        }
        if ($password == '.') {
            $password = "";
        }
        $database = getenv('ORACLE_DATABASE');
        if (empty($database)) {
            $database = 'XE';
        }

        return Factory::getDbInstance("oci://C##TEST:$password@$host/$database");
    }

    public function testDontParseParam_3()
    {
        $this->expectException(PDOException::class);

        parent::testDontParseParam_3();
    }
}
