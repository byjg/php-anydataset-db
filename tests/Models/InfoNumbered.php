<?php

namespace Tests\Models;

/**
 * The info columns, and a property without a column that the constructor sets differently for each instance
 */
class InfoNumbered
{
    public $id;
    public $iduser;
    public $number;
    public $property;
    public int $instance;

    public function __construct()
    {
        static $instances = 0;
        $this->instance = ++$instances;
    }
}
