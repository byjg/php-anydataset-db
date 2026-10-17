<?php

namespace ByJG\AnyDataset\Db;

enum IsolationLevelEnum
{
    case ReadUncommitted;
    case ReadCommitted;
    case RepeatableRead;
    case Serializable;
}
