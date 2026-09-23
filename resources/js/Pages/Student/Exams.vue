<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

defineProps({
    title: String,
    exams: { type: Array, default: () => [] },
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    });
};
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-slate-900">Ujian Saya</h2>

        <div v-if="exams.length === 0" class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-slate-500">Belum ada ujian.</p>
        </div>

        <div v-else class="space-y-3">
            <div v-for="exam in exams" :key="exam.id" class="rounded-xl bg-white p-4 shadow-sm">
                <div class="mb-2 flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-slate-900">{{ exam.title }}</h3>
                    <span
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-xs"
                        :class="exam.attempt?.status === 'submitted'
                            ? 'bg-success-100 text-success-700'
                            : exam.attempt?.status === 'in_progress'
                                ? 'bg-brand-100 text-brand-700'
                                : 'bg-slate-100 text-slate-600'"
                    >
                        {{ exam.attempt?.status_label || exam.status_label }}
                    </span>
                </div>

                <div class="mb-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                    <div>🗓 {{ formatDate(exam.start_at) }}</div>
                    <div>⏱ {{ exam.duration_minutes }} menit</div>
                    <div>📝 {{ exam.questions_count }} soal</div>
                    <div v-if="exam.attempt?.score !== null && exam.attempt?.score !== undefined">
                        🏆 {{ exam.attempt.score }}
                    </div>
                </div>

                <Link
                    :href="route('student.exams.show', exam.id)"
                    class="flex h-11 items-center justify-center rounded-xl bg-brand-600 text-sm font-semibold text-white"
                >
                    Buka
                </Link>
            </div>
        </div>
    </StudentLayout>
</template>
