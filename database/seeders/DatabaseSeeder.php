<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $users = [];
            foreach (['manager' => 'Responsable Orbit', 'alice' => 'Alice', 'bob' => 'Bob', 'outsider' => 'Sans accès'] as $key => $name) {
                $users[$key] = User::firstOrCreate(['email' => $key.'@orbit.test'], ['name' => $name, 'password' => 'Orbit-demo-2026!']);
            }
            $project = Project::firstOrCreate(['name' => 'Lancement Orbit', 'responsable_id' => $users['manager']->id]);
            foreach ([
                ['title' => 'Cadrer le besoin', 'assigned_user_id' => $users['alice']->id, 'status' => 'TODO', 'due_date' => today()->subDay()],
                ['title' => 'Implémenter API', 'assigned_user_id' => $users['bob']->id, 'status' => 'IN_PROGRESS', 'due_date' => today()->addDays(3)],
                ['title' => 'Valider le périmètre', 'assigned_user_id' => $users['alice']->id, 'status' => 'DONE', 'due_date' => today()->subDays(2)],
            ] as $task) {
                $project->tasks()->firstOrCreate(['title' => $task['title']], $task);
            }
        });
    }
}
