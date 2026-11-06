<?php

namespace Tests\Models;

use ArrayObject;

/**
 * The info columns, and a property without a column that the constructor sets to an object
 */
class InfoWithObject
{
    public $id;
    public $iduser;
    public $number;
    public $property;
    protected ?ArrayObject $tags = null;

    public function __construct()
    {
        $this->tags = new ArrayObject(['new']);
    }

    public function getTags(): ?ArrayObject
    {
        return $this->tags;
    }

    public function setTags(?ArrayObject $tags): void
    {
        $this->tags = $tags;
    }
}
