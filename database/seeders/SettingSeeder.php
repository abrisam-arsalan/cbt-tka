<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'app.school_name', 'value' => 'SMP Negeri 5 Tegal', 'type' => 'string', 'group' => 'general', 'label' => 'Nama Sekolah', 'description' => 'Tampil pada header kartu ujian.', 'is_public' => true],
            ['key' => 'app.school_city', 'value' => 'Tegal', 'type' => 'string', 'group' => 'general', 'label' => 'Kota Sekolah', 'description' => 'Tampil di bawah nama sekolah pada kartu.', 'is_public' => true],
            ['key' => 'exam.default_offline_grace', 'value' => '10', 'type' => 'int', 'group' => 'exam', 'label' => 'Default Grace Period (menit)', 'description' => 'Dipakai bila ujian tidak mengatur grace period sendiri.'],
            ['key' => 'exam.passing_score', 'value' => '75', 'type' => 'int', 'group' => 'exam', 'label' => 'Nilai KKM', 'description' => 'Ambang tuntas yang ditampilkan pada hasil ujian siswa.'],
        ];

        foreach ($defaults as $data) {
            Setting::updateOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }
}