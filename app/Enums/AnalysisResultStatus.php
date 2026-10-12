<?php

namespace App\Enums;

enum AnalysisResultStatus: string
{
    case Imported = 'imported';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
