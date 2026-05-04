<?php

namespace App\Enums;

enum SignatureFieldType: string
{
    case Signature = 'signature';
    case SignatureLeft = 'signature_left';
    case SignatureRight = 'signature_right';
    case Text = 'text';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Name = 'name';
    case Date = 'date';
    case Email = 'email';
    case Initials = 'initials';
}
