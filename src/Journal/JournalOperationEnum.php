<?php

namespace ByJG\AnyDataset\Db\Journal;

enum JournalOperationEnum: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
}