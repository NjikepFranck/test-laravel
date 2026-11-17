<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->responsable_id === $user->id
            || $project->tasks()->where('assigned_user_id', $user->id)->exists();
    }
}
