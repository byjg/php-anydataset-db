<?php

namespace Test;

use ByJG\AnyDataset\Db\ParameterBinder;
use ByJG\Util\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParameterBinderTest extends TestCase
{
    public static function getDataTest()
    {
        $paramIn = [
            'name' => 'John',
            'surname' => 'Doe',
            'age' => 43
        ];

        return [
            [
                new Uri('mysql://host'),
                'insert into value (:name, :surname, :age)',
                'insert into value (:name, :surname, :age)',
                $paramIn,
                $paramIn
            ],
            [
                new Uri('mysql://host'),
                'insert into value (:name, :surname, :age)',
                'insert into value (:name, :surname, :age)',
                $paramIn,
                $paramIn
            ],
            [
                new Uri('mysql://host'),
                'insert into value (:name, :surname, :age, :nonexistant)',
                'insert into value (:name, :surname, :age, null)',
                $paramIn,
                $paramIn
            ],
            [
                new Uri('mysql://host'),
                'insert into value (:name, :surname, :age)',
                'insert into value (:name, :surname, :age)',
                $paramIn,
                $paramIn
            ],
            [
                new Uri('mysql://host'),
                'insert into value (:name, :surname, :age)',
                'insert into value (:name, :surname, :age)',
                $paramIn,
                $paramIn
            ],
            [
                new Uri('mysql://host'),
                'select * from table where :age-1900 > 10',
                'select * from table where :age-1900 > 10',
                $paramIn,
                [
                    'age' => 43
                ]
            ],
            [
                new Uri('mysql://host'),
                'select * from table where :age-1900 > 10',
                'select * from table where :age-1900 > 10',
                $paramIn,
                [
                    'age' => 43
                ]
            ],
            [
                new Uri('mysql://host'),
                'select * from table where age = :aaa and date = :bbb',
                'select * from table where age = null and date = null',
                $paramIn,
                []
            ],
            [
                new Uri('mysql://host'),
                "insert into value (':name', 'a:surname', ':age')",
                "insert into value (':name', 'a:surname', ':age')",
                null,
                []
            ],
            [
                new Uri('mysql://host'),
                "insert into value (':name', 'a:surname', ':age')",
                "insert into value (':name', 'a:surname', ':age')",
                $paramIn,
                []
            ],
            [
                new Uri('mysql://host'),
                "insert into value (':na''me', 43, ':ag''e')",
                "insert into value (':na''me', 43, ':ag''e')",
                null,
                []
            ],
        ];
    }

    /**
     * @dataProvider getDataTest
     */
    #[DataProvider('getDataTest')]
    public function testParameterBinding($uri, $subject, $expected, $paramsIn, $paramsExpected)
    {
        $this->assertEquals(
            [
                $expected,
                $paramsExpected
            ],
            ParameterBinder::prepareParameterBindings(
                $uri,
                $subject,
                $paramsIn
            )
        );
    }

    public static function dataTestPostgres()
    {
        return [
            [
                'SELECT column::text, :name, :surname FROM table WHERE age = :age',
                [
                    'name' => 'John',
                    'surname' => 'Doe',
                    'age' => 43
                ],
            ],
            [
                'SELECT sum(age::numeric) as total FROM table',
                []
            ]
        ];
    }

    #[DataProvider('dataTestPostgres')]
    public function testPostgresTypecast($sql, $paramIn)
    {
        // Test with Postgres type casting (::)
        $uri = new Uri('pgsql://host');

        $this->assertEquals(
            [
                $sql,
                $paramIn
            ],
            ParameterBinder::prepareParameterBindings(
                $uri,
                $sql,
                $paramIn
            )
        );
    }
}
