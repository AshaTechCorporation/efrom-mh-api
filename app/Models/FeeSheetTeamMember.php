<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeSheetTeamMember extends Model
{
    use HasFactory;
    protected $fillable = [
        'fee_sheet_id',
        'employee_code',
    ];

    public function feeSheet()
    {
        return $this->belongsTo(FeeSheet::class);
    }
}
