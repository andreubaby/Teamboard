<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\BoardColumn;
use Illuminate\Support\Facades\Hash;

class DevProjectSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Usuario (si no existe)
        $user = User::firstOrCreate(
            ['email' => 'aleja@babyplant.es'],
            [
                'name' => 'Alejandro',
                'password' => Hash::make('password123'),
            ]
        );

        // 2. Proyecto
        $project = Project::firstOrCreate(
            [
                'name' => 'Teamboard',
                'owner_id' => $user->id,
            ]
        );

        // 3. Columnas Kanban por defecto
        $columns = [
            'To Do',
            'In Progress',
            'Review',
            'Done',
        ];

        foreach ($columns as $index => $name) {
            BoardColumn::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $name,
                ],
                [
                    'position' => $index,
                ]
            );
        }

        $this->command->info('✔ DevProjectSeeder ejecutado correctamente');
    }
}
