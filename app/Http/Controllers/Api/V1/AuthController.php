<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'organization_id' => $user->organization_id,
                    'department_id' => $user->department_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $user->load(['organization', 'department']);
        $organization = $user->organization;
        $department = $user->department;

        if (! $organization instanceof Organization) {
            abort(500, 'Authenticated user is missing an organization.');
        }

        if ($department !== null && ! $department instanceof Department) {
            abort(500, 'Authenticated user has an invalid department relation.');
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'country' => $organization->country,
                    'currency' => $organization->currency,
                    'vat_rate' => $organization->vat_rate,
                ],
                'department' => $department instanceof Department ? [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                ] : null,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
