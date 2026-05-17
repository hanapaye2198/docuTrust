<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class StoreNotaryClientCaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $user = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'requestType' => ['required', 'string', Rule::in(config('docutrust.notary.notarial_act_types', ['acknowledgment']))],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'caseDocument' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:15360'],
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.full_name' => ['required', 'string', 'max:255'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.phone' => ['nullable', 'string', 'max:64'],
            'signers.*.address' => ['nullable', 'string', 'max:500'],
            'signers.*.role' => ['nullable', 'string', 'max:64'],
            'notaryUserId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('role', 'notary');
                }),
            ],
        ];
    }
}
