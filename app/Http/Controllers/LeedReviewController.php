<?php

namespace App\Http\Controllers;

use App\Models\LeedReview;

class LeedReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = LeedReview::class;
}
