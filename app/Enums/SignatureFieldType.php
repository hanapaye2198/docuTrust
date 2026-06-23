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
    case Seal = 'seal';
    case Date = 'date';
    case Email = 'email';
    case Initials = 'initials';

    /**
     * @return list<string>
     */
    public static function placeableValues(): array
    {
        return [
            self::Signature->value,
            self::SignatureLeft->value,
            self::SignatureRight->value,
            self::Text->value,
            self::Name->value,
            self::Seal->value,
            self::Date->value,
            self::Email->value,
            self::Initials->value,
        ];
    }
}
