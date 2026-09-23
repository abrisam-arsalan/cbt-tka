<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\HeartbeatRequest;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\PresenceHeartbeat;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function store(Exam $exam, HeartbeatRequest $request, PresenceService $presence): JsonResponse
    {
        $user = $request->user();

        $attempt = Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $presence->ping($attempt, [
            'status' => $request->input('status', 'online'),
            'last_client_seq' => $request->integer('last_client_seq', 0),
            'outbox_pending' => $request->integer('outbox_pending', 0),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'driver' => $presence->driverName(),
            'remaining_seconds' => $attempt->remainingSeconds(),
            'server_time' => now()->getTimestampMs(),
        ]);
    }
}