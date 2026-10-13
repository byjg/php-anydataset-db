<?php

namespace Tests;

use ByJG\AnyDataset\Core\Enum\Relation;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\IteratorFilterSqlFormatter;
use Override;
use PHPUnit\Framework\TestCase;

class IteratorFilterTest extends TestCase
{

    /**
     * @var IteratorFilter
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    #[Override]
    protected function setUp(): void
    {
        $this->object = new IteratorFilter();
    }

    public function testGetSql()
    {
        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename ', $sql);

        $this->object->and('field', Relation::Equal, 'test');
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field' => 'test'], $params);
        $this->assertEquals('select * from tablename  where  field = :field  ', $sql);

        $this->object->and('field2', Relation::GreaterThanOrEqualTo, 'test2');
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2'], $params);
        $this->assertEquals('select * from tablename  where  field = :field  and  field2 >= :field2  ', $sql);

        $this->object->and('field3', Relation::Contains, 'test3');
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2', 'field3' => '%test3%'], $params);
        $this->assertEquals('select * from tablename  where  field = :field  and  field2 >= :field2  and  field3  like  :field3  ', $sql);
    }

    public function testSqlLiteral()
    {
        $literalObject = new LiteralSample(10);

        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename ', $sql);

        $this->object->and('field', Relation::GreaterThan, $literalObject);
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename  where  field > cast(\'10\' as integer)  ', $sql);

        $this->object->and('field2', Relation::LessThan, 5);
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field2' => 5], $params);
        $this->assertEquals('select * from tablename  where  field > cast(\'10\' as integer)  and  field2 < :field2  ', $sql);
    }

    public function testRelationIn()
    {
        $this->object->and('field', Relation::In, ['value1', 'value2']);
        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field0' => 'value1', 'field1' => 'value2'], $params);
        $this->assertEquals('select * from tablename  where  field IN (:field0, :field1)  ', $sql);
    }

    public function testRelationNotIn()
    {
        $this->object->and('field', Relation::NotIn, ['value1', 'value2']);
        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field0' => 'value1', 'field1' => 'value2'], $params);
        $this->assertEquals('select * from tablename  where  field NOT IN (:field0, :field1)  ', $sql);
    }

    public function testAddRelationOr()
    {
        $this->object->and('field', Relation::Equal, 'test');
        $this->object->or('field2', Relation::Equal, 'test2');

        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2'], $params);
        $this->assertEquals('select * from tablename  where  field = :field  or  field2 = :field2  ', $sql);
    }

    public function testRelationIsNull()
    {
        $this->object->and('field', Relation::IsNull, null);

        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );

        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename  where  field IS NULL  ', $sql);
    }

    public function testRelationIsNotNull()
    {
        $this->object->and('field', Relation::IsNotNull, null);

        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );

        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename  where  field IS NOT NULL  ', $sql);
    }

    public function testGroup()
    {
        $this->object->startGroup('field', Relation::Equal, 'test');
        $this->object->and('field2', Relation::Equal, 'test2');
        $this->object->endGroup();
        $this->object->or('field3', Relation::Equal, 'test3');

        $params = [];
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter('tablename', '*'),
            $params
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2', 'field3' => 'test3'], $params);
        $this->assertEquals(
            'select * from tablename  where ( field = :field  and  field2 = :field2 ) or  field3 = :field3  ',
            $sql
        );
    }
}
