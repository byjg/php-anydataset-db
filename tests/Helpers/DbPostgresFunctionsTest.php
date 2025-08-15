<?php

namespace Test\Helpers;

use ByJG\AnyDataset\Db\Helpers\DbPgsqlFunctions;
use Override;
use ByJG\AnyDataset\Db\SqlStatement;
use PHPUnit\Framework\TestCase;

class DbPostgresFunctionsTest extends TestCase
{
    /**
     * @var DbPgsqlFunctions|null
     */
    private ?DbPgsqlFunctions $object;

    #[Override]
    protected function setUp(): void
    {
        $this->object = new DbPgsqlFunctions();
    }

    #[Override]
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
        $this->assertEquals($baseSql . ' LIMIT 50 OFFSET 10', $result);

        $result = $this->object->limit($baseSql, 10, 20);
        $this->assertEquals($baseSql . ' LIMIT 20 OFFSET 10', $result);

        $result = $this->object->limit($baseSql . ' LIMIT 5 OFFSET 50', 10, 20);
        $this->assertEquals($baseSql . ' LIMIT 20 OFFSET 10', $result);
    }

    public function testTop()
    {
        $baseSql = 'select * from table';

        $result = $this->object->top($baseSql, 10);
        $this->assertEquals($baseSql . ' LIMIT 10 OFFSET 0', $result);

        $result = $this->object->top($baseSql . ' LIMIT 350 OFFSET 20', 10);
        $this->assertEquals($baseSql . ' LIMIT 10 OFFSET 0', $result);
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
        $this->assertEquals("TO_CHAR(column,'DD/Mon/YYYY')", $this->object->sqlDate('d/M/Y', 'column'));
        $this->assertEquals("TO_CHAR(column,'DD/MM/YYYY HH24:MI')", $this->object->sqlDate('d/m/Y H:i', 'column'));
        $this->assertEquals("TO_CHAR(column,'HH24:MI')", $this->object->sqlDate('H:i', 'column'));
        $this->assertEquals("TO_CHAR(column,'DD MM YYYY HH24 MI')", $this->object->sqlDate('d m Y H i', 'column'));
        $this->assertEquals("TO_CHAR(column,'Mon Q')", $this->object->sqlDate('M q', 'column'));
        $this->assertEquals("TO_CHAR(current_timestamp,'DD/MM/YY HH:MI')", $this->object->sqlDate('d/m/y h:i'));
    }

    public function testDelimiterField()
    {
        $field = $this->object->delimiterField('field1');
        $field2 = $this->object->delimiterField('table.field1');
        $fieldAr = $this->object->delimiterField(['field2', 'field3']);
        $fieldAr2 = $this->object->delimiterField(['table.field2', 'table.field3']);

        $this->assertEquals('"field1"', $field);
        $this->assertEquals('"table"."field1"', $field2);
        $this->assertEquals(['"field2"', '"field3"'], $fieldAr);
        $this->assertEquals(['"table"."field2"', '"table"."field3"'], $fieldAr2);
    }

    public function testDelimiterTable()
    {
        $table = $this->object->delimiterField('table');
        $tableDb = $this->object->delimiterField('db.table');

        $this->assertEquals('"table"', $table);
        $this->assertEquals('"db"."table"', $tableDb);
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
                'position' => 'after_set',
                "sql" => " FROM \"table1\" ON table1.id = table2.id"
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
                'position' => 'after_set',
                "sql" => " FROM \"table1\" AS t1 ON t1.id = table2.id"
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
                'position' => 'after_set',
                "sql" => " FROM ({$sqlStatement->getSql()}) AS t1 ON t1.id = table2.id"
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
            ]

        ];

        $this->assertEquals(
            [
                'position' => 'after_set',
                "sql" => " FROM \"table1\" AS t1 ON t1.id = table2.id   INNER JOIN  \"table2\" AS t2 ON t2.id = table3.id"
            ],
            $this->object->getJoinTablesUpdate($tables)
        );

    }
}
