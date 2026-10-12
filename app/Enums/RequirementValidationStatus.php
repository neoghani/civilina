<?php

namespace App\Enums;

enum RequirementValidationStatus: string
{
    case Candidate = 'candidate';
    case Validated = 'validated';
    case Rejected = 'rejected';
}
