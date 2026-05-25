<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Zone;
use App\Models\Business;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Zones
        $zoneA = Zone::create(['name' => 'Zone A - Central Business District']);
        $zoneB = Zone::create(['name' => 'Zone B - Industrial Area']);

        // Users
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@ratepoint.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin'
        ]);

        User::create([
            'name' => 'John Finance',
            'email' => 'finance@ratepoint.com',
            'password' => Hash::make('password'),
            'role' => 'finance_officer'
        ]);

        User::create([
            'name' => 'Kwame Agent',
            'email' => 'agent@ratepoint.com',
            'password' => Hash::make('password'),
            'role' => 'field_agent',
            'zone_id' => $zoneA->id
        ]);

        // Businesses
        Business::create([
            'name' => "Ama's Provision Shop",
            'owner_name' => 'Ama Serwaa',
            'gps_lat' => 5.6037,
            'gps_lng' => -0.1870,
            'zone_id' => $zoneA->id,
            'structure_type' => 'Permanent',
            'levy_type' => 'Business Operating Permit',
            'fee_amount' => 150.00,
            'status' => 'unpaid'
        ]);

        Business::create([
            'name' => "Kofi Brothers Garage",
            'owner_name' => 'Kofi Mensah',
            'gps_lat' => 5.6100,
            'gps_lng' => -0.1900,
            'zone_id' => $zoneA->id,
            'structure_type' => 'Temporary',
            'levy_type' => 'Store Levy',
            'fee_amount' => 300.00,
            'status' => 'unpaid'
        ]);
    }
}
