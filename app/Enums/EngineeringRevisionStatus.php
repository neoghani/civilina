<?php

namespace App\Enums;

enum EngineeringRevisionStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Superseded = 'superseded';
}
