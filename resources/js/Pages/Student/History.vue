<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

defineProps({
    title: String,
    attempts: { type: Array, default: () => [] },
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-slate-900">Riwayat Ujian</h2>

        <div v-if="attempts.length === 0" class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-slate-500">Belum ada riwayat ujian.</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="attempt in attempts"
                :key="attempt.id"
                class="rounded-xl bg-white p-4 shadow-sm"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ attempt.exam_title }}</h3>
                        <p class="text-xs text-slate-400">{{ formatDate(attempt.submitted_at || attempt.started_at) }}</p>
                    </div>
                    <span
                        v-if="attempt.score !== null && attempt.score !== undefined"
                        class="shrink-0 rounded-full bg-success-100 px-3 py-1 text-sm font-bold text-success-700"
                    >
                        {{ attempt.score }}
                    </span>
                </div>

                <div class="mb-3 flex flex-wrap gap-2 text-xs text-slate-500">
                    <span class="rounded bg-slate-100 px-2 py-1">{{ attempt.status_label }}</span>
                    <span v-if="attempt.submit_reason_label" class="rounded bg-slate-100 px-2 py-1">
                        {{ attempt.submit_reason_label }}
                    </span>
                </div>

                <Link
                    v-if="attempt.status === 'submitted'"
                    :href="route('student.history.show', attempt.id)"
                    class="flex h-11 items-center justify-center rounded-xl bg-brand-600 text-sm font-semibold text-white"
                >
                    Lihat Hasil
                </Link>
            </div>
        </div>
    </StudentLayout>
</template>
