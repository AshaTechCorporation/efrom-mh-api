<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class FeeSheetTeamMember extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'revision_id',
        'employee_code',
    ];

    public function revision()
    {
        return $this->belongsTo(FeeSheetRevision::class);
    }
}
