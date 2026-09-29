<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    title: String,
    rows: { type: Array, default: () => [] },
    school_name: { type: String, default: '' },
    exam_title: { type: String, default: null },
    class_name: { type: String, default: null },
    generated_at: { type: String, default: '' },
});

// Kolom Ujian disembunyikan bila hasil sudah difilter per ujian.
const showExamColumn = !props.exam_title;

onMounted(() => {
    // Otomatis buka dialog cetak browser (=> printer atau "Simpan sebagai PDF").
    setTimeout(() => window.print(), 500);
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const today = () => new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
</script>

<template>
    <div class="min-h-screen bg-white p-6 text-slate-900">
        <Head :title="title" />

        <div class="no-print mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold">Cetak Hasil Ujian</h2>
            <div class="flex gap-2">
                <button class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white" @click="window.print()">
                    🖨 Cetak Lagi
                </button>
                <Link :href="route('admin.results.index')" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">
                    Kembali
                </Link>
            </div>
        </div>

        <!-- KOP -->
        <div class="mb-4 border-b-2 border-slate-900 pb-3 text-center">
            <p class="text-lg font-bold uppercase tracking-wide">{{ school_name }}</p>
            <p class="text-sm font-semibold">Daftar Hasil Ujian</p>
            <p v-if="exam_title || class_name" class="text-sm">
                <span v-if="class_name">Kelas {{ class_name }}</span>
                <span v-if="class_name && exam_title"> — </span>
                <span v-if="exam_title">{{ exam_title }}</span>
            </p>
        </div>

        <p class="mb-2 text-xs text-slate-500">
            Dicetak: {{ today() }} · Jumlah peserta selesai: {{ rows.length }}
        </p>

        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-slate-100 text-left">
                    <th class="border border-slate-400 px-2 py-1.5">No</th>
                    <th class="border border-slate-400 px-2 py-1.5">Nama</th>
                    <th class="border border-slate-400 px-2 py-1.5">NISN</th>
                    <th class="border border-slate-400 px-2 py-1.5">Kelas</th>
                    <th v-if="showExamColumn" class="border border-slate-400 px-2 py-1.5">Ujian</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-center">Nilai</th>
                    <th class="border border-slate-400 px-2 py-1.5 text-center">B/S/K</th>
                    <th class="border border-slate-400 px-2 py-1.5">Dikumpulkan</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, i) in rows" :key="row.id">
                    <td class="border border-slate-400 px-2 py-1">{{ i + 1 }}</td>
                    <td class="border border-slate-400 px-2 py-1 font-medium">{{ row.name }}</td>
                    <td class="border border-slate-400 px-2 py-1">{{ row.username ?? '-' }}</td>
                    <td class="border border-slate-400 px-2 py-1">{{ row.class_name }}</td>
                    <td v-if="showExamColumn" class="border border-slate-400 px-2 py-1">{{ row.exam_title }}</td>
                    <td class="border border-slate-400 px-2 py-1 text-center font-bold">{{ row.score ?? '-' }}</td>
                    <td class="border border-slate-400 px-2 py-1 text-center">{{ row.correct_count }}/{{ row.wrong_count }}/{{ row.unanswered_count }}</td>
                    <td class="border border-slate-400 px-2 py-1 text-xs">{{ formatDate(row.submitted_at) }}</td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td :colspan="showExamColumn ? 8 : 7" class="border border-slate-400 px-2 py-4 text-center text-slate-500">Tidak ada data pada filter ini.</td>
                </tr>
            </tbody>
        </table>

        <!-- TTD -->
        <div class="mt-10 grid grid-cols-2 text-sm">
            <div class="text-center">
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="h-20"></div>
                <p class="font-semibold underline">(............................)</p>
                <p>NIP. ............................</p>
            </div>
            <div class="text-center">
                <p>Tegal, {{ today() }}</p>
                <p>Panitia Ujian</p>
                <div class="h-20"></div>
                <p class="font-semibold underline">(............................)</p>
                <p>NIP. ............................</p>
            </div>
        </div>

        <style>
            @media print {
                thead {
                    display: table-header-group; /* header tabel berulang tiap halaman */
                }
                tr {
                    break-inside: avoid;
                }
            }
        </style>
    </div>
</template>
