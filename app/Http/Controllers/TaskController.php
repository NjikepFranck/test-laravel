<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        return $project->tasks()
            ->when($project->responsable_id !== $request->user()->id,
                fn ($query) => $query->where('assigned_user_id', $request->user()->id))
            ->orderBy('due_date')->orderBy('id')->paginate(20);
    }

    public function update(Request $request, Task $task)
    {
        Gate::authorize('update', $task);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
            'project_id' => ['prohibited'],
            'assigned_user_id' => ['prohibited'],
            'title' => ['prohibited'],
            'due_date' => ['prohibited'],
        ]);
        $task->update(['status' => $validated['status']]);

        return response()->json(['data' => $task->fresh()]);
    }
}
