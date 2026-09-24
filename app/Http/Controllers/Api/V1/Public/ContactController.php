<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Services\Email\ContactEmailService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ContactEmailService $contactEmail,
    ) {}

    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->contactEmail->send($request->validated());

        return $this->success(null, 'Your message has been sent successfully.');
    }
}
