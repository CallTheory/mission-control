<?php

namespace Database\Seeders;

use App\Models\EnterpriseHost;
use Illuminate\Database\Seeder;

class EnterpriseHostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EnterpriseHost::create([
            'name' => 'Test Enterprise Host',
            'senderID' => 'TEST001',
            'securityCode' => 'test-security-code-123',
            'enabled' => true,
            'callback_url' => 'https://example.com/wctp/receive',
            'phone_numbers' => ['+12025551234', '+13035555678'],
            'team_id' => null,
        ]);

        EnterpriseHost::create([
            'name' => 'Demo Host (Disabled)',
            'senderID' => 'DEMO001',
            'securityCode' => 'demo-security-code-456',
            'enabled' => false,
            'callback_url' => null,
            'phone_numbers' => ['+14155559999'],
            'team_id' => null,
        ]);
    }
}