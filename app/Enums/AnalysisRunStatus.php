<?php
namespace App\Enums;
enum AnalysisRunStatus:string { case Prepared='prepared'; case Submitted='submitted'; case Running='running'; case Succeeded='succeeded'; case Failed='failed'; case Stale='stale'; }
