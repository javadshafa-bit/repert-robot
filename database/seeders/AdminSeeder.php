<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder {
    public function run(): void {
        DB::table('users')->insert([
            'name'       => 'مدیر کل',
            'email'      => 'javad.shafa@gmail.com',
            'password'   => Hash::make('Z0cCR4lC0*bm'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}