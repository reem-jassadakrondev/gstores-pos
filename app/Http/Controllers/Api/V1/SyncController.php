<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Terminal;
use App\Support\Sales\PosSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /** Uploads the device's offline queue. Ops are applied in order; each gets its own result. */
    public function push(Request $request, PosSync $sync): JsonResponse
    {
        $request->validate([
            'ops' => ['required', 'array', 'max:200'],
            'ops.*.op' => ['required', 'string'],
            'ops.*.data' => ['required', 'array'],
        ]);

        /** @var Terminal $terminal */
        $terminal = $request->user();

        return response()->json(['results' => $sync->apply($terminal, $request->input('ops'))]);
    }
}
