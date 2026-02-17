<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCosting extends Model
{
    use HasFactory;
    protected $fillable = [
        'fee_sheet_id',
        'phase',
        'percent',
        'start_date',
        'end_date',
    ];

    public function feeSheet()
    {
        return $this->belongsTo(FeeSheet::class);
    }
}
