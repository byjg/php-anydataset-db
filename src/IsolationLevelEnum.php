<?php

namespace ByJG\AnyDataset\Db;

class IsolationLevelEnum
{
    const READ_UNCOMMITTED = 'READ UNCOMMITTED';
    const READ_COMMITTED = 'READ COMMITTED';
    const REPEATABLE_READ = 'REPEATABLE READ';
    const SERIALIZABLE = 'SERIALIZABLE';
}
