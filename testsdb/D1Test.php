<?php

namespace TestDb;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\Util\Uri;

/**
 * Integration test against a real Cloudflare D1 database.
 *
 * It is skipped unless D1_ACCOUNT_ID, D1_DATABASE_ID and D1_API_TOKEN are set, the same way the
 * dblib/sqlsrv/oci tests skip when their extension is missing. See docs/tests.md.
 */
class D1Test extends BasePdo
{
    protected function createInstance()
    {
        $accountId = getenv('D1_ACCOUNT_ID');
        $databaseId = getenv('D1_DATABASE_ID');
        $apiToken = getenv('D1_API_TOKEN');

        if (empty($accountId) || empty($databaseId) || empty($apiToken)) {
            $this->testSkipped = true;
            $this->markTestSkipped(
                'Cloudflare D1 tests require D1_ACCOUNT_ID, D1_DATABASE_ID and D1_API_TOKEN'
            );
        }

        $host = getenv('D1_HOST') ?: 'api.cloudflare.com';

        $uri = Uri::getInstance(
            sprintf('d1://%s:%s@%s/%s', rawurlencode($accountId), rawurlencode($apiToken), $host, $databaseId)
        );

        return Factory::getDbInstance($uri);
    }

    protected function createDatabase()
    {
        $this->executor->execute("DROP TABLE IF EXISTS Dogs");
        $this->executor->execute(
            "CREATE TABLE Dogs (Id INTEGER NOT NULL PRIMARY KEY, Breed VARCHAR(50), "
            . "Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))"
        );
    }

    public function deleteDatabase()
    {
        $this->executor->execute("DROP TABLE IF EXISTS Dogs");
    }

    public function testGetDate()
    {
        $data = $this->executor->getScalar("SELECT DATE('2018-07-26') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->executor->getScalar("SELECT DATETIME('2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    /**
     * DatabaseExecutor discovers the columns from the first row of a "LIMIT 0" query, which a
     * non-PDO driver cannot answer. Use getHelper()->getTableMetadata() instead (testGetMetadata).
     */
    public function testGetAllFields()
    {
        $this->markTestSkipped('Cloudflare D1 does not support getAllFields(); use getTableMetadata()');
    }

    /**
     * With DONT_PARSE_PARAM the SQL is forwarded verbatim, so it must already use the positional
     * "?" placeholders that the D1 API expects.
     */
    public function testDontParseParam()
    {
        $newUri = $this->executor->getDriver()->getUri()->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
        $newConn = Factory::getDbInstance($newUri);
        $it = DatabaseExecutor::using($newConn)
            ->getIterator('select Id, Breed, Name, Age from Dogs where id = ?', ["field" => 1]);

        $this->assertCount(1, $it->toArray());
        $this->assertFalse($it->isCursorOpen());
    }

    /**
     * A named placeholder left in the SQL is rejected by D1, which only understands "?".
     */
    public function testDontParseParam_3()
    {
        $this->expectException(DatabaseException::class);
        parent::testDontParseParam_3();
    }

    // --------------------------------------------------------------------------- transactions
    // D1 rejects BEGIN/COMMIT: every statement is already committed atomically on its own.

    public function testCommitTransaction()
    {
        $this->expectException(NotAvailableException::class);
        $this->executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
    }

    public function testRollbackTransaction()
    {
        $this->expectException(NotAvailableException::class);
        $this->executor->beginTransaction(IsolationLevelEnum::REPEATABLE_READ);
    }

    public function testRequiresTransaction()
    {
        $this->expectException(NotAvailableException::class);
        $this->executor->beginTransaction(IsolationLevelEnum::READ_COMMITTED);
    }

    public function testBeginTransactionTwice()
    {
        $this->expectException(NotAvailableException::class);
        $this->executor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);
    }

    public function testJoinTransaction()
    {
        $this->expectException(NotAvailableException::class);
        $this->executor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);
    }

    public function testTwoDifferentTransactions()
    {
        $this->expectException(NotAvailableException::class);
        $this->executor->beginTransaction();
    }
}
