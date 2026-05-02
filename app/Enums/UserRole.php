<?php

namespace App\Enums;

enum UserRole: string
{
    case Signer = 'signer';
    case Notary = 'notary';
    case Admin = 'admin';
}
