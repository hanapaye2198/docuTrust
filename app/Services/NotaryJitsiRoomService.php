<?php

namespace App\Services;

use App\Models\NotaryRequest;
use Illuminate\Support\Str;

final class NotaryJitsiRoomService
{
    public function buildRoomName(NotaryRequest $request): string
    {
        return 'docutrust-'.$request->id.'-'.strtolower(Str::random(10));
    }

    public function meetingUrl(string $roomName): string
    {
        $base = rtrim((string) config('docutrust.notary.jitsi_base_url', 'https://meet.jit.si'), '/');

        return $base.'/'.$roomName;
    }
}
