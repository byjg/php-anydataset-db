<?php

namespace ByJG\AnyDataset\Db;

enum IsolationLevelEnum
{
    case READ_UNCOMMITTED;
    case READ_COMMITTED;
    case REPEATABLE_READ;
    case SERIALIZABLE;
}
