<?php

namespace App\Models\Contracts;

/**
 * Governed content whose agent changes can be reverted: it records agent
 * changes (RecordsAgentChanges) and has a trash (Trashable / SoftDeletes).
 * RunReverter only touches models implementing this contract.
 */
interface RevertibleContent
{
    /** @return bool|null */
    public function restore();

    /** @return bool */
    public function trashed();
}
