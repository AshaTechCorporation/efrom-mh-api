<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubConsultantAssessments extends Model
{
    use HasFactory;

    protected $table = 'sub_consultant_assessments';

    public function references()
    {
        return $this->hasMany(SubConsultantAssessmentReferences::class, 'assessment_id', 'id');
    }
}
