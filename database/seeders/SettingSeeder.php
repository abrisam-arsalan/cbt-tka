<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'app.school_name', 'value' => 'SMA Negeri 1 Contoh', 'type' => 'string', 'group' => 'card', 'label' => 'Nama Sekolah', 'is_public' => true],
            ['key' => 'app.school_city', 'value' => 'Jakarta', 'type' => 'string', 'group' => 'card', 'label' => 'Kota', 'is_public' => true],
            ['key' => 'exam.default_offline_grace', 'value' => '10', 'type' => 'int', 'group' => 'exam', 'label' => 'Default Grace Period (menit)'],
            ['key' => 'exam.passing_score', 'value' => '75.00', 'type' => 'int', 'group' => 'exam', 'label' => 'Nilai KKM'],
            ['key' => 'anti_cheat.strict_mode', 'value' => '0', 'type' => 'bool', 'group' => 'anti_cheat', 'label' => 'Mode Ketat'],
        ];

        foreach ($defaults as $data) {
            Setting::updateOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }
}