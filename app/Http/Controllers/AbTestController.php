<?php

namespace App\Http\Controllers;

use App\Models\AbTestEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbTestController extends Controller
{
    public function event(Request $request): JsonResponse
    {
        $data = $request->validate([
            'test_name' => 'required|string|max:64',
            'variant' => 'required|string|max:32',
            'event_type' => 'required|string|max:32',
            'call_id' => 'nullable|string|max:64',
            'metadata' => 'nullable|array',
        ]);

        try {
            AbTestEvent::create([
                'test_name' => $data['test_name'],
                'variant' => $data['variant'],
                'event_type' => $data['event_type'],
                'visitor_id' => $request->cookie('vacilada_vid'),
                'user_id' => $request->user()?->id,
                'call_id' => $data['call_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false], 200);
        }

        return response()->json(['ok' => true]);
    }
}
