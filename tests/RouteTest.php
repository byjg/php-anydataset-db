<?php

namespace Test;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\Exception\RouteNotFoundException;
use ByJG\AnyDataset\Db\Exception\RouteNotMatchedException;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Route;
use Override;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    /**
     * @var Route
     */
    protected $object;

    /**
     * @var DbDriverInterface
     */
    protected $obj1;

    /**
     * @var DbDriverInterface
     */
    protected $obj2;

    /**
     * @var DbDriverInterface
     */
    protected $obj3;

    #[Override]
    public function setUp(): void
    {
        $this->object = new Route();

        $this->obj1 = Factory::getDbInstance('sqlite:///tmp/a.db');
        $this->obj2 = Factory::getDbInstance('sqlite:///tmp/b.db');
        $this->obj3 = Factory::getDbInstance('sqlite:///tmp/c.db');

        $this->object->addDbDriverInterface('route1', 'sqlite:///tmp/a.db');
        $this->object->addDbDriverInterface('route2', $this->obj2);
        $this->object->addDbDriverInterface('route3', 'sqlite:///tmp/c.db');
    }

    #[Override]
    public function tearDown(): void
    {
        $this->object = null;
        $this->obj1 = null;
        $this->obj2 = null;
        $this->obj3 = null;
    }

    public function testAddRouteForSelect()
    {
        $this->object->addRouteForSelect('route3', 'mytable');
        $this->object->addRouteForSelect('route2');
        $this->assertEquals($this->obj2, $this->object->matchRoute('SELECT field1, fields fRom table'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('SELECT field1, fields fRom mytable'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotMatchedException
     */
    public function testAddRouteForSelectFail()
    {
        $this->expectException(RouteNotMatchedException::class);
        
        $this->object->addRouteForSelect('route3', 'mytable');
        $this->object->addRouteForSelect('route2');
        $this->object->matchRoute('update mytable set a=1');
    }

    public function testAddRouteForInsert()
    {
        $this->object->addRouteForInsert('route3', 'mytable');
        $this->object->addRouteForinsert('route2');
        $this->assertEquals($this->obj2, $this->object->matchRoute('Insert into table (a) values (1)'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('inSert into mytable (a) values (2)'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotMatchedException
     */
    public function testAddRouteForInsertFail()
    {
        $this->expectException(RouteNotMatchedException::class);

        $this->object->addRouteForInsert('route3', 'mytable');
        $this->object->addRouteForinsert('route2');
        $this->object->matchRoute('updata table set a=1');
    }

    public function testAddRouteForUpdate()
    {
        $this->object->addRouteForUpdate('route3', 'mytable');
        $this->object->addRouteForUpdate('route2');
        $this->assertEquals($this->obj2, $this->object->matchRoute('update table set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('update mytable set a=1'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotMatchedException
     */
    public function testAddRouteForUpdateFail()
    {
        $this->expectException(RouteNotMatchedException::class);
 
        $this->object->addRouteForUpdate('route3', 'mytable');
        $this->object->addRouteForUpdate('route2');
        $this->object->matchRoute('delete table where set a=1');
    }

    public function testAddRouteForDelete()
    {
        $this->object->addRouteForDelete('route3', 'mytable');
        $this->object->addRouteForDelete('route2');
        $this->assertEquals($this->obj2, $this->object->matchRoute('delete table where set a=1'));
        $this->assertEquals($this->obj2, $this->object->matchRoute('delete from table where set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('delete mytable where set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('delete from mytable where set a=1'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotMatchedException
     */
    public function testAddRouteForDeleteFail()
    {
        $this->expectException(RouteNotMatchedException::class);

        $this->object->addRouteForDelete('route3', 'mytable');
        $this->object->addRouteForDelete('route2');
        $this->object->matchRoute('update table set a=1');
    }

    public function testAddRouteForTable()
    {
        $this->object->addRouteForTable('route3', 'mytable');
        $this->assertEquals($this->obj3, $this->object->matchRoute('delete mytable where set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('delete from mytable where set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('update mytable set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('inSert into mytable (a) values (2)'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('SELECT field1, fields fRom mytable'));
    }

    public function testAddRouteForWriteAndRead()
    {
        $this->object->addRouteForWrite('route3', 'mytable');
        $this->object->addRouteForRead('route2', 'mytable');
        $this->assertEquals($this->obj3, $this->object->matchRoute('delete mytable where set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('delete from mytable where set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('update mytable set a=1'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('inSert into mytable (a) values (2)'));
        $this->assertEquals($this->obj2, $this->object->matchRoute('SELECT field1, fields fRom mytable'));
    }

    public function testAddDefaultRoute()
    {
        $this->object->addRouteForWrite('route3', 'mytable');
        $this->object->addRouteForRead('route2', 'mytable');
        $this->object->addDefaultRoute('route1');
        $this->assertEquals($this->obj1, $this->object->matchRoute('SELECT field1, fields fRom othertable'));
    }

    public function testAddRouteForFilter()
    {
        $this->object->addRouteForFilter('route3', 'id', '3');
        $this->assertEquals($this->obj3, $this->object->matchRoute('SELECT field1, fields fRom othertable where id=3'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('SELECT field1 fRom othertable where id = 3'));
        $this->assertEquals($this->obj3, $this->object->matchRoute('SELECT field1 fRom othertable where id = \'3\''));
        $this->assertEquals($this->obj3, $this->object->matchRoute('SELECT field1, fields fRom othertable where `id` = 3'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotMatchedException
     */
    public function testAddRouteForFilterFail()
    {
        $this->expectException(RouteNotMatchedException::class);

        $this->object->addRouteForFilter('route3', 'id', '3');
        $this->object->matchRoute('SELECT field1, fields fRom othertable where id=31');
    }
}
