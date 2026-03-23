<?php

namespace App\Http\Controllers;

use App\Models\SchematicDesignReview;

class SchematicDesignReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = SchematicDesignReview::class;
}
