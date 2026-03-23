<?php

namespace App\Http\Controllers;

use App\Models\ConceptDesignReview;

class ConceptDesignReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = ConceptDesignReview::class;
}
