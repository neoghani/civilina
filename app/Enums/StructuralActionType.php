<?php
namespace App\Enums;
enum StructuralActionType:string { case Permanent='permanent'; case Variable='variable'; case Wind='wind'; case Seismic='seismic'; case Thermal='thermal'; case Accidental='accidental'; case Other='other'; }
