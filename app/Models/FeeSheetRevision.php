<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeSheetRevision extends Model
{
    use HasFactory;
    protected $fillable = [
        'fee_sheet_id',
        'rev_no',
        'is_latest',
        'fee_sheet_type',
        'project_id',
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

    public function feeSheet()
    {
        return $this->belongsTo(FeeSheet::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(FeeSheetTeamMember::class, 'revision_id');
    }

    public function feeAgreements()
    {
        return $this->hasMany(FeeAgreement::class, 'revision_id');
    }

    public function jobCostings()
    {
        return $this->hasMany(JobCosting::class, 'revision_id');
    }

    public function billingForecasts()
    {
        return $this->hasMany(BillingForecast::class, 'revision_id');
    }
}
