<?php

namespace App\Http\Controllers;

use App\Models\ValueEngineeringReview;

class ValueEngineeringReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = ValueEngineeringReview::class;
}
