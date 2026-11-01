<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AgentRate;
use App\Models\AgentTier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo agents (password "password") and agent rates, to try the agent prices:
 *   agent@safercruise.test  Standard type, 8% off everything
 *   gold@safercruise.test   Gold type, 10% off everything
 * plus a 5% rate for all agent types (agents without a type).
 * Not run by the tests (they set their own rates). Never run this seeder in production.
 *
 *   C:\xampp\php\php.exe artisan db:seed --class=DemoAgentSeeder
 */
class DemoAgentSeeder extends Seeder
{
    public function run(): void
    {
        $standard = AgentTier::firstOrCreate(['name' => 'Standard'], ['sort_order' => 1]);
        $gold = AgentTier::firstOrCreate(['name' => 'Gold'], ['sort_order' => 2]);

        // Every cruise and cabin, unless an admin already set one
        foreach ([[null, 5], [$standard->id, 8], [$gold->id, 10]] as [$tierId, $percent]) {
            AgentRate::firstOrCreate(
                ['agent_tier_id' => $tierId, 'cruise_id' => null, 'cabin_category_id' => null],
                ['percent' => $percent, 'is_active' => true],
            );
        }

        foreach ([
            ['agent@safercruise.test', 'Adel', 'Red Sea Travel', '501112233', $standard],
            ['gold@safercruise.test', 'Ghada', 'Nile Travel', '501112244', $gold],
        ] as [$email, $first, $company, $phone, $tier]) {
            $agent = User::updateOrCreate(['email' => $email], [
                'first_name' => $first,
                'last_name' => 'Agent',
                'company_name' => $company,
                'phone_dial' => '+966',
                'phone' => $phone,
                'password' => 'password',
            ]);
            $agent->forceFill(['role' => UserRole::Agent, 'agent_tier_id' => $tier->id, 'email_verified_at' => now()])->save();
        }
    }
}
