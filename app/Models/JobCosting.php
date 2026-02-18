<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCosting extends Model
{
    use HasFactory;
    protected $fillable = [
        'revision_id',
        'phase',
        'percent',
        'start_date',
        'end_date',
    ];

    public function revision()
    {
        return $this->belongsTo(FeeSheetRevision::class);
    }
}
