<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolPassOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $weekdays = [1, 2, 3, 4, 5];

        foreach ($weekdays as $weekday) {
            DB::table('group_access_schedules')->updateOrInsert(
                ['group_id' => 1, 'weekday' => $weekday],
                [
                    'school_id' => 1,
                    'entry_time' => '07:00:00',
                    'grace_until' => '07:10:00',
                    'late_until' => '07:30:00',
                    'exit_time' => '13:00:00',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('group_access_schedules')->updateOrInsert(
                ['group_id' => 2, 'weekday' => $weekday],
                [
                    'school_id' => 1,
                    'entry_time' => '07:00:00',
                    'grace_until' => '07:10:00',
                    'late_until' => '07:30:00',
                    'exit_time' => '13:30:00',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('group_access_schedules')->updateOrInsert(
                ['group_id' => 3, 'weekday' => $weekday],
                [
                    'school_id' => 1,
                    'entry_time' => '08:00:00',
                    'grace_until' => '08:15:00',
                    'late_until' => '08:40:00',
                    'exit_time' => '14:30:00',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Taller de Química: autorizado para Bachillerato 3B.
        DB::table('area_access_rules')->updateOrInsert(
            [
                'school_id' => 1,
                'area_id' => 2,
                'applies_to_type' => 'group',
                'applies_to_id' => 3,
            ],
            [
                'role_name' => null,
                'weekday' => null,
                'starts_at' => '09:00:00',
                'ends_at' => '12:00:00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}