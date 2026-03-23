<?php

namespace App\Http\Controllers;

use App\Models\PostmanProposalContractReview;

class PostmanProposalContractReviewController extends JsonPayloadCrudController
{
    protected string $modelClass = PostmanProposalContractReview::class;

    protected array $coreFieldMap = [
        'project_name' => ['project_name', 'projectName'],
        'project_no' => ['project_no', 'projectNumber'],
        'client_name' => ['client_name'],
        'city' => ['city'],
        'country' => ['country'],
        'filled_in_by' => ['filled_in_by', 'preparedBy'],
        'proposal_to_be_submitted' => ['proposal_to_be_submitted'],
        'contract_agreed_to_proceed' => ['contract_agreed_to_proceed'],
        'status' => ['status'],
    ];

    protected array $exactFilterMap = [
        'proposal_to_be_submitted' => 'proposal_to_be_submitted',
        'contract_agreed_to_proceed' => 'contract_agreed_to_proceed',
        'status' => 'status',
    ];

    protected array $likeFilterMap = [
        'project_name' => 'project_name',
        'project_no' => 'project_no',
        'client_name' => 'client_name',
        'filled_in_by' => 'filled_in_by',
        'city' => 'city',
        'country' => 'country',
    ];

    protected array $searchableColumns = [
        'project_name',
        'project_no',
        'client_name',
        'city',
        'country',
        'filled_in_by',
        'proposal_to_be_submitted',
        'contract_agreed_to_proceed',
        'status',
    ];

    protected array $orderColumns = [
        0 => 'id',
        1 => 'project_name',
        2 => 'project_no',
        3 => 'client_name',
        4 => 'city',
        5 => 'country',
        6 => 'status',
        7 => 'created_at',
    ];
}
