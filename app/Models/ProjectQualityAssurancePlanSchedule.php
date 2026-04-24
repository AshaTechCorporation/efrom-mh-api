<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectQualityAssurancePlanSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'review_required_cs' => 'boolean',
        'review_required_mep' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(ProjectQualityAssurancePlan::class, 'project_quality_assurance_plan_id');
    }
}
