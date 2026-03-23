<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalContractReview extends Model
{
    use HasFactory;

    public function feeSheets()
    {
        return $this->hasMany(FeeSheet::class, 'project_id');
    }

}
