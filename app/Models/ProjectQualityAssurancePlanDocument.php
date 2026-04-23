<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectQualityAssurancePlanDocument extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(ProjectQualityAssurancePlan::class, 'project_quality_assurance_plan_id');
    }
}
