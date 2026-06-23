<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use App\Services\NotarySignerVideoInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotaryCasePageDuplicateRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_http_response_contains_single_case_workspace(): void
    {
        [$notary, $request] = $this->seedVideoSessionCase();

        $html = $this->actingAs($notary)
            ->get(route('notary.requests.show', ['notaryRequest' => $request->fresh(), 'tab' => 'session']))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($html, 'Case progress'), 'Case progress sidebar should not render in the page-based layout.');
        $this->assertSame(1, substr_count($html, 'Video verification'), 'Expected one Video verification block in initial HTML.');
        $this->assertSame(1, substr_count($html, 'wire:key="case-workspace-'), 'Expected one case workspace grid.');
        $this->assertSame(1, substr_count($html, 'Verification queue'), 'Expected one verification queue block.');
    }

    public function test_repeated_realtime_sync_renders_single_case_workspace(): void
    {
        [$notary, $request] = $this->seedVideoSessionCase();

        $this->actingAs($notary);

        $component = LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request->fresh()])
            ->set('activeTab', 'session');

        for ($i = 0; $i < 5; $i++) {
            $component->call('syncRealtimeRequestState');
        }

        $html = $component->html();

        $this->assertSame(0, substr_count($html, 'Case progress'), 'Case progress sidebar should not reappear after Livewire refresh.');
        $this->assertSame(1, substr_count($html, 'Video verification'), 'Livewire refresh duplicated Video verification.');
        $this->assertSame(1, substr_count($html, 'wire:key="case-workspace-'), 'Livewire refresh duplicated case workspace grid.');
        $this->assertSame(1, substr_count($html, 'Verification queue'), 'Livewire refresh duplicated verification queue.');
    }

    public function test_repeated_tracker_refresh_does_not_render_document_page_tracker(): void
    {
        [$notary, $request] = $this->seedPendingSigningCase();

        $this->actingAs($notary);

        $component = LivewireVolt::test('notary-requests.show', [
            'notaryRequest' => $request->fresh(),
            'page' => 'document',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $component->call('refreshSigningStatus');
        }

        $html = $component->html();

        $this->assertSame(0, substr_count($html, 'data-live-signing-progress'), 'Document page should not render the live signing tracker.');
        $this->assertSame(0, substr_count($html, 'data-tracker-signer-row'), 'Document page should not render tracker signer rows.');
    }

    public function test_notary_status_poll_source_avoids_dom_patch_before_livewire_refresh(): void
    {
        $source = file_get_contents(resource_path('js/notary-status-poll.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('needsLivewireRefresh', $source);
        $this->assertStringNotContainsString(
            "window.dispatchEvent(new CustomEvent('notary-status-updated'",
            $source,
            'Poll script still dispatches notary-status-updated, which can double-trigger Livewire refresh.',
        );

        $processUpdateBody = $this->extractProcessUpdateBody($source);

        $this->assertStringNotContainsString('updateWaitingVideoParties(', $processUpdateBody);
        $this->assertStringNotContainsString('updateSignerStatuses(', $processUpdateBody);
        $this->assertStringNotContainsString('updateDocumentProgress(', $processUpdateBody);
    }

    public function test_settlement_scroll_does_not_listen_for_notary_status_updated(): void
    {
        $source = file_get_contents(resource_path('views/livewire/notary-requests/show/partials/settlement-scroll.blade.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            "addEventListener('notary-status-updated'",
            $source,
            'Settlement scroll still forwards poll events to Livewire, causing duplicate refresh.',
        );
    }

    /**
     * @return array{0: User, 1: NotaryRequest}
     */
    private function seedVideoSessionCase(): array
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionScheduled,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $partyA = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Hannah Panaligan',
        ]);
        $partyB = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Chery Camz',
        ]);

        foreach ([$partyA, $partyB] as $party) {
            DocumentSigner::factory()->for($document)->create([
                'email' => $party->email,
                'name' => $party->full_name,
                'status' => DocumentSignerStatus::Signed,
                'signed_at' => now(),
            ]);
        }

        app(NotarySignerVideoInvitationService::class)
            ->inviteAllSignersWhenReady($request->fresh(['signers', 'sessions', 'notary', 'documents.documentSigners']));

        return [$notary, $request];
    }

    /**
     * @return array{0: User, 1: NotaryRequest}
     */
    private function seedPendingSigningCase(): array
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Tracker Pending Signer',
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 1,
        ]);

        return [$notary, $request];
    }

    private function extractProcessUpdateBody(string $source): string
    {
        $start = strpos($source, 'function processUpdate(data)');
        $this->assertNotFalse($start);

        $nextFunction = strpos($source, 'function hasSignerChanges', $start);
        $this->assertNotFalse($nextFunction);

        return substr($source, $start, $nextFunction - $start);
    }
}
