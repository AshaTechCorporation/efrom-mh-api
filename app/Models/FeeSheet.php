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
        'project_name',
        'discipline_id',
        'director_in_charge_id',
        'client_name',
        'location',
        'mtl_scope_detail',
        'contact_name',
        'comment',
        'project_type_id',
        'form_filled_by_id',
        'form_filled_by_date',
        'approved_by_ch_id',
        'approved_by_ch_date',
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

    public function teamMembers()
    {
        return $this->hasMany(FeeSheetTeamMember::class);
    }

    public function feeAgreements()
    {
        return $this->hasMany(FeeAgreement::class);
    }

    public function jobCostings()
    {
        return $this->hasMany(JobCosting::class);
    }

    public function billingForecasts()
    {
        return $this->hasMany(BillingForecast::class);
    }
}
