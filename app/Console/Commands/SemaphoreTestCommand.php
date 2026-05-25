<?php

namespace App\Console\Commands;

use App\Rules\PhilippineMobileNumber;
use App\Services\SemaphoreService;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SemaphoreTestCommand extends Command
{
    protected $signature = 'semaphore:test
        {number : Philippine mobile number (e.g. 09171234567)}
        {--code= : OTP code to send (default: random 6-digit)}
        {--force : Skip production confirmation}';

    protected $description = 'Send a test OTP SMS via Semaphore to verify API configuration';

    public function handle(SemaphoreService $semaphore, SmsService $smsService): int
    {
        if ((string) config('services.semaphore.api_key', '') === '') {
            $this->error('SEMAPHORE_API_KEY is not set in .env');

            return self::FAILURE;
        }

        $number = (string) $this->argument('number');
        $validator = Validator::make(
            ['number' => $number],
            ['number' => ['required', 'string', new PhilippineMobileNumber]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('Send a real OTP SMS in production?', false)) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $code = (string) ($this->option('code') ?: $this->generateCode());
        $sender = (string) config('services.semaphore.sender_name', 'DocuTrust');

        $this->line('Sending test OTP via Semaphore…');
        $this->line('  Recipient: '.$this->maskNumber($number));
        $this->line('  Sender: '.$sender);
        $this->line('  Code: '.$code.' (for verification on your device only)');

        try {
            $result = $semaphore->sendOtp(
                $number,
                $smsService->formatOtpMessage(),
                $code,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $result['success']) {
            $this->error('Semaphore request failed.');
            $this->line(json_encode($result['raw'], JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $this->info('OTP sent successfully.');
        $this->line('  Message ID: '.($result['message_id'] ?? 'n/a'));

        if ($result['raw'] !== []) {
            $status = $result['raw']['status'] ?? null;
            $network = $result['raw']['network'] ?? null;
            if ($status !== null) {
                $this->line('  Status: '.$status);
            }
            if ($network !== null) {
                $this->line('  Network: '.$network);
            }
        }

        $this->newLine();
        $this->comment('Check the handset for: '.$smsService->formatOtpMessage());
        $this->comment('(Semaphore replaces {otp} with: '.$code.')');

        return self::SUCCESS;
    }

    private function generateCode(): string
    {
        $length = max((int) config('otp.length', 6), 4);
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    private function maskNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? $number;

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }
}
