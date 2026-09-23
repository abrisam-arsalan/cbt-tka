<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SyncAnswersRequest;
use App\Models\Attempt;
use App\Models\Exam;
use App\Services\AnswerSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function store(Exam $exam, SyncAnswersRequest $request, AnswerSyncService $sync): JsonResponse
    {
        $user = $request->user();

        $attempt = Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($attempt === null) {
            return response()->json([
                'accepted' => 0,
                'rejected' => 0,
                'attempt_status' => 'not_found',
                'attempt_message' => 'Anda belum memulai ujian ini.',
                'remaining_seconds' => 0,
                'server_time' => now()->getTimestampMs(),
                'items' => [],
            ], 404);
        }

        $result = $sync->sync($attempt, $user, $request->validated('items'));

        return response()->json($result->toArray());
    }
}