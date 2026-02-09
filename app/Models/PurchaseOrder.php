<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $casts = [
        'attachments' => 'array',
    ];

    public function items()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
