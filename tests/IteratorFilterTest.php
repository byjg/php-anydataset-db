<?php

namespace Tests\AnyDataset\Dataset;

use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\IteratorFilterSqlFormatter;
use ByJG\AnyDataset\Core\Enum\Relation;
use PHPUnit\Framework\TestCase;
use Tests\AnyDataset\Sample\LiteralSample;

require_once 'LiteralSample.php';

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
    protected function setUp()
    {
        $this->object = new IteratorFilter();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    public function testGetSql()
    {
        $params = null;
        $returnFields = '*';
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename ', $sql);

        $this->object->addRelation('field', Relation::EQUAL, 'test');
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals(['field' => 'test'], $params);
        $this->assertEquals('select * from tablename  where  field = [[field]]  ', $sql);

        $this->object->addRelation('field2', Relation::GREATER_OR_EQUAL_THAN, 'test2');
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2'], $params);
        $this->assertEquals('select * from tablename  where  field = [[field]]  and  field2 >= [[field2]]  ', $sql);

        $this->object->addRelation('field3', Relation::CONTAINS, 'test3');
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2', 'field3' => '%test3%'], $params);
        $this->assertEquals('select * from tablename  where  field = [[field]]  and  field2 >= [[field2]]  and  field3  like  [[field3]]  ', $sql);
    }

    public function testSqlLiteral()
    {
        $literalObject = new LiteralSample(10);

        $params = null;
        $returnFields = '*';
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename ', $sql);

        $this->object->addRelation('field', Relation::GREATER_THAN, $literalObject);
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals([], $params);
        $this->assertEquals('select * from tablename  where  field > cast(\'10\' as integer)  ', $sql);

        $this->object->addRelation('field2', Relation::LESS_THAN, 5);
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals(['field2' => 5], $params);
        $this->assertEquals('select * from tablename  where  field > cast(\'10\' as integer)  and  field2 < [[field2]]  ', $sql);
    }

    public function testAddRelationOr()
    {
        $this->object->addRelation('field', Relation::EQUAL, 'test');
        $this->object->addRelationOr('field2', Relation::EQUAL, 'test2');

        $params = null;
        $returnFields = '*';
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2'], $params);
        $this->assertEquals('select * from tablename  where  field = [[field]]  or  field2 = [[field2]]  ', $sql);
    }

    public function testGroup()
    {
        $this->object->startGroup();
        $this->object->addRelation('field', Relation::EQUAL, 'test');
        $this->object->addRelation('field2', Relation::EQUAL, 'test2');
        $this->object->endGroup();
        $this->object->addRelationOr('field3', Relation::EQUAL, 'test3');

        $params = null;
        $returnFields = '*';
        $sql = $this->object->format(
            new IteratorFilterSqlFormatter(),
            'tablename',
            $params,
            $returnFields
        );
        $this->assertEquals(['field' => 'test', 'field2' => 'test2', 'field3' => 'test3'], $params);
        $this->assertEquals(
            'select * from tablename  where  (  field = [[field]]  and  field2 = [[field2]] ) or  field3 = [[field3]]  ',
            $sql
        );
    }
}
