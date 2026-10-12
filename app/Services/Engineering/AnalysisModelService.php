<?php
namespace App\Services\Engineering;
use App\Enums\AnalysisModelStatus;
use App\Enums\EngineeringRevisionStatus;
use App\Enums\ProjectStandardProfileStatus;
use App\Models\AnalysisModel;
use App\Models\AnalysisModelItem;
use App\Models\DesignBasisRevision;
use App\Models\Project;
use App\Models\ProjectStandardProfile;
use App\Models\TechnicalObjectRevision;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;
class AnalysisModelService {
    public function createModel(Project $project,DesignBasisRevision $db,string $code,string $name,?ProjectStandardProfile $profile=null,?User $createdBy=null,string $solver='robot_structural_analysis',array $metadata=[]):AnalysisModel {
        if((int)$db->project_id!==(int)$project->id)throw new DomainException('Design Basis revision must belong to the analysis project.');
        if($db->status!==EngineeringRevisionStatus::Approved)throw new DomainException('Analysis model requires an approved Design Basis revision.');
        if($profile){
            if((int)$profile->project_id!==(int)$project->id)throw new DomainException('Standard profile must belong to the analysis project.');
            if($profile->status!==ProjectStandardProfileStatus::Approved)throw new DomainException('Analysis model requires an approved project standard profile.');
        }
        return AnalysisModel::query()->create(['project_id'=>$project->id,'design_basis_revision_id'=>$db->id,'project_standard_profile_id'=>$profile?->id,'code'=>$this->code($code),'name'=>$this->text($name),'solver_key'=>$solver,'status'=>AnalysisModelStatus::Draft,'metadata'=>$metadata,'created_by_user_id'=>$createdBy?->id]);
    }
    public function addTechnicalRevision(AnalysisModel $model,TechnicalObjectRevision $revision,?string $externalKey=null,array $metadata=[]):AnalysisModelItem {
        if($model->status!==AnalysisModelStatus::Draft)throw new DomainException('Only a draft analysis model can be edited.');
        $projectId=$revision->technicalObject()->value('project_id');
        if((int)$projectId!==(int)$model->project_id)throw new DomainException('Technical revision must belong to the analysis project.');
        if($revision->status!==EngineeringRevisionStatus::Approved)throw new DomainException('Only an approved technical revision can enter an analysis model.');
        $objectCode=$revision->technicalObject()->value('code');
        return AnalysisModelItem::query()->create(['analysis_model_id'=>$model->id,'technical_object_revision_id'=>$revision->id,'source_snapshot_hash'=>$this->revisionHash($revision),'external_key'=>$externalKey?:$objectCode,'metadata'=>$metadata]);
    }
    public function markForReview(AnalysisModel $model):AnalysisModel {
        if($model->status!==AnalysisModelStatus::Draft)throw new DomainException('Only a draft analysis model can move to review.');
        if(!$model->items()->exists())throw new DomainException('Analysis model requires at least one approved technical revision.');
        $model->status=AnalysisModelStatus::Review;$model->save();return $model->refresh();
    }
    public function approve(AnalysisModel $model):AnalysisModel {
        if($model->status!==AnalysisModelStatus::Review)throw new DomainException('Only an analysis model in review can be approved.');
        $model->status=AnalysisModelStatus::Approved;$model->save();return $model->refresh();
    }
    public function revisionHash(TechnicalObjectRevision $revision):string {
        $payload=['id'=>$revision->id,'revision_no'=>$revision->revision_no,'material_id'=>$revision->material_id,'geometry'=>$revision->geometry,'properties'=>$revision->properties,'assumptions'=>$revision->assumptions];
        return hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
    }
    public function hasSupersededSources(AnalysisModel $model):bool {
        foreach($model->items()->with('technicalObjectRevision.technicalObject')->get() as $item){
            $r=$item->technicalObjectRevision;
            if($r->technicalObject->revisions()->where('revision_no','>',$r->revision_no)->where('status',EngineeringRevisionStatus::Approved->value)->exists())return true;
        }
        return false;
    }
    private function code(string $v):string{$v=Str::upper(trim($v));if($v==='')throw new DomainException('A non-empty code is required.');return $v;}
    private function text(string $v):string{$v=trim($v);if($v==='')throw new DomainException('A non-empty value is required.');return $v;}
}
