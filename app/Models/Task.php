<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    public const STATUSES = ['TODO', 'IN_PROGRESS', 'DONE'];

    protected $fillable = ['project_id', 'assigned_user_id', 'title', 'status', 'due_date'];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'DONE' && $this->due_date->lt(today());
    }
}
