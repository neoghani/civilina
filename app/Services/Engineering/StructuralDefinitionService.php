<?php
namespace App\Services\Engineering;
use App\Enums\StructuralActionType;
use App\Enums\StructuralLimitState;
use App\Models\Project;
use App\Models\StructuralBoundaryCondition;
use App\Models\StructuralLoad;
use App\Models\StructuralLoadCase;
use App\Models\StructuralLoadCombination;
use App\Models\StructuralLoadCombinationTerm;
use App\Models\TechnicalObjectRevision;
use DomainException;
use Illuminate\Support\Str;
class StructuralDefinitionService {
    public function createLoadCase(Project $project,string $code,string $name,StructuralActionType $type,float $selfWeightMultiplier=0,array $metadata=[]):StructuralLoadCase {
        return StructuralLoadCase::query()->create(['project_id'=>$project->id,'code'=>$this->code($code),'name'=>$this->text($name),'action_type'=>$type,'self_weight_multiplier'=>$selfWeightMultiplier,'metadata'=>$metadata]);
    }
    public function addLoad(Project $project,StructuralLoadCase $case,string $loadType,array $values,?TechnicalObjectRevision $target=null,string $coordinateSystem='global',?string $sourceType=null,?string $sourceReference=null,array $metadata=[]):StructuralLoad {
        if((int)$case->project_id!==(int)$project->id)throw new DomainException('Load case must belong to the same project.');
        if($target)$this->assertRevisionProject($project,$target,'Load target');
        if($values===[])throw new DomainException('Structural load values cannot be empty.');
        return StructuralLoad::query()->create(['project_id'=>$project->id,'load_case_id'=>$case->id,'technical_object_revision_id'=>$target?->id,'load_type'=>$this->text($loadType),'coordinate_system'=>$this->text($coordinateSystem),'values'=>$values,'source_type'=>$sourceType,'source_reference'=>$sourceReference,'metadata'=>$metadata]);
    }
    public function addBoundaryCondition(Project $project,TechnicalObjectRevision $target,string $conditionType,array $dof,array $stiffness=[],?string $sourceType=null,?string $sourceReference=null,array $metadata=[]):StructuralBoundaryCondition {
        $this->assertRevisionProject($project,$target,'Boundary condition target');
        if($dof===[])throw new DomainException('Boundary condition degrees of freedom cannot be empty.');
        return StructuralBoundaryCondition::query()->create(['project_id'=>$project->id,'technical_object_revision_id'=>$target->id,'condition_type'=>$this->text($conditionType),'degrees_of_freedom'=>$dof,'stiffness'=>$stiffness,'source_type'=>$sourceType,'source_reference'=>$sourceReference,'metadata'=>$metadata]);
    }
    public function createCombination(Project $project,string $code,string $name,StructuralLimitState $state,string $type='manual',array $metadata=[]):StructuralLoadCombination {
        return StructuralLoadCombination::query()->create(['project_id'=>$project->id,'code'=>$this->code($code),'name'=>$this->text($name),'limit_state'=>$state,'combination_type'=>$this->text($type),'metadata'=>$metadata]);
    }
    public function addCombinationTerm(StructuralLoadCombination $combination,StructuralLoadCase $case,float $factor,?string $role=null):StructuralLoadCombinationTerm {
        if((int)$combination->project_id!==(int)$case->project_id)throw new DomainException('Load combination and load case must belong to the same project.');
        return StructuralLoadCombinationTerm::query()->create(['load_combination_id'=>$combination->id,'load_case_id'=>$case->id,'factor'=>$factor,'role'=>$role]);
    }
    private function assertRevisionProject(Project $project,TechnicalObjectRevision $revision,string $label):void {
        $projectId=$revision->technicalObject()->value('project_id');
        if((int)$projectId!==(int)$project->id)throw new DomainException($label.' must belong to the same project.');
    }
    private function code(string $v):string{$v=Str::upper(trim($v));if($v==='')throw new DomainException('A non-empty code is required.');return $v;}
    private function text(string $v):string{$v=trim($v);if($v==='')throw new DomainException('A non-empty value is required.');return $v;}
}
