<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrbitApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function loginAs(string $name): void
    {
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode($name.'@orbit.test:Orbit-demo-2026!')]);
    }

    public function test_authentication_is_required_on_all_endpoints(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson('/api/projects/1/tasks')->assertUnauthorized();
        $this->patchJson('/api/tasks/1/status', ['status' => 'DONE'])->assertUnauthorized();
    }

    public function test_invalid_credentials_are_rejected_even_without_accept_header(): void
    {
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('alice@orbit.test:wrong')])
            ->get('/api/projects')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_outsider_cannot_read_or_modify_project_work(): void
    {
        $this->loginAs('outsider');
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/projects/1/tasks')->assertForbidden();
        $this->patchJson('/api/tasks/1/status', ['status' => 'DONE'])->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => 1, 'status' => 'TODO']);
    }

    public function test_collaborator_sees_only_accessible_projects_and_own_tasks(): void
    {
        $outsider = User::where('email', 'outsider@orbit.test')->firstOrFail();
        Project::create(['name' => 'Secret', 'responsable_id' => $outsider->id]);
        $this->loginAs('alice');
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
        $response = $this->getJson('/api/projects/1/tasks')->assertOk()->assertJsonCount(2, 'data');
        $alice = User::where('email', 'alice@orbit.test')->firstOrFail();
        foreach ($response->json('data') as $task) {
            $this->assertSame($alice->id, $task['assigned_user_id']);
        }
        $this->getJson('/api/projects/2/tasks')->assertForbidden();
    }

    public function test_collaborator_cannot_modify_another_collaborators_task(): void
    {
        $this->loginAs('alice');
        $this->patchJson('/api/tasks/2/status', ['status' => 'DONE'])->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => 2, 'status' => 'IN_PROGRESS']);
    }

    public function test_assignee_can_change_all_supported_statuses(): void
    {
        $this->loginAs('alice');
        foreach (Task::STATUSES as $status) {
            $this->patchJson('/api/tasks/1/status', ['status' => $status])
                ->assertOk()->assertJsonPath('data.status', $status);
            $this->assertDatabaseHas('tasks', ['id' => 1, 'status' => $status]);
        }
    }

    public function test_manager_sees_all_tasks_and_can_update_them(): void
    {
        $this->loginAs('manager');
        $this->getJson('/api/projects/1/tasks')->assertOk()->assertJsonCount(3, 'data');
        $this->patchJson('/api/tasks/2/status', ['status' => 'DONE'])->assertOk();
    }

    public function test_invalid_status_missing_status_and_reassignment_are_rejected(): void
    {
        $this->loginAs('alice');
        foreach ([[], ['status' => 'INVALID'], ['status' => null], ['status' => ['DONE']]] as $body) {
            $this->patchJson('/api/tasks/1/status', $body)->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->patchJson('/api/tasks/1/status', ['status' => 'DONE', 'assigned_user_id' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_user_id');
        $this->assertDatabaseHas('tasks', ['id' => 1, 'status' => 'TODO', 'assigned_user_id' => 2]);
    }

    public function test_unknown_resources_return_json_404(): void
    {
        $this->loginAs('manager');
        $this->getJson('/api/projects/999/tasks')->assertNotFound();
        $this->patchJson('/api/tasks/999/status', ['status' => 'DONE'])->assertNotFound();
    }

    public function test_overdue_rule_excludes_done_and_includes_only_past_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->startOfDay());
        $task = Task::firstOrFail();
        $task->update(['status' => 'TODO', 'due_date' => '2026-11-16']);
        $this->assertTrue($task->is_overdue);
        $task->update(['status' => 'DONE']);
        $this->assertFalse($task->is_overdue);
        $task->update(['status' => 'TODO', 'due_date' => '2026-11-17']);
        $this->assertFalse($task->is_overdue);
        $task->update(['due_date' => '2026-11-18']);
        $this->assertFalse($task->is_overdue);
    }

    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('tasks', 3);
    }
}
