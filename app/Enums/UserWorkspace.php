<?php

namespace App\Enums;

enum UserWorkspace: string
{
    case Signing = 'signing';
    case Enotary = 'enotary';
}
