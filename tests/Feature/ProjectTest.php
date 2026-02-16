<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_projects()
    {
        $user = User::factory()->create();
        $project1 = Project::create(['name' => 'Project 1', 'owner_id' => $user->id]);
        $project2 = Project::create(['name' => 'Project 2', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['name' => 'Project 1'])
                 ->assertJsonFragment(['name' => 'Project 2']);
    }

    public function test_user_can_create_project()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New Project'
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'New Project']);

        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
            'owner_id' => $user->id
        ]);
    }

    public function test_user_can_update_project()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Old Name', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('projects', ['name' => 'Updated Name']);
    }

    public function test_user_can_delete_project()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'To Delete', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(200); // Or 204 depending on implementation
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_user_cannot_access_other_users_projects()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $project = Project::create(['name' => 'Private Project', 'owner_id' => $user1->id]);

        // User 2 tries to view User 1's board
        $response = $this->actingAs($user2)->getJson("/api/projects/{$project->id}/board");

        // Asumiendo que usas policies o scopes, esto debería ser 403 Forbidden o 404 Not Found
        // Si no tienes policies implementadas, podría fallar este test (y ser un bug de seguridad)
        // Ajustaré esto según lo que encuentre, pero por defecto espero 403 o 404.
        $this->assertTrue(in_array($response->status(), [403, 404]), "Status was {$response->status()}");
    }

    public function test_user_can_view_full_board_data()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Full Board', 'owner_id' => $user->id]);
        $column = \App\Models\BoardColumn::create(['project_id' => $project->id, 'name' => 'Col 1', 'position' => 0]);
        $card = \App\Models\Card::create([
            'board_column_id' => $column->id,
            'title' => 'Card 1',
            'position' => 0
        ]);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/board");

        $response->assertStatus(200)
                 ->assertJsonPath('project.id', $project->id)
                 ->assertJsonPath('columns.0.id', $column->id)
                 ->assertJsonPath('columns.0.cards.0.id', $card->id);
    }
}
