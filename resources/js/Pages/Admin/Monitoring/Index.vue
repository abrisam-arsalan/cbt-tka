<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    summary: Object,
    rows: { type: Array, default: () => [] },
    presence_driver: String,
    refresh_seconds: Number,
    session_token: { type: Object, default: null },
});

let refreshTimer = null;

onMounted(() => {
    // Refresh monitoring berkala agar presence tetap akurat.
    const interval = Math.max(15, Math.min(60, props.refresh_seconds ?? 30)) * 1000;
    refreshTimer = setInterval(() => {
        router.reload({ preserveScroll: true });
    }, interval);
});

onBeforeUnmount(() => clearInterval(refreshTimer));

const formatRemaining = (seconds) => {
    if (seconds == null) return '-';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
};

const formatTime = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const statusClass = (status) => ({
    in_progress: 'bg-brand-100 text-brand-700',
    locked: 'bg-warning-100 text-warning-700',
    expired: 'bg-slate-200 text-slate-600',
    submitted: 'bg-success-100 text-success-700',
}[status] || 'bg-slate-100 text-slate-600');

const post = (name, attemptId) => router.post(route(name, { exam: props.exam.id, attempt: attemptId }));

// Force majeure (mis. tidak sengaja klik Kumpulkan): hapus attempt + jawaban
// agar siswa bisa mengerjakan ulang. Butuh konfirmasi karena permanen.
const resetExam = (row) => {
    if (!row.attempt_id) return;
    if (!confirm(`Reset ujian ${row.name}?\n\nSemua jawabannya terhapus dan siswa dapat mengerjakan ulang dari awal. Tindakan ini permanen.`)) return;
    router.post(route('admin.exams.monitoring.reset', { exam: props.exam.id, attempt: row.attempt_id }));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Monitoring Ujian</h2>
                <p class="text-xs text-slate-400">{{ exam.title }} · presence: {{ presence_driver }}</p>
            </div>

            <!-- Token sesi: umumkan ke siswa, berganti otomatis tiap 30 menit -->
            <div v-if="session_token" class="rounded-xl bg-brand-600 px-5 py-3 text-white shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Token Sesi — umumkan ke siswa</p>
                <div class="flex items-center gap-3">
                    <span class="font-mono text-2xl font-bold tracking-widest">{{ session_token.token }}</span>
                    <span class="text-xs opacity-90">
                        berlaku s/d
                        {{ new Date(session_token.expires_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-xl font-bold text-slate-700">{{ summary.total }}</div>
                <div class="text-xs text-slate-500">Total</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-xl font-bold text-success-600">{{ summary.online }}</div>
                <div class="text-xs text-slate-500">Online</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-xl font-bold text-slate-500">{{ summary.offline }}</div>
                <div class="text-xs text-slate-500">Offline</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-xl font-bold text-brand-600">{{ summary.in_progress }}</div>
                <div class="text-xs text-slate-500">Berjalan</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-xl font-bold text-success-600">{{ summary.submitted }}</div>
                <div class="text-xs text-slate-500">Selesai</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-xl font-bold text-danger-600">{{ summary.with_warnings }}</div>
                <div class="text-xs text-slate-500">Peringatan</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-3 py-3">Siswa</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Progress</th>
                        <th class="px-3 py-3">Sisa Waktu</th>
                        <th class="px-3 py-3">Koneksi</th>
                        <th class="px-3 py-3">⚠</th>
                        <th class="px-3 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.attempt_id" class="border-b border-slate-100 last:border-0">
                        <td class="px-3 py-3">
                            <div class="font-medium text-slate-800">{{ row.name }}</div>
                            <div class="text-xs text-slate-400">{{ row.class_name }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(row.status)">{{ row.status_label }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="w-24">
                                <div class="mb-1 h-1.5 rounded bg-slate-200">
                                    <div class="h-1.5 rounded bg-success-500" :style="{ width: row.progress_percent + '%' }"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ row.answered_count }}/{{ row.total_questions }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 tabular-nums">{{ row.status === 'submitted' ? '-' : formatRemaining(row.remaining_seconds) }}</td>
                        <td class="px-3 py-3">
                            <span class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full" :class="row.online ? 'bg-success-500' : 'bg-slate-300'"></span>
                                <span class="text-xs">{{ row.online ? 'Online' : 'Offline' }}</span>
                                <span v-if="row.outbox_pending > 0" class="text-xs text-warning-600">({{ row.outbox_pending }} pending)</span>
                            </span>
                            <div class="text-[10px] text-slate-400">Terakhir: {{ formatTime(row.last_seen_at) }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <span :class="row.warnings_count > 0 ? 'text-danger-600 font-bold' : 'text-slate-300'">{{ row.warnings_count }}</span>
                            <span v-if="row.late_answers_count > 0" class="ml-1 text-xs text-warning-600" :title="'Jawaban late: ' + row.late_answers_count">
                                ⏰{{ row.late_answers_count }}
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <details class="relative">
                                <summary class="cursor-pointer rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Aksi</summary>
                                <div class="absolute right-0 z-10 mt-1 flex w-44 flex-col rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                    <button class="rounded px-2 py-1.5 text-left text-xs hover:bg-slate-50" @click="post('admin.exams.monitoring.extend', row.attempt_id)">Perpanjang +5 menit</button>
                                    <button class="rounded px-2 py-1.5 text-left text-xs hover:bg-slate-50" @click="post('admin.exams.monitoring.unlock', row.attempt_id)">Buka Kunci</button>
                                    <button class="rounded px-2 py-1.5 text-left text-xs hover:bg-slate-50" @click="post('admin.exams.monitoring.reset-warnings', row.attempt_id)">Reset Warning</button>
                                    <button class="rounded px-2 py-1.5 text-left text-xs text-danger-600 hover:bg-danger-50" @click="post('admin.exams.monitoring.force-submit', row.attempt_id)">Submit Paksa</button>
                                    <button
                                        v-if="row.attempt_id"
                                        class="rounded px-2 py-1.5 text-left text-xs text-danger-700 hover:bg-danger-50 border-t border-slate-100"
                                        title="Force majeure: hapus attempt & jawaban, siswa bisa mengulang"
                                        @click="resetExam(row)"
                                    >
                                        Reset Ujian
                                    </button>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada attempt pada ujian ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
