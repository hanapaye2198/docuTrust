<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class SmsService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function send(string $number, string $message): void
    {
        $response = $this->http->asForm()->post('https://www.txtbox.com/api/send', [
            'api_key' => (string) config('services.txtbox.key'),
            'number' => $number,
            'message' => $message,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('TXTBOX SMS send failed.');
        }
    }
}
