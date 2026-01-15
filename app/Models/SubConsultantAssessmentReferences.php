<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubConsultantAssessmentReferences extends Model
{
    use HasFactory;

    protected $table = 'sub_consultant_assessment_references';

    public function assessment()
    {
        return $this->belongsTo(SubConsultantAssessments::class, 'assessment_id', 'id');
    }
}
