<?php

namespace App\Http\Controllers;

use App\Models\ConstructionValidation;

class ConstructionValidationController extends JsonPayloadCrudController
{
    protected string $modelClass = ConstructionValidation::class;
}
