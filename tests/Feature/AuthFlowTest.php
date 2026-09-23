<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/siswa/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_can_login_and_access_dashboard(): void
    {
        $admin = User::create([
            'username' => 'admin.test',
            'name' => 'Admin Test',
            'password' => 'password123',
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ]);

        $this->post('/login', [
            'username' => 'admin.test',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_siswa_cannot_access_admin_area(): void
    {
        $siswa = User::create([
            'username' => 'siswa.test',
            'name' => 'Siswa Test',
            'password' => 'password123',
            'role' => UserRole::Siswa->value,
            'is_active' => true,
        ]);

        $this->actingAs($siswa)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_siswa_can_access_student_dashboard(): void
    {
        $siswa = User::create([
            'username' => 'siswa.test2',
            'name' => 'Siswa Test 2',
            'password' => 'password123',
            'role' => UserRole::Siswa->value,
            'is_active' => true,
        ]);

        $this->actingAs($siswa)
            ->get('/siswa/dashboard')
            ->assertOk();
    }
}
