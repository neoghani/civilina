<?php
namespace App\Services\Engineering;
use App\Contracts\Engineering\StructuralAnalysisAdapter;
use App\Models\AnalysisModel;
use App\Models\StructuralBoundaryCondition;
use App\Models\StructuralLoadCase;
use App\Models\StructuralLoadCombination;
class RobotStructuralAnalysisAdapter implements StructuralAnalysisAdapter {
    public function solverKey():string{return 'robot_structural_analysis';}
    public function buildInputPayload(AnalysisModel $model):array {
        $pid=$model->project_id;
        $items=$model->items()->with(['technicalObjectRevision.technicalObject','technicalObjectRevision.material'])->orderBy('id')->get()->map(function($item){
            $r=$item->technicalObjectRevision;$o=$r->technicalObject;
            return ['external_key'=>$item->external_key,'technical_object_code'=>$o->code,'technical_object_type'=>$o->object_type,'discipline'=>$o->discipline,'revision_no'=>$r->revision_no,'material'=>$r->material?['code'=>$r->material->code,'name'=>$r->material->name,'type'=>$r->material->material_type,'standard_reference'=>$r->material->standard_reference,'properties'=>$r->material->properties]:null,'geometry'=>$r->geometry,'properties'=>$r->properties,'assumptions'=>$r->assumptions,'source_snapshot_hash'=>$item->source_snapshot_hash];
        })->values()->all();
        $cases=StructuralLoadCase::query()->where('project_id',$pid)->with('loads.technicalObjectRevision.technicalObject')->orderBy('code')->get()->map(function($c){
            return ['code'=>$c->code,'name'=>$c->name,'action_type'=>$c->action_type->value,'self_weight_multiplier'=>(float)$c->self_weight_multiplier,'loads'=>$c->loads->sortBy('id')->map(fn($l)=>['load_type'=>$l->load_type,'target_object_code'=>$l->technicalObjectRevision?->technicalObject?->code,'target_revision_no'=>$l->technicalObjectRevision?->revision_no,'coordinate_system'=>$l->coordinate_system,'values'=>$l->values,'source_type'=>$l->source_type,'source_reference'=>$l->source_reference])->values()->all()];
        })->values()->all();
        $combinations=StructuralLoadCombination::query()->where('project_id',$pid)->with('terms.loadCase')->orderBy('code')->get()->map(fn($c)=>['code'=>$c->code,'name'=>$c->name,'limit_state'=>$c->limit_state->value,'combination_type'=>$c->combination_type,'terms'=>$c->terms->sortBy('id')->map(fn($t)=>['load_case_code'=>$t->loadCase->code,'factor'=>(float)$t->factor,'role'=>$t->role])->values()->all()])->values()->all();
        $boundaries=StructuralBoundaryCondition::query()->where('project_id',$pid)->with('technicalObjectRevision.technicalObject')->orderBy('id')->get()->map(fn($b)=>['target_object_code'=>$b->technicalObjectRevision->technicalObject->code,'target_revision_no'=>$b->technicalObjectRevision->revision_no,'condition_type'=>$b->condition_type,'degrees_of_freedom'=>$b->degrees_of_freedom,'stiffness'=>$b->stiffness,'source_type'=>$b->source_type,'source_reference'=>$b->source_reference])->values()->all();
        return ['contract_version'=>'civilina.robot.v1','solver'=>$this->solverKey(),'analysis_model'=>['id'=>$model->id,'code'=>$model->code,'name'=>$model->name,'design_basis_revision_id'=>$model->design_basis_revision_id,'project_standard_profile_id'=>$model->project_standard_profile_id],'technical_model'=>$items,'load_cases'=>$cases,'load_combinations'=>$combinations,'boundary_conditions'=>$boundaries];
    }
}
