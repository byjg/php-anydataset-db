<?php

use ByJG\AnyDataset\Db\Factory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    public function testNonExistentScheme()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'abc' scheme does not exist");

        Factory::getDbRelationalInstance("abc://user:pass@test");
    }
}