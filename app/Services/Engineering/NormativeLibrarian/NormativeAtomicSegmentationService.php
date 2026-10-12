<?php
namespace App\Services\Engineering\NormativeLibrarian;
use Illuminate\Support\Facades\DB;

class NormativeAtomicSegmentationService {
 private array $markers=['ne doit pas','ne doivent pas','doit','doivent','devra','devront','est requis','sont requis','shall not','must not','shall','must','should'];

 public function run(?int $limit=10): array {
  $q=DB::table('civilina_normative_deep_documents')->whereIn('status',['SUCCESS','PARTIAL'])->whereNotNull('reference_code')->orderBy('id');
  if($limit!==null)$q->limit(max(1,$limit));
  $docs=$q->get(); $s=['documents'=>$docs->count(),'sections'=>0,'statements'=>0,'requirements'=>0,'tolerances'=>0,'reference_values'=>0,'formula_tables'=>0,'other'=>0,'errors'=>0];
  foreach($docs as $d){try{$this->doc($d,$s);}catch(\Throwable $e){report($e);$s['errors']++;}}
  return $s;
 }

 private function doc(object $d,array &$s): void {
  $sections=DB::table('civilina_normative_deep_sections')->where('deep_document_id',$d->id)->orderBy('sequence')->get();
  foreach($sections as $sec){
   $s['sections']++; DB::table('civilina_normative_atomic_statements')->where('section_id',$sec->id)->delete();
   foreach($this->split((string)$sec->section_text) as $i=>$txt){
    $a=$this->analyse($txt);
    DB::table('civilina_normative_atomic_statements')->insert([
     'deep_document_id'=>$d->id,'document_id'=>$d->document_id,'section_id'=>$sec->id,'reference_code'=>$d->reference_code,'edition_key'=>$d->edition_key,'section_code'=>$sec->section_code,'sequence'=>$i+1,
     'statement_type'=>$a['type'],'review_status'=>'CANDIDATE','statement_text'=>$txt,'obligation_marker'=>$a['marker'],'has_explicit_limit'=>$a['limit'],'has_numeric_unit'=>$a['numeric'],'looks_like_formula'=>$a['formula'],
     'numeric_entities'=>json_encode($a['entities'],JSON_UNESCAPED_UNICODE),'segmentation_confidence'=>$a['confidence'],'statement_hash'=>hash('sha256',$txt),'source_hash'=>hash('sha256',(string)$sec->section_text),
     'generated_at'=>now(),'created_at'=>now(),'updated_at'=>now()
    ]);
    $s['statements']++; $map=['REQUIREMENT'=>'requirements','TOLERANCE'=>'tolerances','REFERENCE_VALUE'=>'reference_values','FORMULA_TABLE'=>'formula_tables','OTHER'=>'other']; $s[$map[$a['type']]]++;
   }
  }
 }

 private function split(string $text): array {
  $text=str_replace(["\r\n","\r"],"\n",$text); $text=preg_replace('/[ \t]+/u',' ',$text);
  $text=preg_replace('/;\s+(?=[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ0-9])/u',";\n§§",$text);
  $text=preg_replace('/(?<=[.!?])\s+(?=[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ0-9])/u',"\n§§",$text);
  $parts=preg_split('/(?:\n§§|\n{2,})/u',$text)?:[]; $out=[];
  foreach($parts as $p){$p=trim(preg_replace('/\s+/u',' ',$p)); if(mb_strlen($p)>=8)$out[]=$p;}
  return array_values(array_unique($out));
 }

 private function analyse(string $t): array {
  $low=mb_strtolower($t); $marker=null; foreach($this->markers as $m){if(str_contains($low,mb_strtolower($m))){$marker=$m;break;}}
  $limit=preg_match('/(?:±|≤|≥|<|>|\bminimum\b|\bmaximum\b|\bmin\.?\b|\bmax\.?\b|\bau moins\b|\bau plus\b|ne doit pas dépasser|ne doivent pas dépasser|inférieur(?:e)?|supérieur(?:e)?|tolérance|écart admissible|not exceed|at least|at most)/ui',$t)===1;
  $pat='/(?P<op>±|≤|≥|<|>|max(?:imum)?|min(?:imum)?|au moins|au plus)?\s*(?P<value>\d+(?:[.,]\d+)?)\s*(?P<unit>mm|cm|m|m2|m²|m3|m³|mpa|n\/mm2|n\/mm²|kn|%|°c|kg|kg\/m3|kg\/m³|h|jour|jours)\b/ui';
  preg_match_all($pat,$t,$mm,PREG_SET_ORDER); $entities=[]; foreach($mm as $x)$entities[]=['operator'=>trim($x['op']??'')?:null,'value'=>str_replace(',','.',$x['value']),'unit'=>$x['unit']];
  $numeric=count($entities)>0; $formula=preg_match('/\b[A-Za-z]{1,4}\s*=\s*[^.;]{2,}/u',$t)===1 || (preg_match_all('/\d+(?:[.,]\d+)?/u',$t,$n)>=4 && preg_match('/[=<>×*\/]/u',$t));
  if($formula&&!$marker&&!$limit)$type='FORMULA_TABLE'; elseif($numeric&&$limit)$type='TOLERANCE'; elseif($marker)$type='REQUIREMENT'; elseif($numeric)$type='REFERENCE_VALUE'; elseif($formula)$type='FORMULA_TABLE'; else $type='OTHER';
  $conf=50+($marker?20:0)+($limit?15:0)+($numeric?10:0);
  return ['type'=>$type,'marker'=>$marker,'limit'=>$limit,'numeric'=>$numeric,'formula'=>$formula,'entities'=>$entities,'confidence'=>min(100,$conf)];
 }
}
