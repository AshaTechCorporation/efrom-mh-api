<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'discipline_id',
        'prepared_by',
        'created_by',
        'comments',
        'status',
    ];

    public function project()
    {
        return $this->belongsTo(ProjectType::class, 'project_id');
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(DesignReviewDocument::class);
    }

    public function answers()
    {
        return $this->hasMany(DesignReviewAnswer::class);
    }

    public function assignment()
    {
        return $this->hasOne(DesignReviewAssignment::class);
    }

    public function signatures()
    {
        return $this->hasMany(DesignReviewSignature::class);
    }

}