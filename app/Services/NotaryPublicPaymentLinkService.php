<?php

namespace App\Services;

use App\Models\NotaryRequest;
use Illuminate\Support\Facades\URL;

class NotaryPublicPaymentLinkService
{
    public function paymentPageUrl(NotaryRequest $request): string
    {
        return URL::temporarySignedRoute(
            'public.notary.payment.show',
            now()->addDays(7),
            ['notaryRequest' => $request->id],
        );
    }
}
