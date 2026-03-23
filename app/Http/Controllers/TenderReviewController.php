<?php

namespace App\Http\Controllers;

use App\Models\TenderReview;

class TenderReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = TenderReview::class;
}
