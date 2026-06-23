<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\NotaryAvailabilitySlot;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotaryAvailabilitySlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notary = User::query()
            ->where('role', UserRole::Notary->value)
            ->first() ?? User::factory()->notary()->create();

        NotaryAvailabilitySlot::factory()
            ->count(5)
            ->create([
                'notary_user_id' => $notary->id,
            ]);
    }
}
