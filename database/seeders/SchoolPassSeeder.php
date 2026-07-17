<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SchoolPassSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('schools')->insert([
            'id' => 1,
            'name' => 'Colegio Demo Veracruz',
            'slug' => 'colegio-demo-veracruz',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('campuses')->insert([
            'id' => 1,
            'school_id' => 1,
            'name' => 'Campus Centro',
            'address' => 'Veracruz, México',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'name' => 'Director Demo',
                'email' => 'director@demo.test',
                'phone' => '2290000001',
                'password' => Hash::make('12345678'),
                'role' => 'director',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'school_id' => 1,
                'name' => 'Prefecto Demo',
                'email' => 'prefecto@demo.test',
                'phone' => '2290000002',
                'password' => Hash::make('12345678'),
                'role' => 'prefect',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'school_id' => 1,
                'name' => 'Kiosco Entrada Principal',
                'email' => 'kiosco@demo.test',
                'phone' => null,
                'password' => Hash::make('12345678'),
                'role' => 'kiosk',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
    'id' => 4,
    'school_id' => 1,
    'name' => 'Mamá de Juan',
    'email' => 'mama.juan@demo.test',
    'phone' => '2291111111',
    'password' => Hash::make('12345678'),
    'role' => 'guardian',
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
],
        ]);

        DB::table('academic_levels')->insert([
            ['id' => 1, 'school_id' => 1, 'name' => 'Primaria', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'school_id' => 1, 'name' => 'Secundaria', 'sort_order' => 2, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'school_id' => 1, 'name' => 'Bachillerato', 'sort_order' => 3, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('academic_cycles')->insert([
            'id' => 1,
            'school_id' => 1,
            'name' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-07-31',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_groups')->insert([
            ['id' => 1, 'school_id' => 1, 'campus_id' => 1, 'academic_level_id' => 1, 'academic_cycle_id' => 1, 'name' => 'Primaria 2B', 'grade_label' => '2', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'school_id' => 1, 'campus_id' => 1, 'academic_level_id' => 2, 'academic_cycle_id' => 1, 'name' => 'Secundaria 1A', 'grade_label' => '1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'school_id' => 1, 'campus_id' => 1, 'academic_level_id' => 3, 'academic_cycle_id' => 1, 'name' => 'Bachillerato 3B', 'grade_label' => '3er semestre', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('students')->insert([
            ['id' => 1, 'school_id' => 1, 'campus_id' => 1, 'current_group_id' => 1, 'student_code' => 'A0001', 'first_name' => 'Juan', 'last_name' => 'Pérez López', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'school_id' => 1, 'campus_id' => 1, 'current_group_id' => 1, 'student_code' => 'A0002', 'first_name' => 'María', 'last_name' => 'López García', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'school_id' => 1, 'campus_id' => 1, 'current_group_id' => 2, 'student_code' => 'A0003', 'first_name' => 'Luis', 'last_name' => 'García Torres', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'school_id' => 1, 'campus_id' => 1, 'current_group_id' => 3, 'student_code' => 'A0004', 'first_name' => 'Andrea', 'last_name' => 'Torres Ruiz', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('guardians')->insert([
          [
    'id' => 1,
    'school_id' => 1,
    'user_id' => 4,
    'first_name' => 'Mamá de Juan',
    'last_name' => 'López',
    'phone' => '2291111111',
    'email' => 'mama.juan@demo.test',
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
],
        ]);

        DB::table('student_guardians')->insert([
            ['student_id' => 1, 'guardian_id' => 1, 'relationship' => 'madre', 'can_view_attendance' => 1, 'can_receive_notifications' => 1, 'can_authorize_exit' => 1, 'is_primary' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('areas')->insert([
            ['id' => 1, 'school_id' => 1, 'campus_id' => 1, 'name' => 'Entrada Principal', 'type' => 'main_access', 'affects_attendance' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'school_id' => 1, 'campus_id' => 1, 'name' => 'Taller de Química', 'type' => 'lab', 'affects_attendance' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('access_devices')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'campus_id' => 1,
                'area_id' => 1,
                'name' => 'Tablet Prefectura Entrada',
                'device_uuid' => 'tablet-prefectura-entrada-001',
                'platform' => 'web',
                'device_type' => 'prefect_app',
                'assigned_to_user_id' => 2,
                'mode' => 'attendance',
                'default_event_type' => 'entry',
                'can_unlock' => 0,
                'allow_manual_search' => 1,
                'show_student_photo' => 1,
                'auto_reset_seconds' => 3,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'school_id' => 1,
                'campus_id' => 1,
                'area_id' => 1,
                'name' => 'Kiosco Entrada Principal',
                'device_uuid' => 'kiosco-entrada-principal-001',
                'platform' => 'web',
                'device_type' => 'kiosk',
                'assigned_to_user_id' => 3,
                'mode' => 'attendance',
                'default_event_type' => 'entry',
                'can_unlock' => 0,
                'allow_manual_search' => 0,
                'show_student_photo' => 1,
                'auto_reset_seconds' => 3,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('student_credentials')->insert([
            ['school_id' => 1, 'student_id' => 1, 'type' => 'qr', 'token_hash' => hash('sha256', 'QR-JUAN-0001'), 'public_code' => 'QR-JUAN-0001', 'status' => 'active', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => 1, 'student_id' => 2, 'type' => 'qr', 'token_hash' => hash('sha256', 'QR-MARIA-0002'), 'public_code' => 'QR-MARIA-0002', 'status' => 'active', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}