<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

defineProps({
    title: String,
    exam: Object,
    participant: Object,
    attempt: { type: Object, default: null },
    canStart: Boolean,
    unavailableReason: { type: String, default: null },
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-lg">
            <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm">
                <span
                    class="mb-2 inline-block rounded-full px-3 py-1 text-xs font-medium"
                    :class="{
                        'bg-success-100 text-success-700': exam.status === 'active',
                        'bg-warning-100 text-warning-700': exam.status === 'paused',
                        'bg-slate-100 text-slate-600': exam.status === 'draft' || exam.status === 'completed',
                    }"
                >
                    {{ exam.status === 'active' ? 'Aktif' : exam.status === 'paused' ? 'Dijeda' : exam.status === 'completed' ? 'Selesai' : 'Draf' }}
                </span>

                <h2 class="text-xl font-bold text-slate-900">{{ exam.title }}</h2>
                <p v-if="exam.description" class="mt-1 text-sm text-slate-500">
                    {{ exam.description }}
                </p>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Jadwal Mulai</dt>
                        <dd class="font-medium">{{ formatDate(exam.start_at) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Jadwal Selesai</dt>
                        <dd class="font-medium">{{ formatDate(exam.end_at) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Durasi</dt>
                        <dd class="font-medium">{{ exam.duration_minutes }} menit</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Toleransi Offline</dt>
                        <dd class="font-medium">{{ exam.offline_grace_minutes }} menit</dd>
                    </div>
                </dl>
            </div>

            <!-- Attempt belum ada -->
            <div v-if="!attempt" class="rounded-2xl bg-white p-6 shadow-sm">
                <p v-if="unavailableReason" class="mb-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-700">
                    {{ unavailableReason }}
                </p>
                <p v-else class="mb-4 text-sm text-slate-600">
                    Pastikan perangkat Anda terisi penuh dan koneksi internet stabil sebelum memulai.
                </p>

                <Link
                    v-if="canStart"
                    :href="route('student.exam.run', exam.id)"
                    class="flex h-14 w-full items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white"
                >
                    Mulai Ujian
                </Link>
            </div>

            <!-- Attempt sudah ada -->
            <div v-else class="rounded-2xl bg-white p-6 shadow-sm">
                <p class="mb-4 text-sm text-slate-600">
                    Anda sudah memiliki attempt untuk ujian ini
                    <span
                        class="ml-1 rounded-full px-2 py-0.5 text-xs"
                        :class="attempt.status === 'submitted'
                            ? 'bg-success-100 text-success-700'
                            : attempt.status === 'locked'
                                ? 'bg-warning-100 text-warning-700'
                                : 'bg-brand-100 text-brand-700'"
                    >
                        {{ attempt.status_label }}
                    </span>
                </p>

                <div v-if="attempt.status === 'submitted'" class="text-center">
                    <div class="mb-2 text-4xl font-bold text-success-600">
                        {{ attempt.score ?? '-' }}
                    </div>
                    <p class="mb-4 text-sm text-slate-500">
                        {{ attempt.correct_count }} benar · {{ attempt.wrong_count }} salah ·
                        {{ attempt.unanswered_count }} kosong
                    </p>
                    <Link
                        :href="route('student.history.show', attempt.id)"
                        class="flex h-12 items-center justify-center rounded-xl bg-brand-600 font-semibold text-white"
                    >
                        Lihat Hasil Lengkap
                    </Link>
                </div>

                <Link
                    v-else
                    :href="route('student.exam.run', exam.id)"
                    class="flex h-14 w-full items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white"
                >
                    {{ attempt.status === 'locked' ? 'Buka Halaman Ujian' : 'Lanjutkan Ujian' }}
                </Link>
            </div>
        </div>
    </StudentLayout>
</template>
