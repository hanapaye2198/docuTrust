<?php

namespace App\Enums;

enum SigningMethod: string
{
    case EmailLink = 'email_link';
    case AccountVerified = 'account_verified';
    case PkiCertificate = 'pki_certificate';
}
