<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasTable('civilina_normative_atomic_statements')){
   Schema::create('civilina_normative_atomic_statements',function(Blueprint $t){
    $t->id(); $t->unsignedBigInteger('deep_document_id')->index(); $t->unsignedBigInteger('document_id')->index(); $t->unsignedBigInteger('section_id')->index();
    $t->string('reference_code')->nullable()->index(); $t->string('edition_key')->nullable()->index(); $t->string('section_code')->nullable()->index(); $t->unsignedInteger('sequence')->default(0);
    $t->string('statement_type')->index(); $t->string('review_status')->default('CANDIDATE')->index(); $t->longText('statement_text');
    $t->string('obligation_marker')->nullable(); $t->boolean('has_explicit_limit')->default(false); $t->boolean('has_numeric_unit')->default(false); $t->boolean('looks_like_formula')->default(false);
    $t->json('numeric_entities')->nullable(); $t->decimal('segmentation_confidence',5,2)->default(0); $t->string('statement_hash',64)->index(); $t->string('source_hash',64)->index();
    $t->timestamp('generated_at')->nullable(); $t->timestamps(); $t->unique(['section_id','statement_hash'],'civ_atomic_unique');
   });
  }
 }
 public function down(): void {}
};
