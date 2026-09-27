<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
});

const post = (routeName) => {
    router.post(route(routeName, props.exam.id));
};

const closeAutoSubmit = () => {
    if (!confirm('Tutup ujian DAN submit semua attempt aktif? Skor dihitung dari jawaban yang sudah masuk. Tindakan ini tidak bisa dibatalkan.')) return;
    router.post(route('admin.exams.close-auto-submit', props.exam.id));
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const actionLabel = {
    log_only: 'Hanya catat log',
    warning_only: 'Tampilkan peringatan',
    auto_submit_after_limit: 'Auto submit',
    lock_after_limit: 'Kunci attempt',
}[props.exam.anti_cheat_action] || '-';
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ exam.title }}</h2>
                <p class="text-sm text-slate-500">
                    {{ exam.status_label }}
                    <span v-if="exam.target_label" class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        👥 {{ exam.target_label }}
                    </span>
                    <span v-if="exam.question_count" class="ml-2 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">
                        📝 {{ exam.question_count }} soal keluar dari {{ exam.total_active_questions }} di bank
                    </span>
                </p>
            </div>
            <Link :href="route('admin.exams.edit', exam.id)" class="rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">
                Edit
            </Link>
        </div>

        <!-- Blocker sebelum aktifkan -->
        <div v-if="exam.activation_blockers.length > 0 && exam.status === 'draft'" class="mb-4 rounded-xl bg-warning-50 p-4 text-sm text-warning-800">
            <p class="mb-1 font-semibold">Ujian belum bisa diaktifkan:</p>
            <ul class="list-inside list-disc space-y-0.5">
                <li v-for="blocker in exam.activation_blockers" :key="blocker">{{ blocker }}</li>
            </ul>
        </div>

        <!-- Tombol aksi status -->
        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-if="exam.status === 'draft'"
                :disabled="!exam.can_activate"
                class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40"
                @click="post('admin.exams.activate')"
            >
                Aktifkan
            </button>
            <button
                v-if="exam.status === 'active'"
                class="rounded-xl bg-warning-500 px-4 py-2.5 text-sm font-semibold text-white"
                @click="post('admin.exams.pause')"
            >
                Jeda Ujian
            </button>
            <button
                v-if="exam.status === 'paused'"
                class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white"
                @click="post('admin.exams.resume')"
            >
                Lanjutkan
            </button>
            <button
                v-if="exam.status === 'active' || exam.status === 'paused'"
                class="rounded-xl bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white"
                @click="post('admin.exams.close')"
            >
                Tutup
            </button>
            <button
                v-if="exam.status === 'active' || exam.status === 'paused'"
                class="rounded-xl bg-danger-600 px-4 py-2.5 text-sm font-semibold text-white"
                @click="closeAutoSubmit"
            >
                Tutup &amp; Auto Submit
            </button>
        </div>

        <!-- Detail -->
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-xs text-slate-500">Durasi</div>
                <div class="font-semibold">{{ exam.duration_minutes }} menit</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-xs text-slate-500">Soal</div>
                <div class="font-semibold">{{ exam.questions_count }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-xs text-slate-500">Peserta</div>
                <div class="font-semibold">{{ exam.participants_count }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-xs text-slate-500">Attempt Aktif</div>
                <div class="font-semibold">{{ exam.active_attempts }}</div>
            </div>
        </div>

        <div class="mb-4 rounded-xl bg-white p-4 text-sm shadow-sm">
            <div class="mb-2 grid grid-cols-2 gap-3">
                <div><span class="text-slate-500">Mulai:</span> {{ formatDate(exam.start_at) }}</div>
                <div><span class="text-slate-500">Selesai:</span> {{ formatDate(exam.end_at) }}</div>
                <div><span class="text-slate-500">Anti-Cheat:</span> {{ exam.anti_cheat_enabled ? 'Aktif (' + actionLabel + ')' : 'Nonaktif' }}</div>
                <div><span class="text-slate-500">Grace Period:</span> {{ exam.offline_grace_minutes }} menit</div>
            </div>
            <p v-if="exam.description" class="text-slate-600">{{ exam.description }}</p>
        </div>

        <!-- Navigasi modul -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Link :href="route('admin.exams.questions.index', exam.id)" class="rounded-xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="font-semibold text-slate-800">📝 Bank Soal</div>
                <div class="text-xs text-slate-500">{{ exam.questions_count }} soal</div>
            </Link>
            <Link :href="route('admin.exams.participants.index', exam.id)" class="rounded-xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="font-semibold text-slate-800">👥 Peserta</div>
                <div class="text-xs text-slate-500">{{ exam.participants_count }} siswa</div>
            </Link>
            <Link :href="route('admin.exams.cards.index', exam.id)" class="rounded-xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="font-semibold text-slate-800">🪪 Kartu Ujian</div>
                <div class="text-xs text-slate-500">QR + token</div>
            </Link>
            <Link :href="route('admin.exams.monitoring.index', exam.id)" class="rounded-xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="font-semibold text-slate-800">📊 Monitoring</div>
                <div class="text-xs text-slate-500">Presence real-time</div>
            </Link>
        </div>
    </AdminLayout>
</template>
