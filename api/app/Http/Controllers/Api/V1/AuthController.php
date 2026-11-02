<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Portal;
use App\Domain\Auth\Services\SignInServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Resources\MeResource;

class AuthController extends Controller
{
    public function __construct(
        private readonly SignInServiceInterface $signInService
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
}
