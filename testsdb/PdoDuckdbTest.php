<?php

namespace TestDb;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use PDOException;

class PdoDuckdbTest extends BasePdo
{
    #[\Override]
    protected function dateLiteral(): string
    {
        return "TIMESTAMP '2026-03-05 14:07:09'";
    }

    protected string $file = "/tmp/test.duckdb";

    protected function createInstance()
    {
        if (!extension_loaded('pdo_duckdb')) {
            $this->testSkipped = true;
            $this->markTestSkipped("PDO DuckDB extension is not loaded");
        }

        return Factory::getDbInstance("duckdb://" . $this->file);
    }

    protected function createDatabase()
    {
        //create the database
        $this->executor->execute("CREATE SEQUENCE seq_dogs");
        $this->executor->execute("CREATE TABLE Dogs (Id INTEGER PRIMARY KEY DEFAULT nextval('seq_dogs'), Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        $this->executor->getDriver()->disconnect();
        @unlink($this->file);
        @unlink($this->file . ".wal");
    }

    /**
     * DuckDB has only virtual generated columns.
     */
    #[\Override]
    protected function getGeneratedColumnDefinition(): string
    {
        return "Total INTEGER GENERATED ALWAYS AS (A + B) VIRTUAL";
    }

    /**
     * DuckDB's catalog does not mark a generated column: it shows its expression as the default
     * value, the same way as a regular default.
     */
    public function testGetTableMetadataGeneratedColumn()
    {
        $this->executor->execute("CREATE TABLE MetaSum (Id INTEGER NOT NULL PRIMARY KEY, A INTEGER, B INTEGER, " . $this->getGeneratedColumnDefinition() . ")");
        try {
            $metadata = $this->executor->getHelper()->getTableMetadata($this->executor, 'MetaSum');

            $this->assertSame(['id', 'a', 'b', 'total'], array_keys($metadata));
            $this->assertFalse($metadata['total']->generated);
            $this->assertSame('CAST((A + B) AS INTEGER)', $metadata['total']->default);
        } finally {
            $this->executor->execute("DROP TABLE MetaSum");
        }
    }

    /**
     * pdo_duckdb opens a separate database for each PDO object, and two databases on the same
     * file in one process do not see each other's commits. One connection per file.
     */
    public function testTwoDifferentTransactions()
    {
        $this->markTestSkipped('DuckDB: one connection per database file in a process');
    }

    public function testInMemoryDatabase()
    {
        $executor = DatabaseExecutor::using(Factory::getDbInstance("duckdb://"));
        $executor->execute("CREATE TABLE Memory (Id INTEGER)");
        $executor->execute("INSERT INTO Memory VALUES (1), (2)");

        $this->assertEquals(2, $executor->getScalar("select count(*) from Memory"));
        $this->assertEquals(0, $this->executor->getScalar("select count(*) from information_schema.tables where table_name = 'Memory'"));
    }

    /**
     * In memory: querying a file needs no database file. read_csv() detects the format from the
     * content, so the file needs no extension.
     */
    public function testQueryACsvFile()
    {
        $csv = tempnam(sys_get_temp_dir(), 'duckdb');
        file_put_contents($csv, "breed,amount\nMutt,10\nBrazilian Terrier,20\nMutt,5\n");
        try {
            $executor = DatabaseExecutor::using(Factory::getDbInstance("duckdb://"));
            $rows = $executor->getIterator(
                "select breed, sum(amount) as total from read_csv(:file) group by breed order by breed",
                ['file' => $csv]
            )->toArray();

            $this->assertEquals([['breed' => 'Brazilian Terrier', 'total' => 20], ['breed' => 'Mutt', 'total' => 15]], $rows);
        } finally {
            unlink($csv);
        }
    }

    public function testExecuteAndGetIdWithoutSequence()
    {
        $this->executor->execute("CREATE TABLE NoSeq (Id INTEGER PRIMARY KEY, Name VARCHAR)");
        try {
            $this->assertNull($this->executor->executeAndGetId("INSERT INTO NoSeq (Id, Name) VALUES (10, 'a')"));
            $this->assertEquals(1, $this->executor->getScalar("select count(*) from NoSeq where Id = 10"));
        } finally {
            $this->executor->execute("DROP TABLE NoSeq");
        }
    }

    public function testGetDate() {
        $data = $this->executor->getScalar("SELECT CAST('2018-07-26' AS DATE) ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->executor->getScalar("SELECT CAST('2018-07-26 20:02:03' AS TIMESTAMP) ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testDontParseParam_3() {
        $this->expectException(PDOException::class);

        parent::testDontParseParam_3();
    }

    /**
     * DuckDB keeps the default value of the sequence, and VARCHAR has no length (VARCHAR(50) is
     * a VARCHAR).
     */
    #[\Override]
    protected function getExpectedMetadata()
    {
        $expected = parent::getExpectedMetadata();
        $expected['id']['default'] = "nextval('seq_dogs')";
        $expected['id']['autoIncrement'] = true;
        $expected['breed']['length'] = null;
        $expected['name']['length'] = null;

        return $expected;
    }
}
