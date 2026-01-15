<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitions extends Model
{
    use HasFactory;

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionItems::class,'purchase_requisition_id','id');
    }
}
