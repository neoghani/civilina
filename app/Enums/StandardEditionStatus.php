<?php

namespace App\Enums;

enum StandardEditionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';
}
