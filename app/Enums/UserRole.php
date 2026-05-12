<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Notary = 'notary';
    case NotaryAdmin = 'notary_admin';
    case SuperAdmin = 'super_admin';
}
