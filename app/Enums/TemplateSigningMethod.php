<?php

namespace App\Enums;

enum TemplateSigningMethod: string
{
    case DocuTrustSign = 'docutrust_sign';
    case Email = 'email';
}
