<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Atv;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'is_verified' => true,
            'address' => '123 Admin Street',
        ]);

        // Create Manager User
        User::create([
            'first_name' => 'Manager',
            'last_name' => 'User',
            'username' => 'manager',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'role' => 'manager',
            'is_verified' => true,
            'address' => '456 Manager Avenue',
        ]);

        // Create Customer User
        User::create([
            'first_name' => 'John',
            'last_name' => 'Customer',
            'username' => 'johncustomer',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'role' => 'customer',
            'is_verified' => true,
            'address' => '789 Customer Lane',
        ]);

        // Create Sample ATVs
        $atvs = [
            [
                'name' => 'Honda TRX 250',
                'type' => '250cc Sport',
                'serial_number' => 'ATV-HON-001',
                'hourly_price' => 45.00,
                'status' => 'available',
                'description' => 'Perfect for beginners. Easy to handle with reliable performance.',
            ],
            [
                'name' => 'Yamaha Raptor 700',
                'type' => '700cc Sport',
                'serial_number' => 'ATV-YAM-002',
                'hourly_price' => 75.00,
                'status' => 'available',
                'description' => 'High-performance sport ATV for experienced riders.',
            ],
            [
                'name' => 'Polaris Sportsman 450',
                'type' => '450cc Utility',
                'serial_number' => 'ATV-POL-003',
                'hourly_price' => 55.00,
                'status' => 'available',
                'description' => 'Versatile utility ATV with great towing capacity.',
            ],
            [
                'name' => 'Can-Am Outlander 570',
                'type' => '570cc Utility',
                'serial_number' => 'ATV-CAN-004',
                'hourly_price' => 65.00,
                'status' => 'available',
                'description' => 'Powerful utility ATV with advanced suspension.',
            ],
            [
                'name' => 'Kawasaki KFX 50',
                'type' => '50cc Youth',
                'serial_number' => 'ATV-KAW-005',
                'hourly_price' => 25.00,
                'status' => 'available',
                'description' => 'Safe and fun ATV designed for young riders.',
            ],
            [
                'name' => 'Honda FourTrax Rancher',
                'type' => '420cc Utility',
                'serial_number' => 'ATV-HON-006',
                'hourly_price' => 50.00,
                'status' => 'maintenance',
                'description' => 'Reliable utility ATV with automatic transmission.',
            ],
            [
                'name' => 'Yamaha Grizzly 700',
                'type' => '700cc Utility',
                'serial_number' => 'ATV-YAM-007',
                'hourly_price' => 70.00,
                'status' => 'available',
                'description' => 'Premium utility ATV with power steering.',
            ],
            [
                'name' => 'Polaris RZR 1000',
                'type' => '1000cc Sport',
                'serial_number' => 'ATV-POL-008',
                'hourly_price' => 95.00,
                'status' => 'available',
                'description' => 'High-performance side-by-side for thrill seekers.',
            ],
        ];

        foreach ($atvs as $atv) {
            Atv::create($atv);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@example.com / password123');
        $this->command->info('Manager login: manager@example.com / password123');
        $this->command->info('Customer login: customer@example.com / password123');
    }
}
