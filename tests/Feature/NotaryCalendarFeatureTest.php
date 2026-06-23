<?php

namespace Tests\Feature;

use App\Models\NotaryAppointment;
use App\Models\NotaryAvailabilitySlot;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotaryCalendarFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_notary_calendar_page_loads(): void
    {
        $notary = User::factory()->notary()->create();

        $this->actingAs($notary)
            ->get(route('notary.calendar'))
            ->assertOk()
            ->assertSee('Calendar')
            ->assertSee('Upcoming bookings');
    }

    public function test_notary_can_add_repeating_availability_slots(): void
    {
        $notary = User::factory()->notary()->create();
        $startDate = now()->addDay()->toDateString();

        $this->actingAs($notary);

        LivewireVolt::test('notary.calendar')
            ->set('selectedDate', $startDate)
            ->set('newSlotStartTime', '09:00')
            ->set('newSlotEndTime', '10:00')
            ->set('newSlotDuration', 60)
            ->set('repeatWeekly', true)
            ->set('repeatWeeks', 3)
            ->call('addSlot')
            ->assertHasNoErrors();

        $this->assertSame(3, NotaryAvailabilitySlot::query()
            ->where('notary_user_id', $notary->id)
            ->where('start_time', '09:00')
            ->count());
    }

    public function test_notary_can_delete_unbooked_slot(): void
    {
        $notary = User::factory()->notary()->create();
        $slot = NotaryAvailabilitySlot::factory()->create([
            'notary_user_id' => $notary->id,
            'date' => now()->addDay()->toDateString(),
            'is_booked' => false,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.calendar')
            ->call('deleteSlot', $slot->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('notary_availability_slots', [
            'id' => $slot->id,
        ]);
    }

    public function test_notary_can_confirm_and_cancel_appointments(): void
    {
        $notary = User::factory()->notary()->create();
        $client = User::factory()->enotarySigner()->create();
        $slot = NotaryAvailabilitySlot::factory()->booked()->create([
            'notary_user_id' => $notary->id,
            'date' => now()->addDay()->toDateString(),
        ]);
        $appointment = NotaryAppointment::factory()->create([
            'notary_availability_slot_id' => $slot->id,
            'notary_request_id' => null,
            'client_user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => 'pending',
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.calendar')
            ->call('confirmAppointment', $appointment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notary_appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);

        LivewireVolt::test('notary.calendar')
            ->call('cancelAppointment', $appointment->id, 'Client unavailable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notary_appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Client unavailable',
        ]);
        $this->assertDatabaseHas('notary_availability_slots', [
            'id' => $slot->id,
            'is_booked' => false,
        ]);
    }

    public function test_client_can_book_available_notary_slot(): void
    {
        $notary = User::factory()->notary()->create();
        $client = User::factory()->enotarySigner()->create();
        $slot = NotaryAvailabilitySlot::factory()->create([
            'notary_user_id' => $notary->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);
        $notaryRequest = NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'title' => 'Affidavit notarization',
        ]);

        $this->actingAs($client)
            ->get(route('notary.appointment.book', $notary))
            ->assertOk()
            ->assertSee('Book an appointment')
            ->assertSee('1:00 PM');

        $this->actingAs($client);

        LivewireVolt::test('notary-requests.book-appointment', ['notaryUser' => $notary])
            ->set('selectedSlotId', $slot->id)
            ->set('notaryRequestId', $notaryRequest->id)
            ->set('notes', 'Please review my affidavit.')
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect(route('notary-requests.index'));

        $this->assertDatabaseHas('notary_appointments', [
            'notary_availability_slot_id' => $slot->id,
            'notary_request_id' => $notaryRequest->id,
            'client_user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notary_availability_slots', [
            'id' => $slot->id,
            'is_booked' => true,
        ]);

        $this->actingAs($client)
            ->get(route('notary.appointment.book', $notary))
            ->assertOk()
            ->assertDontSee('1:00 PM');
    }
}
