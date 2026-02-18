<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingForecast extends Model
{
    use HasFactory;
    protected $fillable = [
        'revision_id',
        'month',
        'amount',
    ];

    public function revision()
    {
        return $this->belongsTo(FeeSheetRevision::class);
    }
}
