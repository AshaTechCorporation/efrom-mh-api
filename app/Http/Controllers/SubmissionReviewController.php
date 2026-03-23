<?php

namespace App\Http\Controllers;

use App\Models\SubmissionReview;

class SubmissionReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = SubmissionReview::class;
}
