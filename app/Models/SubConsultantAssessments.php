<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubConsultantAssessments extends Model
{
    use HasFactory;

    protected $table = 'sub_consultant_assessments';

    protected $fillable = [
        'created_by',
    ];

    public function references()
    {
        return $this->hasMany(SubConsultantAssessmentReferences::class, 'assessment_id', 'id');
    }

    public function files()
    {
        return $this->hasMany(SubConsultantAssessmentFiles::class, 'assessment_id', 'id');
    }
}
