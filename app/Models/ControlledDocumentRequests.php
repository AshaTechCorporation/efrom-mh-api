<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ControlledDocumentRequests extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'to',
        'from',
        'date',
        'cdr_no',
        'categories',
        'request_for',
        'document_name',
        'current_revision',
        'reason_description',
        'effective_date_purpose',
        'attach_document_note',
        'requested_by',
        'requested_date',
        'review_comments',
        'reviewed_by',
        'reviewed_by_status',
        'reviewed_by_date',
        'approval_comments',
        'approved_by',
        'approved_by_status',
        'approved_by_date',
        'new_revision',
        'action_effective_date',
        'acknowledged_by',
        'acknowledged_by_status',
        'acknowledged_by_status_2',
        'acknowledged_by_date',
    ];

    protected $casts = [
        'date' => 'date',
        'effective_date_purpose' => 'date',
        'requested_date' => 'date',
        'reviewed_by_date' => 'date',
        'approved_by_date' => 'date',
        'action_effective_date' => 'date',
        'acknowledged_by_date' => 'date',
    ];
}
