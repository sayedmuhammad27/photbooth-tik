<?php

namespace Tests\Feature;

use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_queue_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/queue');

        $response->assertStatus(200);
        $response->assertSee('MANAJEMEN ANTREAN');
        $response->assertSee('DAFTAR ANTREAN HARI INI');
    }

    public function test_admin_can_toggle_queue_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/queue/toggle');
        $response->assertRedirect();

        $setting = QueueSetting::forToday();
        $this->assertTrue($setting->is_open);
    }

    public function test_admin_can_create_manual_queue_and_start_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/queue/manual', [
            'name'       => 'Pengunjung VIP',
            'auto_start' => '1',
        ]);

        $response->assertRedirect();

        $ticket = QueueTicket::today()->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('Pengunjung VIP', $ticket->name);
        $this->assertEquals('in_session', $ticket->status);
    }

    public function test_admin_can_call_specific_queue_number(): void
    {
        $user = User::factory()->create();
        $setting = QueueSetting::forToday();

        $ticket = QueueTicket::create([
            'queue_number' => 5,
            'name'         => 'Nomor Lima',
            'status'       => 'waiting',
        ]);

        $response = $this->actingAs($user)->post('/admin/queue/set-specific', [
            'target_number' => 5,
            'action'        => 'start_session',
        ]);

        $response->assertRedirect();
        $this->assertEquals('in_session', $ticket->fresh()->status);
    }

    public function test_welcome_page_displays_in_session_queue(): void
    {
        QueueTicket::create([
            'queue_number' => 7,
            'name'         => 'Giliran Tujuh',
            'status'       => 'in_session',
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('ANTREAN #7');
        $response->assertSee('Giliran Tujuh');
        $response->assertSee('MULAI SESI FOTO SEKARANG');
    }

    public function test_public_user_can_take_queue_ticket(): void
    {
        $setting = QueueSetting::forToday();
        $setting->update(['is_open' => true]);

        $response = $this->postJson('/queue/take', [
            'name' => 'Budi Expo',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'ticket'  => [
                'queue_number' => 1,
                'name'         => 'Budi Expo',
                'status'       => 'waiting',
            ],
        ]);
    }

    public function test_admin_queue_live_data_returns_html_and_state_hash(): void
    {
        $user = User::factory()->create();

        QueueTicket::create([
            'queue_number' => 1,
            'name'         => 'Antrean Pertama',
            'status'       => 'in_session',
        ]);
        QueueTicket::create([
            'queue_number' => 2,
            'name'         => 'Antrean Kedua',
            'status'       => 'waiting',
        ]);

        $response = $this->actingAs($user)->getJson('/admin/queue/live-data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'is_open',
            'current_serving',
            'stats' => ['total', 'waiting', 'in_session', 'called', 'completed', 'skipped'],
            'html_rows',
            'html_active',
            'state_hash',
            'last_ticket_id',
        ]);

        $this->assertStringContainsString('#1', $response->json('html_rows'));
        $this->assertStringContainsString('#2', $response->json('html_rows'));
        $this->assertEquals(2, $response->json('stats.total'));
        $this->assertEquals(1, $response->json('stats.waiting'));
        $this->assertEquals(1, $response->json('stats.in_session'));
    }

    public function test_admin_queue_ajax_action_returns_json(): void
    {
        $user = User::factory()->create();

        $ticket = QueueTicket::create([
            'queue_number' => 3,
            'name'         => 'Nomor Tiga',
            'status'       => 'waiting',
        ]);

        // Panggil via JSON
        $response = $this->actingAs($user)->postJson(route('admin.queue.set-specific'), [
            'target_number' => 3,
            'action'        => 'call',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('called', $ticket->fresh()->status);
    }
}
