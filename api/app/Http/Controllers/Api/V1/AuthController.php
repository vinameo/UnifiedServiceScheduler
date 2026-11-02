<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Portal;
use App\Domain\Auth\Services\SignInServiceInterface;
use App\Domain\Auth\Services\SignUpServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerSignUpRequest;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly SignInServiceInterface $signInService,
        private readonly SignUpServiceInterface $signUpService,
    ) {}

    public function staffSignIn(SignInRequest $request): MeResource
    {
        $user = $this->signInService->signIn(
            $request->validated('email'),
            $request->validated('password'),
            Portal::STAFF
        );

        return new MeResource($user);
    }

    public function customerSignUp(CustomerSignUpRequest $request): JsonResponse
    {
        $user = $this->signUpService->signUp(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('fullName'),
            $request->validated('phone'),
        );

        return (new MeResource($user))->response()->setStatusCode(201);
    }
}
