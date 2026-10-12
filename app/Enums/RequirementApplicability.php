<?php

namespace App\Enums;

enum RequirementApplicability: string
{
    case Pending = 'pending';
    case Applicable = 'applicable';
    case NotApplicable = 'not_applicable';
}
