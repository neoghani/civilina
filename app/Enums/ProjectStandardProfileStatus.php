<?php

namespace App\Enums;

enum ProjectStandardProfileStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
}
