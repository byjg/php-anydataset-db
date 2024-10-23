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
     * @param string|int $value
     */
    public function __construct(string|int $value)
    {
        parent::__construct();
        $this->value = $value;
    }

    public function __toString()
    {
        return "cast('" . $this->value . "' as integer)";
    }
}
