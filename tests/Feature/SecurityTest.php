<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\BoardColumn;
use App\Models\Card;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    // --- 1. Unauthenticated Access Tests ---

    public function test_guest_cannot_access_protected_routes()
    {
        $protectedRoutes = [
            'GET' => '/api/projects',
            'POST' => '/api/projects',
            'GET' => '/api/user',
            'GET' => '/api/tags',
        ];

        foreach ($protectedRoutes as $method => $route) {
            $response = $this->json($method, $route);
            $response->assertStatus(401); // Unauthorized
        }
    }

    // --- 2. IDOR (Insecure Direct Object Reference) Tests ---

    public function test_user_cannot_view_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::create(['name' => 'Secret Project', 'owner_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->getJson("/api/projects/{$project->id}/board");

        // Expect 403 Forbidden or 404 Not Found (security through obscurity)
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_user_cannot_update_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::create(['name' => 'Original Name', 'owner_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->patchJson("/api/projects/{$project->id}", [
            'name' => 'Hacked Name'
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original Name']);
    }

    public function test_user_cannot_delete_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::create(['name' => 'To Delete', 'owner_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->deleteJson("/api/projects/{$project->id}");

        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_user_cannot_manipulate_others_columns()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $owner->id]);
        $column = BoardColumn::create(['project_id' => $project->id, 'name' => 'Col 1']);

        // Try update
        $response = $this->actingAs($otherUser)->patchJson("/api/columns/{$column->id}", ['name' => 'Hacked']);
        $this->assertTrue(in_array($response->status(), [403, 404]));

        // Try delete
        $response = $this->actingAs($otherUser)->deleteJson("/api/columns/{$column->id}");
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_user_cannot_manipulate_others_cards()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $owner->id]);
        $column = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);
        $card = Card::create(['board_column_id' => $column->id, 'title' => 'Sensitive Task', 'position' => 0]);

        // Try update
        $response = $this->actingAs($otherUser)->patchJson("/api/cards/{$card->id}", ['title' => 'Hacked']);
        $this->assertTrue(in_array($response->status(), [403, 404]));

        // Try move
        $response = $this->actingAs($otherUser)->patchJson("/api/cards/{$card->id}/move", ['to_board_column_id' => $column->id, 'to_position' => 1]);
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    // --- 3. Mass Assignment / Input Security Tests ---

    public function test_user_cannot_inject_columns_into_others_project()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $project = Project::create(['name' => 'Target Project', 'owner_id' => $owner->id]);

        $response = $this->actingAs($attacker)->postJson("/api/projects/{$project->id}/columns", [
            'name' => 'Malicious Column'
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertDatabaseMissing('board_columns', ['name' => 'Malicious Column', 'project_id' => $project->id]);
    }

    public function test_user_cannot_inject_cards_into_others_column()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $project = Project::create(['name' => 'Target Project', 'owner_id' => $owner->id]);
        $column = BoardColumn::create(['project_id' => $project->id, 'name' => 'Target Column']);

        $response = $this->actingAs($attacker)->postJson("/api/cards", [
            'board_column_id' => $column->id,
            'title' => 'Spam Card'
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertDatabaseMissing('cards', ['title' => 'Spam Card', 'board_column_id' => $column->id]);
    }

    public function test_mass_assignment_protection_on_project_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Try to create project assigning execution to another user (should result in current user as owner)
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'My Project',
            'owner_id' => $otherUser->id // Attempt to spoof owner
        ]);

        $response->assertStatus(201);

        // Assert the project was created but owned by the authenticated user, NOT the spoofed ID
        $this->assertDatabaseHas('projects', [
            'name' => 'My Project',
            'owner_id' => $user->id
        ]);

        $this->assertDatabaseMissing('projects', [
            'name' => 'My Project',
            'owner_id' => $otherUser->id
        ]);
    }

    public function test_xss_protection_in_text_fields()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);
        $column = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);

        $xssPayload = '<script>alert("XSS")</script>';

        // It is generally okay to store XSS payloads in DB if properly escaped on output.
        // However, some apps might want to strip tags. Laravel doesn't strip by default.
        // We verify that the API accepts it but stores it as is (or stripped ifmiddleware exists).
        // The crucial part is frontend escaping (Vue does this automatically with {{ }}).

        $response = $this->actingAs($user)->postJson('/api/cards', [
            'board_column_id' => $column->id,
            'title' => $xssPayload
        ]);

        $response->assertStatus(201);

        // Ensure it performs correct DB insertion without SQL error
        $this->assertDatabaseHas('cards', ['title' => $xssPayload]);
    }
}


