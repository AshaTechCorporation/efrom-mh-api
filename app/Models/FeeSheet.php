<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeSheet extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'fee_sheet_type',
        'project_id',
        'mt_project_no',
        'current_revision_id'
    ];

    public function project()
    {
        return $this->belongsTo(ProposalContractReview::class, 'project_id');
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class, 'discipline_id');
    }

    public function projectType()
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function directorInCharge()
    {
        return $this->belongsTo(User::class, 'director_in_charge_id');
    }

    public function revisions()
    {
        return $this->hasMany(FeeSheetRevision::class);
    }

    public function currentRevision()
    {
        return $this->belongsTo(FeeSheetRevision::class, 'current_revision_id');
    }

}
