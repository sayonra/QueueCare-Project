<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceTokenRequest;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = $request->user()->deviceTokens()->updateOrCreate(['token' => $request->string('token')->toString()], ['platform' => $request->string('platform')->toString(), 'is_active' => true, 'last_seen_at' => now()]);

        return response()->json(['data' => ['id' => $token->id, 'registered' => true]]);
    }
}
