<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeAgreement extends Model
{
    use HasFactory;
    protected $fillable = [
        'fee_sheet_id',
        'revision_no',
        'gross_fee_excl_vat',
        'less_subconsultants_name',
        'less_subconsultants_number',
        'less_other_expenses',
        'net_fee_excl_vat',
    ];

    public function feeSheet()
    {
        return $this->belongsTo(FeeSheet::class);
    }
}
