<?php

namespace App\Console\Commands;

use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Services\NotaryNotificationService;
use App\Services\NotaryPaymentService;
use App\Services\NotaryPublicPaymentLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestNotaryPaymentFlow extends Command
{
    protected $signature = 'notary:test-payment-flow
        {action : prepare or verify}
        {--request= : Existing notary request ID}
        {--gateway= : Gateway code to use in prepare mode}
        {--recipient= : Recipient email for the payment-ready email in prepare mode}
        {--email : Queue the payment-ready email in prepare mode}
        {--refresh : Refresh the latest payment from GatewayHub in verify mode}
        {--webhook= : Path to a JSON webhook payload file to apply in verify mode}
        {--force : Allow running in production}';

    protected $description = 'Exercise the live e-notary payment flow for an existing request and print a VPS-friendly status summary';

    public function handle(
        NotaryPaymentService $paymentService,
        NotaryNotificationService $notificationService,
        NotaryPublicPaymentLinkService $publicPaymentLinkService,
    ): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $action = strtolower(trim((string) $this->argument('action')));
        if (! in_array($action, ['prepare', 'verify'], true)) {
            $this->error('Unsupported action. Use "prepare" or "verify".');

            return self::FAILURE;
        }

        $requestId = (int) $this->option('request');
        if ($requestId <= 0) {
            $this->error('Provide a valid --request=<id> option.');

            return self::FAILURE;
        }

        $notaryRequest = NotaryRequest::query()
            ->with([
                'requester',
                'notary',
                'payments',
                'registerEntries',
                'attorneyNotarialRegistry',
                'eInvoices',
            ])
            ->find($requestId);

        if (! $notaryRequest instanceof NotaryRequest) {
            $this->error(sprintf('Notary request %d was not found.', $requestId));

            return self::FAILURE;
        }

        return match ($action) {
            'prepare' => $this->handlePrepare(
                $notaryRequest,
                $paymentService,
                $notificationService,
                $publicPaymentLinkService,
            ),
            'verify' => $this->handleVerify($notaryRequest, $paymentService),
        };
    }

    private function handlePrepare(
        NotaryRequest $notaryRequest,
        NotaryPaymentService $paymentService,
        NotaryNotificationService $notificationService,
        NotaryPublicPaymentLinkService $publicPaymentLinkService,
    ): int {
        $gateway = trim((string) $this->option('gateway'));
        if ($gateway === '') {
            $this->error('Provide --gateway=<code> in prepare mode, for example --gateway=coins.');

            return self::FAILURE;
        }

        $latestBefore = $this->latestPayment($notaryRequest);
        $payment = $paymentService->createGatewayPayment(
            $notaryRequest->fresh(['requester', 'notary', 'payments', 'registerEntries', 'attorneyNotarialRegistry', 'eInvoices']),
            $gateway,
            null,
        );

        if ($this->option('email')) {
            $notificationService->notifyPaymentReady(
                $notaryRequest->fresh(['requester', 'notary']),
                $payment,
                $this->resolvedRecipientEmail(),
            );
        }

        $publicPaymentUrl = $publicPaymentLinkService->paymentPageUrl($notaryRequest);
        $queueSnapshot = $this->notificationQueueSnapshot();
        $this->info('Prepared notary payment flow.');

        $this->writeSummary([
            'request_id' => (string) $notaryRequest->id,
            'request_title' => (string) $notaryRequest->title,
            'request_status' => $notaryRequest->status->value,
            'requester_email' => (string) ($notaryRequest->requester?->email ?? ''),
            'recipient_email' => (string) ($this->resolvedRecipientEmail() ?? ($notaryRequest->requester?->email ?? '')),
            'notary_email' => (string) ($notaryRequest->notary?->email ?? ''),
            'fee_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'public_payment_url' => $publicPaymentUrl,
            'payment_id' => (string) $payment->id,
            'payment_reference' => (string) $payment->reference,
            'payment_gateway' => (string) $payment->gateway,
            'payment_status' => $payment->status->value,
            'payment_reused' => $latestBefore instanceof Payment && $latestBefore->is($payment) ? 'yes' : 'no',
            'provider_payment_id' => (string) ($payment->provider_payment_id ?? ''),
            'checkout_url' => (string) ($payment->checkout_url ?? ''),
            'redirect_url' => (string) ($payment->redirect_url ?? ''),
            'qr_available' => $this->hasQrPayload($payment) ? 'yes' : 'no',
            'qr_payload_length' => (string) strlen((string) ($payment->qr_data ?? '')),
            'expires_at' => (string) optional($payment->expires_at)->toIso8601String(),
            'payment_ready_email' => $this->option('email') ? 'queued' : 'skipped',
            'queue_connection' => (string) config('queue.default'),
            'notifications_queue' => (string) config('docutrust.queues.notifications'),
            'pending_notification_jobs' => $queueSnapshot,
        ]);

        return self::SUCCESS;
    }

    private function handleVerify(
        NotaryRequest $notaryRequest,
        NotaryPaymentService $paymentService,
    ): int {
        $webhookPath = trim((string) $this->option('webhook'));
        $shouldRefresh = (bool) $this->option('refresh');

        if ($webhookPath !== '' && $shouldRefresh) {
            $this->error('Use either --refresh or --webhook=<path> in verify mode, not both.');

            return self::FAILURE;
        }

        $payment = $this->latestPayment($notaryRequest);
        if (! $payment instanceof Payment) {
            $this->error(sprintf('Notary request %d has no payment to verify.', $notaryRequest->id));

            return self::FAILURE;
        }

        if ($webhookPath !== '') {
            $payload = $this->readWebhookPayload($webhookPath);
            if ($payload === null) {
                return self::FAILURE;
            }

            $updatedPayment = $paymentService->handleGatewayWebhook($payload);
            if (! $updatedPayment instanceof Payment) {
                $this->error('Webhook payload did not match any payment.');

                return self::FAILURE;
            }

            $payment = $updatedPayment;
        } elseif ($shouldRefresh) {
            $payment = $paymentService->refreshGatewayPayment($payment);
        } else {
            $payment = $payment->fresh();
        }

        $invoice = $payment->eInvoice()->latest('id')->first();

        $this->info('Verified notary payment flow.');
        $this->writeSummary([
            'request_id' => (string) $notaryRequest->id,
            'payment_id' => (string) $payment->id,
            'payment_reference' => (string) $payment->reference,
            'payment_gateway' => (string) $payment->gateway,
            'payment_status' => $payment->status->value,
            'provider_payment_id' => (string) ($payment->provider_payment_id ?? ''),
            'provider_reference' => (string) ($payment->provider_reference ?? ''),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => (string) $payment->currency,
            'checkout_url' => (string) ($payment->checkout_url ?? ''),
            'redirect_url' => (string) ($payment->redirect_url ?? ''),
            'qr_available' => $this->hasQrPayload($payment) ? 'yes' : 'no',
            'qr_payload_length' => (string) strlen((string) ($payment->qr_data ?? '')),
            'expires_at' => (string) optional($payment->expires_at)->toIso8601String(),
            'paid_at' => (string) optional($payment->paid_at)->toIso8601String(),
            'last_verified_at' => (string) optional($payment->last_verified_at)->toIso8601String(),
            'einvoice_id' => $invoice !== null ? (string) $invoice->id : '',
            'einvoice_status' => $invoice !== null ? (string) $invoice->status->value : '',
        ]);

        return self::SUCCESS;
    }

    private function latestPayment(NotaryRequest $notaryRequest): ?Payment
    {
        return Payment::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->latest('id')
            ->first();
    }

    private function notificationQueueSnapshot(): string
    {
        $connection = (string) config('queue.default');
        $queue = (string) config('docutrust.queues.notifications');

        if ($connection !== 'database') {
            return 'n/a';
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        return (string) DB::table($table)
            ->where('queue', $queue)
            ->count();
    }

    private function resolvedRecipientEmail(): ?string
    {
        $recipientEmail = trim((string) $this->option('recipient'));

        return $recipientEmail === '' ? null : $recipientEmail;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readWebhookPayload(string $path): ?array
    {
        if (! is_file($path)) {
            $this->error(sprintf('Webhook payload file not found: %s', $path));

            return null;
        }

        $contents = file_get_contents($path);
        if (! is_string($contents) || trim($contents) === '') {
            $this->error('Webhook payload file is empty.');

            return null;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->error('Webhook payload file is not valid JSON: '.$exception->getMessage());

            return null;
        }

        if (! is_array($payload)) {
            $this->error('Webhook payload JSON must decode to an object.');

            return null;
        }

        return $payload;
    }

    private function hasQrPayload(Payment $payment): bool
    {
        return trim((string) ($payment->qr_data ?? '')) !== '';
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function writeSummary(array $rows): void
    {
        foreach ($rows as $key => $value) {
            $this->line(sprintf('%s: %s', $key, $value));
        }
    }
}
