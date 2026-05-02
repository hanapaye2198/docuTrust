<?php

namespace App\Enums;

enum SignatureFieldType: string
{
    case Signature = 'signature';
    case Text = 'text';
    case Name = 'name';
    case Date = 'date';
}
