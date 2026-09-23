<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['username' => 'siswa01', 'name' => 'Andi Pratama'],
            ['username' => 'siswa02', 'name' => 'Budi Santoso'],
            ['username' => 'siswa03', 'name' => 'Citra Dewi'],
            ['username' => 'siswa04', 'name' => 'Dian Permata'],
            ['username' => 'siswa05', 'name' => 'Eko Prasetyo'],
        ];

        foreach ($students as $i => $data) {
            User::create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => null,
                'password' => Hash::make('siswa123'),
                'role' => 'siswa',
                'class_id' => ($i % 2) + 1, // 1,2,1,2,1
                'is_active' => true,
            ]);
        }
    }
}