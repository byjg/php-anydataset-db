<?php

namespace TestDb;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Util\Uri;


class PdoSqliteTest extends BasePdo
{
    /**
     * strftime() has no month names nor a two-digit year, and its day without zero and 12-hour
     * clock only exist in recent SQLite versions: sqlDate() keeps to what every version has.
     */
    #[\Override]
    protected function expectedSqlDates(): array
    {
        return [
            'D/m/Y H:i:s' => '05/03/2026 14:07:09',
            'Y-m-D' => '2026-03-05',
        ];
    }

    protected $host;

    protected bool $integerPrimaryKeyIsAutoIncrement = true;

    protected function createInstance()
    {
        $this->host = getenv('SQLITE_TEST_HOST');
        if (empty($host)) {
            $this->host = "/tmp/test.db";
        }

        $uri = Uri::getInstance("sqlite://" . $this->host);

        return Factory::getDbInstance($uri);
    }

    protected function createDatabase()
    {
        //create the database
        $this->executor->execute("CREATE TABLE Dogs (Id INTEGER NOT NULL PRIMARY KEY, Breed VARCHAR(50), Name VARCHAR(50), Age INTEGER, Weight NUMERIC(10,2))");
    }

    public function deleteDatabase()
    {
        unlink($this->host);
    }

    public function testGetAllFields()
    {
        $this->markTestSkipped('SQLite does not support getAllFields() method');
    }

    public function testGetDate() {
        $data = $this->executor->getScalar("SELECT DATE('2018-07-26') ");
        $this->assertEquals("2018-07-26", $data);

        $data = $this->executor->getScalar("SELECT DATETIME('2018-07-26 20:02:03') ");
        $this->assertEquals("2018-07-26 20:02:03", $data);
    }

    public function testOnlyASingleIntegerPrimaryKeyIsAutoIncrement()
    {
        $this->executor->execute("CREATE TABLE BigKey (Id BIGINT NOT NULL PRIMARY KEY)");
        $this->executor->execute("CREATE TABLE TwoKeys (A INTEGER NOT NULL, B INTEGER NOT NULL, PRIMARY KEY (A, B))");
        $helper = $this->executor->getHelper();

        $bigKey = $helper->getTableMetadata($this->executor, 'BigKey');
        $this->assertTrue($bigKey['id']->primaryKey);
        $this->assertFalse($bigKey['id']->autoIncrement);

        $twoKeys = $helper->getTableMetadata($this->executor, 'TwoKeys');
        $this->assertTrue($twoKeys['a']->primaryKey);
        $this->assertTrue($twoKeys['b']->primaryKey);
        $this->assertFalse($twoKeys['a']->autoIncrement);
        $this->assertFalse($twoKeys['b']->autoIncrement);
    }

    public function testForeignKeyWithoutColumnsReferencesThePrimaryKey()
    {
        $this->executor->execute("CREATE TABLE Parent (A INTEGER NOT NULL, B INTEGER NOT NULL, PRIMARY KEY (B, A))");
        $this->executor->execute("CREATE TABLE Child (Id INTEGER PRIMARY KEY, PB INTEGER, PA INTEGER, FOREIGN KEY (PB, PA) REFERENCES Parent)");

        $this->assertEquals(
            [['columns' => ['PB', 'PA'], 'parentTable' => 'Parent', 'parentColumns' => ['B', 'A']]],
            $this->metadataToArray($this->executor->getHelper()->getTableForeignKeys($this->executor, 'Child'))
        );
    }
}
