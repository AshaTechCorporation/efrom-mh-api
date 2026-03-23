<?php

namespace App\Http\Controllers;

use App\Models\EngineeringAuditReview;

class EngineeringAuditReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = EngineeringAuditReview::class;
}
