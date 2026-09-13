<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()), 'Profile fetched successfully.');
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user()->fill($request->validated());
        $user->save();

        return $this->success(new UserResource($user->refresh()), 'Profile updated successfully.');
    }
}
