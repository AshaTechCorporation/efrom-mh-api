<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionItems extends Model
{
    use HasFactory;

    protected $casts = [
        'need_asset_code_registration' => 'boolean',
    ];
}
