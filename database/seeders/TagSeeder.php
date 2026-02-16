<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        \App\Models\Tag::insert([
            ['name' => 'Backend', 'color' => '#3b82f6'], // Azul
            ['name' => 'Frontend', 'color' => '#10b981'], // Verde
            ['name' => 'Design',   'color' => '#ec4899'], // Rosa
            ['name' => 'Bug',      'color' => '#ef4444'], // Rojo
            ['name' => 'Docs',     'color' => '#f59e0b'], // Naranja
        ]);
    }
}
