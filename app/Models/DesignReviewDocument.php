<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignReviewDocument extends Model
{
    use HasFactory;
    protected $fillable = [
        'design_review_id',
        'document_type'
    ];
}
