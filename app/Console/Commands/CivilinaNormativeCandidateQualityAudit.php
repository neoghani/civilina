<?php
namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeCandidateQualityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CivilinaNormativeCandidateQualityAudit extends Command
{
    protected $signature='civilina:normative-deep:quality-audit {--apply}';
    protected $description='Audit sémantique des candidats normatifs.';

    public function handle(NormativeCandidateQualityService $q): int
    {
        $apply=(bool)$this->option('apply');
        $rows=DB::table('civilina_normative_deep_requirement_candidates as c')
            ->leftJoin('civilina_normative_deep_documents as d','d.id','=','c.deep_document_id')
            ->select('c.id','c.status','c.candidate_type','c.text','d.edition_key')->get();

        $s=['seen'=>0,'requirement'=>0,'tolerance'=>0,'reference_value'=>0,'other'=>0,'type_changes'=>0,'unknown_edition'=>0,'validated_to_review'=>0];

        foreach($rows as $r){
            $s['seen']++;
            $x=$q->classify((string)$r->text);
            $key=strtolower($x['type']);
            if(isset($s[$key])) $s[$key]++;
            if($r->candidate_type!==$x['type']) $s['type_changes']++;
            $known=$q->editionIsKnown($r->edition_key);
            if(!$known) $s['unknown_edition']++;

            $reset=$r->status==='VALIDATED' && (!$x['publishable'] || !$known);
            if($reset) $s['validated_to_review']++;

            if($apply){
                $u=['candidate_type'=>$x['type'],'marker'=>$x['marker'],'updated_at'=>now()];
                if($reset){
                    $u['status']='CANDIDATE';
                    $u['validation_note']='QUALITY-GATE: remise en revue (édition inconnue ou sémantique non publiable).';
                    $u['validated_at']=null;
                }
                DB::table('civilina_normative_deep_requirement_candidates')->where('id',$r->id)->update($u);
            }
        }

        $this->info($apply?'AUDIT APPLIQUE':'AUDIT DRY-RUN');
        foreach($s as $k=>$v) $this->line(str_pad($k,22).' : '.$v);
        return self::SUCCESS;
    }
}
