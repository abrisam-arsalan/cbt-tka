<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\SchoolClass;
use App\Services\ExamSessionTokenService;
use App\Services\PresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Halaman "hub" (titik masuk) untuk menu top-level di sidebar admin.
 *
 * Sebagian fitur (bank soal, kartu, peserta, impor) secara domain terikat ke
 * satu ujian (route nested admin.exams.*). Halaman-halaman ini menyajikan
 * daftar ujian sebagai pintu masuk, sedangkan detailnya tetap di route nested.
 * Monitoring dan Hasil disajikan sebagai pandangan global lintas ujian.
 */
class HubController extends Controller
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly ExamSessionTokenService $tokens,
    ) {}

    public function bankSoal(): Response
    {
        return $this->examHub('Bank Soal', 'questions');
    }

    public function importSoal(): Response
    {
        $exams = Exam::query()
            ->withCount(['questions'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => (int) $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'questions_count' => (int) $exam->questions_count,
            ]);

        return Inertia::render('Admin/Import/Index', [
            'title' => 'Impor Soal',
            'exams' => $exams,
            'typeOptions' => \App\Enums\QuestionType::options(),
        ]);
    }

    public function cards(): Response
    {
        return $this->examHub('Cetak Kartu', 'cards');
    }

    public function peserta(): Response
    {
        return $this->examHub('Peserta', 'participants');
    }

    /**
     * Monitoring gabungan (satunya halaman monitoring): semua ujian yang
     * sedang/pernah berjalan ditampilakan dalam FORMAT REAL-TIME yang sama
     * dengan monitoring per-ujian — presence, progress, sisa waktu,
     * peringatan, dan menu Aksi di kanan tiap baris.
     */
    public function monitoring(): Response
    {
        $exams = Exam::query()
            ->where(function ($q) {
                $q->whereIn('status', [ExamStatus::Active->value, ExamStatus::Paused->value])
                    ->orWhereHas('attempts', fn ($a) => $a->whereIn('status', [
                        AttemptStatus::InProgress->value,
                        AttemptStatus::Locked->value,
                        AttemptStatus::Expired->value,
                    ]));
            })
            ->orderByDesc('id')
            ->get();

        $rows = [];
        $sessionTokens = [];

        foreach ($exams as $exam) {
            $session = $this->tokens->current($exam);

            $sessionTokens[] = [
                'exam_id' => (int) $exam->id,
                'exam_title' => $exam->title,
                'token' => $session['token'],
                'expires_at' => $session['expires_at'],
            ];

            foreach ($this->presence->monitoringRows($exam) as $row) {
                $row['exam_id'] = (int) $exam->id;
                $row['exam_title'] = $exam->title;
                $rows[] = $row;
            }
        }

        return Inertia::render('Admin/Monitoring/Index', [
            'title' => 'Monitoring Ujian',
            'exam' => null,
            'rows' => $rows,
            'summary' => $this->presence->summarize($rows),
            'session_token' => null,
            'session_tokens' => $sessionTokens,
            'exam_options' => $exams->map(fn (Exam $e) => ['value' => (int) $e->id, 'label' => $e->title])->all(),
            'presence_driver' => $this->presence->driverName(),
            'refresh_seconds' => (int) config('cbt.presence.online_seconds', 45),
        ]);
    }

    /**
     * Hasil ujian: seluruh attempt yang sudah dinilai (bisa difilter
     * ?exam=ID dan ?kelas=ID — filter yang sama dipakai tombol Cetak & Unduh).
     */
    public function hasil(Request $request): Response
    {
        [$examId, $classId] = $this->resultFilters($request);

        return Inertia::render('Admin/Results/Index', [
            'title' => 'Hasil Ujian',
            'rows' => $this->resolveRows($examId, $classId, 500),
            'exams' => Exam::query()->orderByDesc('id')->get(['id', 'title'])
                ->map(fn (Exam $e) => ['value' => (int) $e->id, 'label' => $e->title])->all(),
            'classes' => SchoolClass::query()->active()->ordered()->get(['id', 'name'])
                ->map(fn (SchoolClass $c) => ['value' => (int) $c->id, 'label' => $c->name])->all(),
            'filters' => ['exam' => $examId, 'kelas' => $classId],
        ]);
    }

    /**
     * Halaman ramah-print (A4) — dialog cetak terbuka otomatis sehingga bisa
     * dicetak ke printer atau disimpan sebagai PDF.
     */
    public function hasilPrint(Request $request): Response
    {
        [$examId, $classId] = $this->resultFilters($request);

        return Inertia::render('Admin/Results/Print', [
            'title' => 'Cetak Hasil Ujian',
            'rows' => $this->resolveRows($examId, $classId, null),
            'school_name' => (string) config('cbt.card.school_name'),
            'exam_title' => $examId ? Exam::find($examId)?->title : null,
            'class_name' => $classId ? SchoolClass::find($classId)?->name : null,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Unduh hasil sebagai Excel (.xlsx) — seluruh baris, bukan hanya 500.
     */
    public function hasilExport(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        [$examId, $classId] = $this->resultFilters($request);
        $rows = $this->resolveRows($examId, $classId, null);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hasil Ujian');

        $header = ['No', 'Nama', 'NISN/Username', 'Kelas', 'Ujian', 'Nilai', 'Benar', 'Salah', 'Kosong', 'Total Soal', 'Dikumpulkan', 'Cara Submit', 'Keterangan'];

        foreach ($header as $i => $text) {
            $sheet->setCellValue([$i + 1, 1], $text);
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $r = 2;
        foreach ($rows as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row['name'],
                $row['username'] ?? '',
                $row['class_name'],
                $row['exam_title'],
                $row['score'],
                $row['correct_count'] ?? '',
                $row['wrong_count'] ?? '',
                $row['unanswered_count'] ?? '',
                $row['total_questions'],
                $row['submitted_at'] ? Carbon::parse($row['submitted_at'])->format('d/m/Y H:i') : '',
                $row['submit_reason_label'] ?? '',
                $row['note'] ?? '',
            ], null, 'A'.$r);
            $r++;
        }

        // Lebar kolom adaptif agar rapi saat dibuka di Excel.
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cbt_hasil_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);

        $exam = $examId ? Exam::find($examId) : null;
        $nama = 'Hasil-Ujian'.($exam ? '-'.Str::slug($exam->title) : '').'-'.now()->format('d-m-Y-His').'.xlsx';

        return response()
            ->download($tmp, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resultFilters(Request $request): array
    {
        $examId = $request->query('exam') ? (int) $request->query('exam') : null;
        $classId = $request->query('kelas') ? (int) $request->query('kelas') : null;

        return [$examId, $classId];
    }

    /**
     * Baris hasil untuk tabel/cetak/unduh.
     *
     * Bila ujian tertentu dipilih → pakai ROSTER PESERTA lengkap: siswa yang
     * belum ikut tetap muncul dengan keterangan, supaya daftar nilai tidak
     * diam-diam kehilangan nama. Tanpa filter ujian → daftar attempt tersubmit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveRows(?int $examId, ?int $classId, ?int $limit): array
    {
        $exam = $examId !== null ? Exam::find($examId) : null;

        return $exam !== null
            ? $this->rosterRows($exam, $classId)
            : $this->resultRows($examId, $classId, $limit);
    }

    /**
     * Roster peserta satu ujian: data attempt tersubmit, atau baris kosong
     * dengan note ('Belum mengikuti ujian' / status attempt aktif).
     *
     * @return array<int, array<string, mixed>>
     */
    private function rosterRows(Exam $exam, ?int $classId): array
    {
        $participants = $exam->participants()
            ->active()
            ->with('user.schoolClass')
            ->orderBy('id')
            ->get();

        // Attempt dicocokkan per user (exam+user unik) — tidak menggantungkan
        // FK exam_participant_id yang bisa kosong pada data lama.
        $attemptsByUser = $exam->attempts()->get()->keyBy('user_id');

        return $participants
            ->filter(fn (ExamParticipant $p) => $classId === null || (int) ($p->user?->class_id ?? 0) === $classId)
            ->map(function (ExamParticipant $p) use ($exam, $attemptsByUser) {
                $attempt = $attemptsByUser->get($p->user_id);

                if ($attempt !== null && $attempt->status === AttemptStatus::Submitted) {
                    return [
                        'id' => (int) $attempt->id,
                        'name' => $p->user?->name ?? '(siswa terhapus)',
                        'username' => $p->user?->username,
                        'class_name' => $p->user?->schoolClass?->name ?? '-',
                        'exam_title' => $exam->title,
                        'score' => $attempt->score !== null ? (float) $attempt->score : null,
                        'correct_count' => (int) $attempt->correct_count,
                        'wrong_count' => (int) $attempt->wrong_count,
                        'unanswered_count' => (int) $attempt->unanswered_count,
                        'total_questions' => (int) $attempt->total_questions,
                        'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                        'submit_reason_label' => $attempt->submit_reason?->label(),
                        'participated' => true,
                        'note' => null,
                    ];
                }

                return [
                    'id' => 'p'.$p->id,
                    'name' => $p->user?->name ?? '(siswa terhapus)',
                    'username' => $p->user?->username,
                    'class_name' => $p->user?->schoolClass?->name ?? '-',
                    'exam_title' => $exam->title,
                    'score' => $attempt?->score !== null ? (float) $attempt->score : null,
                    'correct_count' => $attempt?->correct_count,
                    'wrong_count' => $attempt?->wrong_count,
                    'unanswered_count' => $attempt?->unanswered_count,
                    'total_questions' => $attempt !== null ? (int) $attempt->total_questions : $exam->effectiveQuestionCount(),
                    'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
                    'submit_reason_label' => $attempt?->submit_reason?->label(),
                    'participated' => false,
                    'note' => $attempt === null
                        ? 'Belum mengikuti ujian'
                        : 'Belum dikumpulkan ('.$attempt->status->label().')',
                ];
            })
            ->sortBy([['class_name', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Baris hasil ujian (attempt tersubmit terurut terbaru).
     *
     * @return array<int, array<string, mixed>>
     */
    private function resultRows(?int $examId, ?int $classId, ?int $limit): array
    {
        return Attempt::query()
            ->with(['user.schoolClass', 'exam'])
            ->where('status', AttemptStatus::Submitted->value)
            ->when($examId, fn ($q) => $q->where('exam_id', $examId))
            ->when($classId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('class_id', $classId)))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => (int) $attempt->id,
                'name' => $attempt->user?->name ?? '(siswa terhapus)',
                'username' => $attempt->user?->username,
                'class_name' => $attempt->user?->schoolClass?->name ?? '-',
                'exam_title' => $attempt->exam?->title ?? '(ujian terhapus)',
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'correct_count' => (int) $attempt->correct_count,
                'wrong_count' => (int) $attempt->wrong_count,
                'unanswered_count' => (int) $attempt->unanswered_count,
                'total_questions' => (int) $attempt->total_questions,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'submit_reason_label' => $attempt->submit_reason?->label(),
                'participated' => true,
                'note' => null,
            ])
            ->all();
    }

    /**
     * Daftar ujian sebagai pintu masuk fitur yang terikat pada satu ujian.
     *
     * @param  string  $mode  questions | cards | participants | import
     */
    private function examHub(string $title, string $mode): Response
    {
        $exams = Exam::query()
            ->withCount(['questions', 'participants', 'attempts'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => (int) $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'questions_count' => (int) $exam->questions_count,
                'participants_count' => (int) $exam->participants_count,
                'attempts_count' => (int) $exam->attempts_count,
            ]);

        return Inertia::render('Admin/Shared/ExamHub', [
            'title' => $title,
            'mode' => $mode,
            'exams' => $exams,
        ]);
    }
}
