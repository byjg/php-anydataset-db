<?php

namespace ByJG\AnyDataset\Db\Journal;

enum JournalOperationEnum: string
{
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
}