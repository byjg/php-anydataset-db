<?php

namespace Tests;

use ByJG\AnyDataset\Db\DatabaseRouter;
use ByJG\AnyDataset\Db\Exception\RouteNotFoundException;
use ByJG\AnyDataset\Db\Exception\RouteNotMatchedException;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseRouterTest extends TestCase
{
    /**
     * @var DatabaseRouter
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
        $this->object = new DatabaseRouter();

        $this->obj1 = Factory::getDbInstance('sqlite:///tmp/a.db');
        $this->obj2 = Factory::getDbInstance('sqlite:///tmp/b.db');
        $this->obj3 = Factory::getDbInstance('sqlite:///tmp/c.db');

        $this->object->addDriver('route1', $this->obj1);
        $this->object->addDriver('route2', $this->obj2);
        $this->object->addDriver('route3', $this->obj3);
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

    public function testSamePatternKeepsOrderAdded()
    {
        // Routes are tried in the order added: the first one with the pattern wins
        $this->object->addCustomRoute('route1', '^select\s');
        $this->object->addRouteForSelect('route2', 'mytable');
        $this->object->addCustomRoute('route3', '^select\s');
        $this->assertSame($this->obj1, $this->object->matchRoute('SELECT field1 fRom mytable'));
    }

    public static function tableWithRegexCharsProvider(): array
    {
        return [
            'select' => ['addRouteForSelect', 'a.b', 'select * from a.b ', 'select * from axb '],
            'insert' => ['addRouteForInsert', 'a.b', 'insert into a.b (a) values (1)', 'insert into axb (a) values (1)'],
            'update' => ['addRouteForUpdate', 'a.b', 'update a.b set a=1', 'update axb set a=1'],
            'delete' => ['addRouteForDelete', 'a.b', 'delete from a.b where a=1', 'delete from axb where a=1'],
            'table'  => ['addRouteForTable', 'a.b', 'select * from a.b ', 'select * from axb '],
            'slash'  => ['addRouteForSelect', 'a/b', 'select * from a/b ', 'select * from axb '],
        ];
    }

    #[DataProvider('tableWithRegexCharsProvider')]
    public function testTableNameIsMatchedLiterally(string $method, string $table, string $matchingSql, string $otherSql)
    {
        $this->object->$method('route3', $table);
        $this->object->addDefaultRoute('route1');
        $this->assertSame($this->obj3, $this->object->matchRoute($matchingSql));
        $this->assertSame($this->obj1, $this->object->matchRoute($otherSql));
    }

    public function testFilterValueIsMatchedLiterally()
    {
        $this->object->addRouteForFilter('route3', 'email', 'a.b');
        $this->object->addDefaultRoute('route1');
        $this->assertSame($this->obj3, $this->object->matchRoute("select * from t where email = 'a.b'"));
        $this->assertSame($this->obj1, $this->object->matchRoute("select * from t where email = 'axb'"));
    }

    public static function caseInsensitiveProvider(): array
    {
        return [
            'uppercase table'  => ['addRouteForRead', ['Users'], 'SELECT * FROM Users WHERE id = 1'],
            'uppercase insert' => ['addRouteForWrite', ['Users'], 'insert into users (a) values (1)'],
            'custom pattern'   => ['addCustomRoute', ['GROUP\s+BY.*HAVING'], 'select a, count(*) from t group by a having count(*) > 1'],
            'filter value'     => ['addRouteForFilter', ['Email', 'A@B'], "select * from t where email = 'a@b'"],
        ];
    }

    #[DataProvider('caseInsensitiveProvider')]
    public function testMatchIsCaseInsensitive(string $method, array $args, string $sql)
    {
        $this->object->$method('route3', ...$args);
        $this->object->addDefaultRoute('route1');
        $this->assertSame($this->obj3, $this->object->matchRoute($sql));
    }
}
