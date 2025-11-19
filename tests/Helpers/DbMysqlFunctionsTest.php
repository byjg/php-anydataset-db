<?php

namespace Test\Helpers;

use ByJG\AnyDataset\Db\SqlDialect\MysqlSqlDialect;
use ByJG\AnyDataset\Db\SqlStatement;
use Override;
use PHPUnit\Framework\TestCase;

class DbMysqlFunctionsTest extends TestCase
{
    /**
     * @var MysqlSqlDialect|null
     */
    protected ?MysqlSqlDialect $object;

    #[Override]
    protected function setUp(): void
    {
        $this->object = new MysqlSqlDialect();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->object = null;
    }

    public function testConcat()
    {
        $result = $this->object->concat('param1', 'param2');
        $this->assertEquals('concat(param1, param2)', $result);

        $result = $this->object->concat('param1', 'param2', 'param3');
        $this->assertEquals('concat(param1, param2, param3)', $result);

        $result = $this->object->concat('param1', 'param2', 'param3', 'param4');
        $this->assertEquals('concat(param1, param2, param3, param4)', $result);
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
        $this->assertEquals("DATE_FORMAT(column,'%e/%b/%Y')", $this->object->sqlDate('d/M/Y', 'column'));
        $this->assertEquals("DATE_FORMAT(column,'%d/%m/%Y %H:%i')", $this->object->sqlDate('D/m/Y H:i', 'column'));
        $this->assertEquals("DATE_FORMAT(column,'%H:%i')", $this->object->sqlDate('H:i', 'column'));
        $this->assertEquals("DATE_FORMAT(column,'%e %m %Y %H %i')", $this->object->sqlDate('d m Y H i', 'column'));
        $this->assertEquals("DATE_FORMAT(now(),'%e/%m/%y %I:%i')", $this->object->sqlDate('d/m/y h:i'));
        $this->assertEquals("DATE_FORMAT(column,'%b ')", $this->object->sqlDate('M q', 'column'));
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
        $this->assertTrue($this->object->hasForUpdate());

        $sql1 = 'select * from table';
        $sql2 = 'select * from table for update';
        $sql3 = 'select * from table for update ';

        $this->assertEquals(
            'select * from table FOR UPDATE ',
            $this->object->forUpdate($sql1)
        );

        $this->assertEquals(
            'select * from table for update',
            $this->object->forUpdate($sql2)
        );

        $this->assertEquals(
            'select * from table for update ',
            $this->object->forUpdate($sql3)
        );
    }

    public function testGetJoinTable()
    {
        $tables = [
            [
                "table" => "table1",
                "condition" => "table1.id = table2.id",
            ]
        ];

        $this->assertEquals(
            [
                'position' => 'before_set',
                "sql" => " INNER JOIN `table1` ON table1.id = table2.id"
            ],
            $this->object->getJoinTablesUpdate($tables)
        );

    }

    public function testGetJoinTable2()
    {
        $tables = [
            [
                "table" => "table1",
                "condition" => "t1.id = table2.id",
                "alias" => "t1"
            ]
        ];

        $this->assertEquals(
            [
                'position' => 'before_set',
                "sql" => " INNER JOIN `table1` AS t1 ON t1.id = table2.id"
            ],
            $this->object->getJoinTablesUpdate($tables)
        );
    }

    public function testGetJoinTable3()
    {
        $sqlStatement = new SqlStatement("select * from table1");
        $tables = [
            [
                "table" => $sqlStatement,
                "condition" => "t1.id = table2.id",
                "alias" => "t1"
            ]
        ];

        $this->assertEquals(
            [
                'position' => 'before_set',
                "sql" => " INNER JOIN ({$sqlStatement->getSql()}) AS t1 ON t1.id = table2.id"
            ],
            $this->object->getJoinTablesUpdate($tables)
        );
    }

    public function testGetJoinTable4()
    {
        $tables = [
            [
                "table" => "table1",
                "condition" => "t1.id = table2.id",
                "alias" => "t1"
            ],
            [
                "table" => "table2",
                "condition" => "t2.id = table3.id",
                "alias" => "t2"
            ],
        ];

        $this->assertEquals(
            [
                'position' => 'before_set',
                "sql" => " INNER JOIN `table1` AS t1 ON t1.id = table2.id  INNER JOIN `table2` AS t2 ON t2.id = table3.id"
            ],
            $this->object->getJoinTablesUpdate($tables)
        );
    }
}
