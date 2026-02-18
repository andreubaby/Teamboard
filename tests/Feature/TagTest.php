<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Tag;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_tags()
    {
        $user = User::factory()->create();
        Tag::create(['name' => 'Bug', 'color' => '#FF0000']);
        Tag::create(['name' => 'Feature', 'color' => '#00FF00']);

        $response = $this->actingAs($user)->getJson('/api/tags');

        $response->assertStatus(200)
                 ->assertJsonCount(2)
                 ->assertJsonFragment(['name' => 'Bug'])
                 ->assertJsonFragment(['name' => 'Feature']);
    }

    public function test_user_can_add_tag_to_card()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);
        $col = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);
        $card = Card::create(['board_column_id' => $col->id, 'title' => 'Task', 'position' => 0]);
        $tag = Tag::create(['name' => 'Urgent', 'color' => 'red']);

        $response = $this->actingAs($user)->postJson("/api/cards/{$card->id}/tags", [
            'tag_name' => 'Urgent'
        ]);

        $response->assertStatus(200);

        $this->assertTrue($card->fresh()->tags->contains($tag->id));
    }

    public function test_user_can_remove_tag_from_card()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);
        $col = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);
        $card = Card::create(['board_column_id' => $col->id, 'title' => 'Task', 'position' => 0]);
        $tag = Tag::create(['name' => 'Urgent', 'color' => 'red']);

        // Attach first
        $card->tags()->attach($tag->id);
        $this->assertTrue($card->fresh()->tags->contains($tag->id));

        $response = $this->actingAs($user)->deleteJson("/api/cards/{$card->id}/tags/{$tag->id}");

        $response->assertStatus(200);
        $this->assertFalse($card->fresh()->tags->contains($tag->id));
    }

    public function test_cannot_add_nonexistent_tag()
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P1', 'owner_id' => $user->id]);
        $col = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);
        $card = Card::create(['board_column_id' => $col->id, 'title' => 'Task', 'position' => 0]);

        $response = $this->actingAs($user)->postJson("/api/cards/{$card->id}/tags", [
            'tag_name' => 'NonExistent'
        ]);

        $response->assertStatus(422); // Validation fails
    }

    public function test_user_cannot_modify_tags_on_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = Project::create(['name' => 'P1', 'owner_id' => $owner->id]);
        $col = BoardColumn::create(['project_id' => $project->id, 'name' => 'C1']);
        $card = Card::create(['board_column_id' => $col->id, 'title' => 'Task', 'position' => 0]);
        $tag = Tag::create(['name' => 'Urgent', 'color' => 'red']);

        // Attempt add tag
        $response = $this->actingAs($otherUser)->postJson("/api/cards/{$card->id}/tags", [
            'tag_name' => 'Urgent'
        ]);
        $this->assertTrue(in_array($response->status(), [403, 404])); // Forbidden

        // Attach for delete test
        $card->tags()->attach($tag->id);

        // Attempt remove tag
        $responseDel = $this->actingAs($otherUser)->deleteJson("/api/cards/{$card->id}/tags/{$tag->id}");
        $this->assertTrue(in_array($responseDel->status(), [403, 404])); // Forbidden
    }
}

