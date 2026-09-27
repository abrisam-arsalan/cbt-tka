<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Enums\SubmitReason;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Services\ExamTimerService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Engine ujian siswa.
 *
 * Halaman ini adalah satu-satunya yang benar-benar "real-time" di aplikasi.
 * Semua data penting (waktu tersisa, status attempt, jumlah soal terjawab)
 * di-render ulang di server setiap reload, lalu disinkronkan di klien dengan
 * serverTime dari HandleInertiaRequests agar jam HP siswa tidak menjadi
 * sumber kebenaran.
 */
class ExamController extends Controller
{
    public function __construct(private readonly ExamTimerService $timer) {}

    public function index(Request $request): Response
    {
        return $this->listForStudent($request, 'Daftar Ujian');
    }

    public function show(Request $request, Exam $exam): Response
    {
        $user = $request->user();

        $participant = ExamParticipant::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            abort(403, 'Anda bukan peserta ujian ini.');
        }

        if (! $exam->accessibleToUser($user)) {
            abort(403, 'Ujian ini khusus untuk kelas tertentu.');
        }

        $attempt = $this->findAttempt($exam, $user);

        return Inertia::render('Student/ExamShow', [
            'title' => $exam->title,
            'exam' => $this->examPayload($exam),
            'participant' => [
                'first_joined_at' => $participant->first_joined_at?->toIso8601String(),
            ],
            'attempt' => $this->attemptPayload($attempt),
            'canStart' => $attempt === null && $exam->isJoinableNow(),
            'unavailableReason' => $exam->unavailableReason(),
        ]);
    }

    /**
     * Halaman mengerjakan ujian: menampilkan soal, indikator waktu, dan
     * status sinkronisasi.
     */
    public function run(Request $request, Exam $exam): Response
    {
        $user = $request->user();

        $participant = ExamParticipant::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $exam->accessibleToUser($user)) {
            abort(403, 'Ujian ini khusus untuk kelas tertentu.');
        }

        $attempt = $this->findAttempt($exam, $user);

        // Jika attempt belum ada tapi ujian sudah boleh diikuti, tampilkan
        // layar start. Kalau ujian sudah tidak boleh diikuti, siswa tidak
        // bisa masuk ke halaman ini — dialihkan ke show().
        if ($attempt === null && ! $exam->isJoinableNow()) {
            return redirect()
                ->route('student.exams.show', $exam)
                ->with('error', $exam->unavailableReason() ?? 'Ujian tidak bisa diikuti sekarang.');
        }

        $questions = $exam->questions()
            ->with(['options' => fn ($q) => $q->orderBy('order')->orderBy('id'), 'matchingPairs'])
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        if ($attempt !== null) {
            $questions = $this->applyShuffle($exam, $questions, (int) $attempt->shuffle_seed);
        }

        $answers = $attempt !== null
            ? $attempt->answers()->get()->keyBy('question_id')
            : collect();

        return Inertia::render('Student/Exam', [
            'title' => 'Mengerjakan: '.$exam->title,
            'exam' => $this->examPayload($exam),
            'participant' => [
                'id' => $participant->id,
            ],
            'attempt' => $this->attemptPayload($attempt),
            'questions' => $this->questionsPayload($questions, $attempt),
            'answers' => $this->answersPayload($answers),
            'canStart' => $attempt === null && $exam->isJoinableNow(),
        ]);
    }

    public function start(Request $request, Exam $exam): RedirectResponse
    {
        $user = $request->user();

        if (! $exam->isJoinableNow()) {
            return redirect()
                ->route('student.exams.show', $exam)
                ->with('error', $exam->unavailableReason() ?? 'Ujian tidak bisa dimulai sekarang.');
        }

        $participant = ExamParticipant::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($participant === null) {
            abort(403, 'Anda bukan peserta ujian ini.');
        }

        if (! $exam->accessibleToUser($user)) {
            abort(403, 'Ujian ini khusus untuk kelas tertentu.');
        }

        $this->timer->startAttempt(
            exam: $exam,
            user: $user,
            participant: $participant,
            ip: (string) $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return redirect()->route('student.exam.run', $exam);
    }

    public function submit(Request $request, Exam $exam): RedirectResponse
    {
        $user = $request->user();
        $attempt = $this->findAttempt($exam, $user);

        if ($attempt === null) {
            return redirect()
                ->route('student.exams.show', $exam)
                ->with('error', 'Anda belum memulai ujian ini.');
        }

        $this->timer->submit($attempt, SubmitReason::Manual, (string) $request->ip());

        return redirect()
            ->route('student.history.show', $attempt)
            ->with('success', 'Jawaban berhasil dikumpulkan.');
    }

    private function listForStudent(Request $request, string $title): Response
    {
        $user = $request->user();

        $examIds = $user->examParticipations()->where('is_active', true)->pluck('exam_id');

        $exams = Exam::query()
            ->whereIn('id', $examIds)
            ->where(fn ($q) => $q->whereNull('class_id')->orWhere('class_id', $user->class_id))
            ->withCount(['questions' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Exam $exam) use ($user) {
                $attempt = $this->findAttempt($exam, $user);

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'start_at' => $exam->start_at?->toIso8601String(),
                    'end_at' => $exam->end_at?->toIso8601String(),
                    'duration_minutes' => (int) $exam->duration_minutes,
                    'status' => $exam->status->value,
                    'status_label' => $exam->status->label(),
                    'is_joinable' => $exam->isJoinableNow(),
                    'unavailable_reason' => $exam->unavailableReason(),
                    'questions_count' => (int) $exam->questions_count,
                    'attempt' => $this->attemptPayload($attempt),
                ];
            });

        return Inertia::render('Student/Exams', [
            'title' => $title,
            'exams' => $exams,
        ]);
    }

    private function findAttempt(Exam $exam, $user): ?Attempt
    {
        return Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function examPayload(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'description' => $exam->description,
            'start_at' => $exam->start_at?->toIso8601String(),
            'end_at' => $exam->end_at?->toIso8601String(),
            'duration_minutes' => (int) $exam->duration_minutes,
            'status' => $exam->status->value,
            'anti_cheat_enabled' => (bool) $exam->anti_cheat_enabled,
            'anti_cheat_action' => $exam->anti_cheat_action?->value,
            'anti_cheat_max_warnings' => (int) $exam->maxWarnings(),
            'shuffle_questions' => (bool) $exam->shuffle_questions,
            'shuffle_options' => (bool) $exam->shuffle_options,
            'offline_grace_minutes' => (int) $exam->graceMinutes(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attemptPayload(?Attempt $attempt): ?array
    {
        if ($attempt === null) {
            return null;
        }

        $now = now();

        return [
            'id' => $attempt->id,
            'status' => $attempt->status->value,
            'status_label' => $attempt->status->label(),
            'started_at' => $attempt->started_at?->toIso8601String(),
            'deadline_at' => $attempt->deadline_at?->toIso8601String(),
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'remaining_seconds' => $attempt->remainingSeconds($now),
            'warnings_count' => (int) $attempt->warnings_count,
            'total_questions' => (int) $attempt->total_questions,
            'score' => $attempt->score !== null ? (float) $attempt->score : null,
            'correct_count' => (int) $attempt->correct_count,
            'wrong_count' => (int) $attempt->wrong_count,
            'unanswered_count' => (int) $attempt->unanswered_count,
            'past_deadline' => $attempt->isPastDeadline($now),
            'past_grace' => $attempt->isPastGracePeriod($now),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionsPayload(EloquentCollection $questions, ?Attempt $attempt): array
    {
        return $questions->map(function ($question, $index) {
            $options = $question->options->map(fn ($option) => [
                'id' => (int) $option->id,
                'label' => $option->label,
                'text' => $option->option_text,
                'media_url' => $option->media_url,
                // is_correct TIDAK dikirim ke siswa — itu adalah kunci.
            ])->all();

            $pairs = $question->matchingPairs->map(fn ($pair) => [
                'id' => (int) $pair->id,
                'left_text' => $pair->left_text,
                'right_text' => $pair->right_text,
                'left_media_url' => $pair->left_media_url,
                'right_media_url' => $pair->right_media_url,
            ])->all();

            return [
                'id' => (int) $question->id,
                'number' => $index + 1,
                'order' => (int) $question->order,
                'type' => $question->type->value,
                'type_label' => $question->type->labelShort(),
                'instruction' => $question->type->instruction(),
                'stimulus' => $question->stimulus,
                'question_text' => $question->question_text,
                'media_url' => $question->media_url,
                'options' => $options,
                'pairs' => $pairs,
                'item_count' => $question->itemCount(),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>> keyed by question id
     */
    private function answersPayload(Collection $answers): array
    {
        return $answers->mapWithKeys(fn ($answer) => [
            (int) $answer->question_id => [
                'answer_payload' => $answer->answer_payload,
                'idempotency_key' => $answer->idempotency_key,
                'client_seq' => (int) $answer->client_seq,
                'late_sync_flag' => (bool) $answer->late_sync_flag,
                'updated_at' => $answer->updated_at?->toIso8601String(),
            ],
        ])->all();
    }

    /**
     * Shuffle soal/opsi dengan seed stabil per attempt.
     *
     * @return EloquentCollection<int, mixed>
     */
    private function applyShuffle(Exam $exam, EloquentCollection $questions, int $seed): EloquentCollection
    {
        if ((bool) $exam->shuffle_questions) {
            $questions = $this->shuffleCollection($questions, $seed);
        }

        if ((bool) $exam->shuffle_options) {
            foreach ($questions as $question) {
                if ($question->type->usesOptions()) {
                    $question->setRelation(
                        'options',
                        $this->shuffleCollection($question->options, $seed ^ ((int) $question->id + 1)),
                    );
                }

                if ($question->type === QuestionType::Matching) {
                    $question->setRelation(
                        'matchingPairs',
                        $this->shuffleCollection($question->matchingPairs, $seed ^ ((int) $question->id + 7)),
                    );
                }
            }
        }

        return $questions;
    }

    /**
     * Shuffle deterministik dengan seeded PRNG sederhana.
     *
     * @template T
     * @param  Collection<int, T>|EloquentCollection<int, T>  $collection
     * @return EloquentCollection<int, T>
     */
    private function shuffleCollection(Collection|EloquentCollection $collection, int $seed): EloquentCollection
    {
        $items = $collection->all();

        if (count($items) < 2) {
            return $collection instanceof EloquentCollection ? $collection : new EloquentCollection($items);
        }

        // Algoritma Fisher-Yates dengan seeded rand. Cukup untuk tujuan
        // pengacakan soal; bukan sumber acak kriptografis.
        //
        // State dikunci ke 31 bit: hasil kali berikutnya maks 2^31 * 1.1e9
        // ~= 2.4e18, selalu integer 64-bit. Tanpa clamp, seed besar membuat
        // perkalian meluap ke float dan cast int-nya memicu deprecation
        // PHP 8.5 ("float not representable as an int") -> ErrorException
        // -> 500 saat siswa membuka ujian dengan shuffle aktif.
        $state = $seed & 0x7fffffff;

        for ($i = count($items) - 1; $i > 0; $i--) {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;
            $j = $state % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return new EloquentCollection($items);
    }
}
