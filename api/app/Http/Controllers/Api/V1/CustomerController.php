<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\SearchCustomersRequest;
use App\Http\Resources\CustomerSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerServiceInterface $customerService,
    ) {}

    public function index(SearchCustomersRequest $request): AnonymousResourceCollection
    {
        /** @var string $query */
        $query = $request->validated('query');
        $result = $this->customerService->search($query, 20);

        return CustomerSummaryResource::collection($result->customers)
            ->additional(['meta' => ['total' => $result->total]]);
    }
}
