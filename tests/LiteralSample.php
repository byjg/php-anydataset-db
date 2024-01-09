<?php

namespace Test;

use ByJG\Serializer\BaseModel;

/**
 * @Xmlnuke:NodeName ModelGetter
 */
class LiteralSample extends BaseModel
{

    protected string $value = "";

    /**
     * LiteralSample constructor.
     *
     * @param string $value
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return "cast('" . $this->value . "' as integer)";
    }
}
