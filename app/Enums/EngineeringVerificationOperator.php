<?php

namespace App\Enums;

enum EngineeringVerificationOperator: string
{
    case MaxLessOrEqual = 'max_lte';
    case MaxAbsLessOrEqual = 'max_abs_lte';
    case MinGreaterOrEqual = 'min_gte';
}
