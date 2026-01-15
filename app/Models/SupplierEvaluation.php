<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierEvaluation extends Model
{
    use HasFactory;

    public function items()
    {
        return $this->hasMany(SupplierEvaluationItem::class,'supplier_evaluation_id','id');
    }
}
