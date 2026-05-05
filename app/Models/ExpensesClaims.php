<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpensesClaims extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses_claims';

    protected $casts = [
        'draft_payload' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(ExpensesClaimItems::class, 'expenses_claim_id', 'id');
    }
}
