<?php

namespace App\Enums;

enum EngineeringVerificationRuleStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Superseded = 'superseded';
}
