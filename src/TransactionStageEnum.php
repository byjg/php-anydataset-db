<?php

namespace ByJG\AnyDataset\Db;

enum TransactionStageEnum
{
    case begin;

    case commit;

    case rollback;
}
