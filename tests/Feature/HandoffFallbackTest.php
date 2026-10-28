<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoffFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_sso_tanpa_kode_redirect_ke_login(): void
    {
        $this->get('/sso')->assertRedirect(route('login'));
    }

    public function test_sso_tanpa_konfigurasi_fallback_ke_login(): void
    {
        // Tanpa CBT_PORTAL_BASE_URL & CBT_HANDOFF_SECRET → fallback login manual.
        config(['cbt.portal_base_url' => '', 'cbt.handoff_secret' => '']);
        $this->get('/sso?kode=apa-pun')->assertRedirect(route('login'));
    }
}
