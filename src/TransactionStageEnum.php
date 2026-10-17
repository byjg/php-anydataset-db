<?php

namespace ByJG\AnyDataset\Db;

enum TransactionStageEnum
{
    case Begin;

    case Commit;

    case Rollback;
}
