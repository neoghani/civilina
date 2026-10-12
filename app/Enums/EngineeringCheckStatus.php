<?php

namespace App\Enums;

enum EngineeringCheckStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
