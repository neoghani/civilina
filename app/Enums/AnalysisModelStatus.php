<?php
namespace App\Enums;
enum AnalysisModelStatus:string { case Draft='draft'; case Review='review'; case Approved='approved'; case Superseded='superseded'; }
