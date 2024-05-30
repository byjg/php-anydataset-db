<?php

namespace Tests\AnyDataset\Store\Helpers;

use ByJG\AnyDataset\Db\Helpers\DbDblibFunctions;
use PHPUnit\Framework\TestCase;

class DbDblibFunctionsTest extends TestCase
{
    /**
     * @var DbDblibFunctions
     */
    protected $object;

    protected function setUp(): void
    {
        $this->object = new DbDblibFunctions();
    }

    protected function tearDown(): void
    {
        $this->object = null;
    }

    public function testConcat()
    {
        $result = $this->object->concat('param1', 'param2');
        $this->assertEquals('param1 + param2', $result);

        $result = $this->object->concat('param1', 'param2', 'param3');
        $this->assertEquals('param1 + param2 + param3', $result);

        $result = $this->object->concat('param1', 'param2', 'param3', 'param4');
        $this->assertEquals('param1 + param2 + param3 + param4', $result);
    }

    public function testLimit()
    {
        $this->expectException(\ByJG\AnyDataset\Core\Exception\NotAvailableException::class);

        $this->object->limit('select  from table', 0, 10);
    }

    public function testTop()
    {
        $result = $this->object->top('select * from table', 10);
        $this->assertEquals('select top 10 * from table', $result);

        $result = $this->object->top('select TOP 234 * from table', 20);
        $this->assertEquals('select TOP 20 * from table', $result);
    }

    public function testHasTop()
    {
        $this->assertTrue($this->object->hasTop());
    }

    public function testHasLimit()
    {
        $this->assertFalse($this->object->hasLimit());
    }

    public function testSqlDate()
    {
        $this->assertEquals("FORMAT(column, 'dd/MM/YYYY')", $this->object->sqlDate('d/M/Y', 'column'));
        $this->assertEquals("FORMAT(column, 'dd/M/YYYY HH:mm')", $this->object->sqlDate('d/m/Y H:i', 'column'));
        $this->assertEquals("FORMAT(column, 'HH:mm')", $this->object->sqlDate('H:i', 'column'));
        $this->assertEquals("FORMAT(column, 'dd M YYYY HH mm')", $this->object->sqlDate('d m Y H i', 'column'));
        $this->assertEquals("FORMAT(getdate(), 'dd/M/YY H:mm')", $this->object->sqlDate('d/m/y h:i'));
        $this->assertEquals("FORMAT(column, 'MM ')", $this->object->sqlDate('M q', 'column'));
    }

    public function testDelimiterField()
    {
        $field = $this->object->delimiterField('field1');
        $field2 = $this->object->delimiterField('table.field1');
        $fieldAr = $this->object->delimiterField(['field2', 'field3']);
        $fieldAr2 = $this->object->delimiterField(['master.dbo.field2', 'table.field3']);

        $this->assertEquals('"field1"', $field);
        $this->assertEquals('"table"."field1"', $field2);
        $this->assertEquals(['"field2"', '"field3"'], $fieldAr);
        $this->assertEquals(['"master"."dbo"."field2"', '"table"."field3"'], $fieldAr2);
    }

    public function testDelimiterTable()
    {
        $table = $this->object->delimiterField('table');
        $tableDb = $this->object->delimiterField('dbo.table');
        $tableDb2 = $this->object->delimiterField('master.dbo.table');

        $this->assertEquals('"table"', $table);
        $this->assertEquals('"dbo"."table"', $tableDb);
        $this->assertEquals('"master"."dbo"."table"', $tableDb2);
    }

    public function testForUpdate()
    {
        $this->expectException(\ByJG\AnyDataset\Core\Exception\NotAvailableException::class);

        $this->assertFalse($this->object->hasForUpdate());
        $this->object->forUpdate('select * from table');
    }
}
