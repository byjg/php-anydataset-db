<?php

use ByJG\AnyDataset\Db\Factory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 'abc' scheme does not exist
     */
    public function testNonExistentScheme()
    {
        Factory::getDbRelationalInstance("abc://user:pass@test");
    }
}