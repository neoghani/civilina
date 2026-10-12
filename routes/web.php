<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

require __DIR__.'/civilina-auth.php';
require __DIR__.'/engineering-workspace.php';


// CIVILINA Block 2K - QA/QC / ITP Core
require __DIR__.'/engineering_itp.php';


// CIVILINA Block 2K.1 - QA/QC Inspection Test Records Engine
require __DIR__.'/engineering_qa_control.php';


// CIVILINA Block 2K.2 - QA/QC Normative Agent
require __DIR__.'/engineering_qa_normative.php';

require __DIR__.'/engineering_normative_v2.php';

require __DIR__.'/engineering_normative_v22.php';

require __DIR__.'/engineering_normative_library_v24.php';

require __DIR__.'/engineering_normative_librarian.php';
require __DIR__.'/engineering_normative_librarian_audit.php';
require __DIR__.'/engineering_normative_physical_library.php';
require __DIR__.'/engineering_normative_canonical_review.php';
require __DIR__.'/engineering_normative_recovery.php';
require __DIR__.'/engineering_workspace_bridge.php';
require __DIR__.'/engineering_home_bridge.php';
require __DIR__.'/civilina_hub_bridge.php';
require __DIR__.'/engineering_normative_deep_review.php';
require __DIR__.'/engineering_normative_source_review.php';
require __DIR__.'/engineering_normative_published_registry.php';
