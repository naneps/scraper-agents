<?php

namespace Tests\Feature\Admin;

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_sources_index()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/sources');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_create_source()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/sources', [
            'name' => 'Detik News',
            'base_url' => 'https://news.detik.com',
            'selector_title' => 'h1.detail__title',
            'selector_body' => 'div.detail__body-text',
            'schedule_type' => 'interval',
            'schedule_value' => '60',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/sources');
        $this->assertDatabaseHas('sources', [
            'name' => 'Detik News',
        ]);
    }

    public function test_authenticated_user_can_update_source()
    {
        $user = User::factory()->create();
        
        $source = Source::create([
            'name' => 'Old Name',
            'base_url' => 'https://old.com',
            'selector_title' => 'h1',
            'selector_body' => 'div',
            'schedule_type' => 'interval',
            'schedule_value' => '60',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put('/admin/sources/' . $source->id, [
            'name' => 'New Name',
            'base_url' => 'https://new.com',
            'selector_title' => 'h1',
            'selector_body' => 'div',
            'schedule_type' => 'interval',
            'schedule_value' => '60',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/sources');
        $this->assertDatabaseHas('sources', [
            'id' => $source->id,
            'name' => 'New Name',
        ]);
    }

    public function test_authenticated_user_can_delete_source()
    {
        $user = User::factory()->create();
        
        $source = Source::create([
            'name' => 'To Delete',
            'base_url' => 'https://delete.com',
            'selector_title' => 'h1',
            'selector_body' => 'div',
            'schedule_type' => 'interval',
            'schedule_value' => '60',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete('/admin/sources/' . $source->id);

        $response->assertRedirect('/admin/sources');
        $this->assertDatabaseMissing('sources', [
            'id' => $source->id,
        ]);
    }
}
