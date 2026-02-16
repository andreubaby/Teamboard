<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\BoardColumn;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_requires_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => '' // Empty name
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_column_requires_name_and_project_id()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/columns", [
            'name' => ''
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_card_requires_title_and_column_id()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);
        $column = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);

        // Missing fields
        $response = $this->actingAs($user)->postJson('/api/cards', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title', 'board_column_id']);
    }

    public function test_card_creation_validates_priority()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);
        $column = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);

        $response = $this->actingAs($user)->postJson('/api/cards', [
            'board_column_id' => $column->id,
            'title' => 'Valid Title',
            'priority' => 'INVALID_PRIORITY'
        ]);

        // Depending on validation rules (in, enum), this should fail
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['priority']);
    }

    public function test_reorder_columns_requires_array_of_ids()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}/columns/reorder", [
            'ordered_ids' => 'not-an-array'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['ordered_ids']);
    }
}

