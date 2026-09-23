<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Services\AuditLogService;
use App\Services\CardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantController extends Controller
{
    public function __construct(
        private readonly CardService $cards,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Exam $exam): Response
    {
        $participants = $exam->participants()
            ->with('user.schoolClass')
            ->orderBy('id')
            ->get();

        $availableUsers = \App\Models\User::query()
            ->siswa()
            ->active()
            ->whereNotIn('id', $participants->pluck('user_id'))
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Participants/Index', [
            'title' => 'Peserta: '.$exam->title,
            'exam' => $exam->only(['id', 'title', 'status']),
            'participants' => $participants->map(fn (ExamParticipant $p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->name ?? '(terhapus)',
                'username' => $p->user?->username,
                'class_name' => $p->user?->schoolClass?->name ?? '-',
                'has_token' => $p->hasToken(),
                'is_active' => (bool) $p->is_active,
                'first_joined_at' => $p->first_joined_at?->toIso8601String(),
            ]),
            'availableUsers' => $availableUsers->map(fn ($u) => [
                'id' => $u->id,
                'label' => $u->displayLabel(),
            ]),
        ]);
    }

    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('role', 'siswa'),
                Rule::unique('exam_participants', 'user_id')->where('exam_id', $exam->id),
            ],
        ], [
            'user_id.unique' => 'Siswa ini sudah terdaftar sebagai peserta.',
            'user_id.exists' => 'User tidak ditemukan atau bukan siswa.',
        ]);

        $participant = $exam->participants()->create([
            'user_id' => $validated['user_id'],
            'token_hash' => '',
            'is_active' => true,
        ]);

        $this->cards->generateToken($participant);

        $this->audit->log(
            action: 'participant.added',
            subject: $participant,
            description: "Peserta ditambahkan ke ujian {$exam->title}.",
        );

        return back()->with('success', 'Peserta berhasil ditambahkan beserta token ujian.');
    }

    /**
     * Tambah banyak siswa sekaligus.
     */
    public function bulk(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('role', 'siswa')],
        ]);

        $existing = $exam->participants()->pluck('user_id')->all();

        $added = 0;
        $now = now();

        foreach ($validated['user_ids'] as $userId) {
            if (in_array((int) $userId, $existing, true)) {
                continue;
            }

            $participant = $exam->participants()->create([
                'user_id' => $userId,
                'token_hash' => '',
                'is_active' => true,
            ]);

            $this->cards->generateToken($participant);
            $added++;
        }

        $this->audit->log(
            action: 'participant.bulk_added',
            subject: $exam,
            description: "{$added} peserta ditambahkan massal ke ujian {$exam->title}.",
            meta: ['added' => $added, 'skipped' => count($validated['user_ids']) - $added],
        );

        return back()->with('success', "{$added} peserta berhasil ditambahkan.");
    }

    public function destroy(Exam $exam, ExamParticipant $participant): RedirectResponse
    {
        if ((int) $participant->exam_id !== (int) $exam->id) {
            abort(404);
        }

        $hasAttempt = $participant->attempt()->exists();

        if ($hasAttempt) {
            $participant->update(['is_active' => false]);

            $this->audit->log(
                action: 'participant.deactivated',
                subject: $participant,
                description: 'Peserta dinonaktifkan (memiliki attempt, tidak dihapus).',
            );

            return back()->with('warning', 'Peserta sudah punya attempt sehingga hanya dinonaktifkan, tidak dihapus.');
        }

        $participant->delete();

        $this->audit->log(
            action: 'participant.removed',
            subject: $exam,
            description: 'Peserta dihapus dari ujian.',
        );

        return back()->with('success', 'Peserta berhasil dihapus.');
    }

    public function generateTokens(Exam $exam): RedirectResponse
    {
        $generated = $this->cards->ensureTokensForExam($exam);

        if ($generated === []) {
            return back()->with('info', 'Semua peserta sudah memiliki token.');
        }

        return back()->with('success', count($generated).' token berhasil digenerate.');
    }

    public function regenerateToken(Exam $exam, ExamParticipant $participant): RedirectResponse
    {
        if ((int) $participant->exam_id !== (int) $exam->id) {
            abort(404);
        }

        $this->cards->regenerateToken($participant, request()->user());

        return back()->with('success', 'Token peserta berhasil digenerate ulang. Cetak ulang kartu ujiannya.');
    }
}
