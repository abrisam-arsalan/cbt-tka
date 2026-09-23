<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CardService;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function __construct(private readonly CardService $cards) {}

    public function index(Exam $exam): Response
    {
        return Inertia::render('Admin/Cards/Index', [
            'title' => 'Kartu Ujian: '.$exam->title,
            'exam' => $exam->only(['id', 'title']),
            'cards' => $this->cards->bulkCardData($exam),
        ]);
    }

    /**
     * Halaman cetak massal: satu kartu per siswa, 2 kolom per A4.
     */
    public function print(Exam $exam): Response
    {
        return Inertia::render('Admin/Cards/Print', [
            'title' => 'Cetak Kartu: '.$exam->title,
            'exam' => $exam->only(['id', 'title']),
            'cards' => $this->cards->bulkCardData($exam),
        ]);
    }
}
