<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\NotaryAppointment;
use App\Models\NotaryAvailabilitySlot;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotaryAppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notary = User::query()
            ->where('role', UserRole::Notary->value)
            ->first() ?? User::factory()->notary()->create();
        $client = User::factory()->enotarySigner()->create();

        $slot = NotaryAvailabilitySlot::query()
            ->where('notary_user_id', $notary->id)
            ->where('is_booked', false)
            ->where('is_blocked', false)
            ->where('date', '>=', today())
            ->first() ?? NotaryAvailabilitySlot::factory()->create([
                'notary_user_id' => $notary->id,
            ]);

        $notaryRequest = NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'title' => 'Sample appointment notarization',
        ]);

        $slot->update(['is_booked' => true]);

        NotaryAppointment::factory()->create([
            'notary_availability_slot_id' => $slot->id,
            'notary_request_id' => $notaryRequest->id,
            'client_user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => 'pending',
        ]);
    }
}
