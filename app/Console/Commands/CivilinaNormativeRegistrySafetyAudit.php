<?php
namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeCandidateQualityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CivilinaNormativeRegistrySafetyAudit extends Command
{
    protected $signature='civilina:normative-registry:safety-audit {--apply}';
    protected $description='Audit des publications actives.';

    public function handle(NormativeCandidateQualityService $q): int
    {
        $apply=(bool)$this->option('apply');
        $rows=DB::table('civilina_normative_registry_requirements')->where('publication_state','ACTIVE')->get();
        $seen=$safe=$unsafe=$revoked=0;

        foreach($rows as $r){
            $seen++;
            $x=$q->classify((string)$r->requirement_text);
            $ok=$q->editionIsKnown($r->edition_key) && $x['publishable'];

            if($ok){$safe++;continue;}
            $unsafe++;
            $reason='QUALITY-GATE 2K.5.1.1: édition inconnue ou sémantique non publiable';
            $this->warn('#'.$r->id.' '.$r->reference_code.' '.$r->edition_key.' -> '.$reason);

            if($apply){
                DB::transaction(function() use($r,$reason){
                    DB::table('civilina_normative_registry_requirements')->where('id',$r->id)->update([
                        'publication_state'=>'REVOKED','revoked_at'=>now(),'revocation_reason'=>$reason,'updated_at'=>now()
                    ]);
                    DB::table('civilina_normative_publication_events')->insert([
                        'registry_requirement_id'=>$r->id,'source_candidate_id'=>$r->source_candidate_id,
                        'publication_run_id'=>null,'event_type'=>'REVOKE','source_hash'=>$r->source_hash,
                        'provenance_hash'=>$r->provenance_hash,'reason'=>$reason,'actor'=>'QUALITY_GATE_2K511',
                        'event_at'=>now(),'created_at'=>now(),'updated_at'=>now()
                    ]);
                    DB::table('civilina_normative_deep_requirement_candidates')->where('id',$r->source_candidate_id)->update([
                        'status'=>'CANDIDATE','validation_note'=>$reason,'validated_at'=>null,'updated_at'=>now()
                    ]);
                });
                $revoked++;
            }
        }

        foreach(['seen'=>$seen,'safe'=>$safe,'unsafe'=>$unsafe,'revoked'=>$revoked] as $k=>$v) $this->line(str_pad($k,10).' : '.$v);
        return self::SUCCESS;
    }
}
