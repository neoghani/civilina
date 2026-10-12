<?php
namespace Tests\Feature;
use App\Enums\AnalysisRunStatus;
use App\Enums\StructuralActionType;
use App\Enums\StructuralLimitState;
use App\Models\User;
use App\Services\Engineering\AnalysisModelService;
use App\Services\Engineering\AnalysisRunService;
use App\Services\Engineering\DesignBasisService;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\RobotStructuralAnalysisAdapter;
use App\Services\Engineering\StructuralDefinitionService;
use App\Services\Engineering\TechnicalModelService;
use App\Services\Organizations\OrganizationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class StructuralModelRobotContractTest extends TestCase {
 use RefreshDatabase;
 private function ctx():array{
  $u=User::factory()->create();$org=app(OrganizationService::class)->createForOwner($u,'Civilina');
  $p=app(EngineeringProjectService::class)->createProject($org,'P-001','Project');
  $dbs=app(DesignBasisService::class);$db=$dbs->createRevision($p,'DB');$db=$dbs->markForReview($db);$db=$dbs->approve($db);
  return ['org'=>$org,'project'=>$p,'db'=>$db,'tech'=>app(TechnicalModelService::class),'struct'=>app(StructuralDefinitionService::class),'models'=>app(AnalysisModelService::class)];
 }
 private function approvedRevision(array $c,string $code='COL-001'){
  $m=$c['tech']->createMaterial($c['project'],'MAT-'.$code,'Concrete','concrete','EN 206',['fck_mpa'=>30]);
  $o=$c['tech']->createObject($c['project'],$code,'Column','column','structure');
  $r=$c['tech']->createRevision($o,['shape'=>'rectangle','width_m'=>0.4,'height_m'=>0.4,'length_m'=>4.2],[],['k'=>1.0],$m);
  $r=$c['tech']->markForReview($r);return $c['tech']->approve($r);
 }
 public function test_load_case_is_project_scoped():void{$c=$this->ctx();$lc=$c['struct']->createLoadCase($c['project'],'G','Permanent',StructuralActionType::Permanent,1);$this->assertSame($c['project']->id,$lc->project_id);}
 public function test_load_rejects_cross_project_target():void{$c=$this->ctx();$p2=app(EngineeringProjectService::class)->createProject($c['org'],'P-002','Other');$m=$c['tech']->createMaterial($p2,'M2','Concrete','concrete');$o=$c['tech']->createObject($p2,'O2','Other','column','structure');$r=$c['tech']->createRevision($o,['shape'=>'rectangle','width_m'=>.4,'height_m'=>.4],[],[],$m);$r=$c['tech']->markForReview($r);$r=$c['tech']->approve($r);$lc=$c['struct']->createLoadCase($c['project'],'G','Permanent',StructuralActionType::Permanent);$this->expectException(DomainException::class);$c['struct']->addLoad($c['project'],$lc,'force',['fz_kn'=>-100],$r);}
 public function test_combination_cannot_mix_projects():void{$c=$this->ctx();$p2=app(EngineeringProjectService::class)->createProject($c['org'],'P-002','Other');$lc=$c['struct']->createLoadCase($p2,'Q','Variable',StructuralActionType::Variable);$co=$c['struct']->createCombination($c['project'],'ULS-01','ULS',StructuralLimitState::Uls);$this->expectException(DomainException::class);$c['struct']->addCombinationTerm($co,$lc,1.5);}
 public function test_analysis_model_rejects_draft_technical_revision():void{$c=$this->ctx();$o=$c['tech']->createObject($c['project'],'COL-001','Column','column','structure');$r=$c['tech']->createRevision($o,['shape'=>'rectangle','width_m'=>.4,'height_m'=>.4]);$m=$c['models']->createModel($c['project'],$c['db'],'RSA-01','Robot');$this->expectException(DomainException::class);$c['models']->addTechnicalRevision($m,$r);}
 public function test_robot_payload_contains_model_loads_combinations_and_boundary_conditions():void{
  $c=$this->ctx();$r=$this->approvedRevision($c);$lc=$c['struct']->createLoadCase($c['project'],'G','Permanent',StructuralActionType::Permanent,1);
  $c['struct']->addLoad($c['project'],$lc,'nodal_force',['fz_kn'=>-350],$r,'global','engineer_input','LOAD-001');
  $co=$c['struct']->createCombination($c['project'],'ULS-01','ULS',StructuralLimitState::Uls);$c['struct']->addCombinationTerm($co,$lc,1.35,'leading');
  $c['struct']->addBoundaryCondition($c['project'],$r,'fixed',['ux'=>true,'uy'=>true,'uz'=>true,'rx'=>true,'ry'=>true,'rz'=>true]);
  $m=$c['models']->createModel($c['project'],$c['db'],'RSA-01','Robot');$c['models']->addTechnicalRevision($m,$r,'COL-001');
  $p=app(RobotStructuralAnalysisAdapter::class)->buildInputPayload($m);
  $this->assertSame('civilina.robot.v1',$p['contract_version']);$this->assertSame('COL-001',$p['technical_model'][0]['technical_object_code']);$this->assertSame(-350,$p['load_cases'][0]['loads'][0]['values']['fz_kn']);$this->assertSame(1.35,$p['load_combinations'][0]['terms'][0]['factor']);$this->assertSame('fixed',$p['boundary_conditions'][0]['condition_type']);
 }
 public function test_analysis_run_requires_approved_analysis_model():void{$c=$this->ctx();$r=$this->approvedRevision($c);$m=$c['models']->createModel($c['project'],$c['db'],'RSA-01','Robot');$c['models']->addTechnicalRevision($m,$r);$this->expectException(DomainException::class);app(AnalysisRunService::class)->prepareRun($m,app(RobotStructuralAnalysisAdapter::class));}
 public function test_approved_analysis_model_prepares_hashed_robot_run():void{$c=$this->ctx();$r=$this->approvedRevision($c);$m=$c['models']->createModel($c['project'],$c['db'],'RSA-01','Robot');$c['models']->addTechnicalRevision($m,$r);$m=$c['models']->markForReview($m);$m=$c['models']->approve($m);$run=app(AnalysisRunService::class)->prepareRun($m,app(RobotStructuralAnalysisAdapter::class));$this->assertSame(AnalysisRunStatus::Prepared,$run->status);$this->assertSame(64,strlen($run->input_hash));$this->assertSame('robot_structural_analysis',$run->solver_key);}
}
