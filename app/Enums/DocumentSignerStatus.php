<?php

namespace App\Enums;

enum DocumentSignerStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
}
