<?php

namespace Tests\AnyDataset\Store\Helpers;

use ByJG\AnyDataset\Db\Helpers\DbSqliteFunctions;

class DbSqliteFunctionsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DbSqliteFunctions
     */
    private $object;

    protected function setUp(): void
    {
        $this->object = new DbSqliteFunctions();
    }

    protected function tearDown(): void
    {
        $this->object = null;
    }

    public function testConcat()
    {
        $result = $this->object->concat('param1', 'param2');
        $this->assertEquals('param1 || param2', $result);

        $result = $this->object->concat('param1', 'param2', 'param3');
        $this->assertEquals('param1 || param2 || param3', $result);

        $result = $this->object->concat('param1', 'param2', 'param3', 'param4');
        $this->assertEquals('param1 || param2 || param3 || param4', $result);
    }

    public function testLimit()
    {
        $baseSql = 'select * from table';

        $result = $this->object->limit($baseSql, 10);
        $this->assertEquals($baseSql . ' LIMIT 10, 50', $result);

        $result = $this->object->limit($baseSql, 10, 20);
        $this->assertEquals($baseSql . ' LIMIT 10, 20', $result);

        $result = $this->object->limit($baseSql . ' LIMIT 5, 50', 10, 20);
        $this->assertEquals($baseSql . ' LIMIT 10, 20', $result);
    }

    public function testTop()
    {
        $baseSql = 'select * from table';

        $result = $this->object->top($baseSql, 10);
        $this->assertEquals($baseSql . ' LIMIT 0, 10', $result);

        $result = $this->object->top($baseSql . ' LIMIT 20,350', 10);
        $this->assertEquals($baseSql . ' LIMIT 0, 10', $result);
    }

    public function testHasTop()
    {
        $this->assertTrue($this->object->hasTop());
    }

    public function testHasLimit()
    {
        $this->assertTrue($this->object->hasLimit());
    }

    public function testSqlDate()
    {
        $this->assertEquals("strftime('%d/%m/%Y', column)", $this->object->sqlDate('d/M/Y', 'column'));
        $this->assertEquals("strftime('%d/%m/%Y %H:%M', column)", $this->object->sqlDate('d/m/Y H:i', 'column'));
        $this->assertEquals("strftime('%H:%M', column)", $this->object->sqlDate('H:i', 'column'));
        $this->assertEquals("strftime('%d %m %Y %H %M', column)", $this->object->sqlDate('d m Y H i', 'column'));
        $this->assertEquals("strftime('%d/%m/%Y %H:%M', 'now')", $this->object->sqlDate('d/m/y h:i'));
        $this->assertEquals("strftime('%m ', column)", $this->object->sqlDate('M q', 'column'));
    }

    public function testDelimiterField()
    {
        $field = $this->object->delimiterField('field1');
        $field2 = $this->object->delimiterField('table.field1');
        $fieldAr = $this->object->delimiterField(['field2', 'field3']);
        $fieldAr2 = $this->object->delimiterField(['table.field2', 'table.field3']);

        $this->assertEquals('`field1`', $field);
        $this->assertEquals('`table`.`field1`', $field2);
        $this->assertEquals(['`field2`', '`field3`'], $fieldAr);
        $this->assertEquals(['`table`.`field2`', '`table`.`field3`'], $fieldAr2);
    }

    public function testDelimiterTable()
    {
        $table = $this->object->delimiterField('table');
        $tableDb = $this->object->delimiterField('db.table');

        $this->assertEquals('`table`', $table);
        $this->assertEquals('`db`.`table`', $tableDb);
    }

    public function testForUpdate()
    {
        $this->expectException(\ByJG\AnyDataset\Core\Exception\NotAvailableException::class);
        
        $this->assertFalse($this->object->hasForUpdate());
        $this->object->forUpdate('select * from table');
    }
}
