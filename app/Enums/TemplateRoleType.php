<?php

namespace App\Enums;

enum TemplateRoleType: string
{
    case Signer = 'signer';
    case Approver = 'approver';
    case Recipient = 'recipient';
}
