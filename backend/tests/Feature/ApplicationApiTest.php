<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_application_starts_in_created_and_ignores_status_input(): void
    {
        $this->postJson('/api/v1/applications', [
            'title' => 'Nueva',
            'description' => 'Desc',
            'status' => 'done',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.allowed_transitions', ['in_review']);
    }

    public function test_create_requires_title(): void
    {
        $this->postJson('/api/v1/applications', ['description' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_list_show_and_filter(): void
    {
        Application::factory()->count(2)->create();
        Application::factory()->status(ApplicationStatus::Paused)->create();

        $this->getJson('/api/v1/applications')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/applications?status=paused')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/applications?status=nope')->assertUnprocessable();

        $app = Application::first();
        $this->getJson("/api/v1/applications/{$app->id}")->assertOk()->assertJsonPath('data.id', $app->id);
        $this->getJson('/api/v1/applications/999999')->assertNotFound();
    }

    public function test_update_title_and_description_only(): void
    {
        $app = Application::factory()->create();

        $this->putJson("/api/v1/applications/{$app->id}", [
            'title' => 'Editado',
            'description' => 'Nueva desc',
            'status' => 'done',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Editado')
            ->assertJsonPath('data.status', 'created');
    }

    public function test_full_happy_path_with_pause_and_resume(): void
    {
        $app = Application::factory()->create();

        foreach (['in_review', 'paused', 'in_review', 'in_progress', 'paused', 'in_progress', 'done'] as $target) {
            $this->postJson("/api/v1/applications/{$app->id}/transition", ['target_status' => $target])
                ->assertOk()
                ->assertJsonPath('data.status', $target);
        }

        $this->getJson("/api/v1/applications/{$app->id}/transitions")
            ->assertOk()
            ->assertJsonPath('data.current_status', 'done')
            ->assertJsonPath('data.allowed_transitions', [])
            ->assertJsonCount(7, 'data.history');
    }

    public function test_invalid_transition_returns_400_and_does_not_touch_db(): void
    {
        $app = Application::factory()->create();
        $updatedAt = $app->fresh()->updated_at;

        $this->postJson("/api/v1/applications/{$app->id}/transition", ['target_status' => 'in_progress'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_transition')
            ->assertJsonPath('current_status', 'created')
            ->assertJsonPath('allowed_transitions', ['in_review']);

        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'created', 'updated_at' => $updatedAt]);
        $this->assertDatabaseCount('application_status_logs', 0);
    }

    public function test_paused_cannot_go_directly_to_done(): void
    {
        $app = Application::factory()->status(ApplicationStatus::Paused)->create();

        $this->postJson("/api/v1/applications/{$app->id}/transition", ['target_status' => 'done'])
            ->assertStatus(400);

        $this->assertSame(ApplicationStatus::Paused, $app->fresh()->status);
    }

    public function test_created_cannot_be_paused(): void
    {
        $app = Application::factory()->create();

        $this->postJson("/api/v1/applications/{$app->id}/transition", ['target_status' => 'paused'])
            ->assertStatus(400);
    }

    public function test_unknown_target_status_is_a_validation_error(): void
    {
        $app = Application::factory()->create();

        $this->postJson("/api/v1/applications/{$app->id}/transition", ['target_status' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_status');
    }

    public function test_statuses_catalog_uses_codes(): void
    {
        $this->getJson('/api/v1/statuses')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.code', 'created')
            ->assertJsonPath('data.3.code', 'paused')
            ->assertJsonPath('data.3.allowed_transitions', ['in_review', 'in_progress']);
    }
}
