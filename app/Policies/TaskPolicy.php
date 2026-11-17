<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $task->assigned_user_id === $user->id
            || $task->project->responsable_id === $user->id;
    }
}
