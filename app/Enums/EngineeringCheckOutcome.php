<?php

namespace App\Enums;

enum EngineeringCheckOutcome: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Warning = 'warning';
    case NotEvaluated = 'not_evaluated';
}
