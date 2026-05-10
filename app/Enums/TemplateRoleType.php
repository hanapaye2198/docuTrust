<?php

namespace App\Enums;

enum TemplateRoleType: string
{
    case Signer = 'signer';
    case Approver = 'approver';
    case Recipient = 'recipient';

    /**
     * @return list<self>
     */
    public static function activeCases(): array
    {
        return [self::Signer, self::Approver, self::Recipient];
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::activeCases(),
        );
    }

    public function isActive(): bool
    {
        return in_array($this, self::activeCases(), true);
    }
}
