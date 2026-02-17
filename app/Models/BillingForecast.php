<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingForecast extends Model
{
    use HasFactory;
    protected $fillable = [
        'fee_sheet_id',
        'month',
        'amount',
    ];

    public function feeSheet()
    {
        return $this->belongsTo(FeeSheet::class);
    }
}
