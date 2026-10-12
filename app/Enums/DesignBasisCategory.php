<?php

namespace App\Enums;

enum DesignBasisCategory: string
{
    case ClientRequirement = 'client_requirement';
    case Location = 'location';
    case Climate = 'climate';
    case Seismic = 'seismic';
    case Geotechnical = 'geotechnical';
    case CodeStandard = 'code_standard';
    case Constraint = 'constraint';
    case Assumption = 'assumption';
}
