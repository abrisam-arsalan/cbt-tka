<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

defineProps({
    title: String,
    exams: { type: Array, default: () => [] },
    activeCount: { type: Number, default: 0 },
    completedCount: { type: Number, default: 0 },
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const statusClass = (exam) => {
    if (exam.attempt_status === 'submitted') return 'bg-success-100 text-success-700';
    if (exam.attempt_status === 'in_progress') return 'bg-brand-100 text-brand-700';
    return {
        draft: 'bg-slate-100 text-slate-600',
        active: 'bg-success-100 text-success-700',
        paused: 'bg-warning-100 text-warning-700',
        completed: 'bg-slate-100 text-slate-600',
    }[exam.status] || 'bg-slate-100 text-slate-600';
};

const primaryAction = (exam) => {
    if (exam.attempt_status === 'submitted') {
        return { label: 'Lihat Hasil', route: 'student.history', icon: null };
    }
    if (exam.attempt_status === 'in_progress' || exam.attempt_status === 'locked') {
        return { label: 'Lanjutkan', route: 'student.exams.show', icon: null };
    }
    if (exam.is_joinable) {
        return { label: 'Ikuti Ujian', route: 'student.exam.join' };
    }
    return null;
};
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <div class="mb-4">
            <h2 class="text-xl font-bold text-slate-900">Dashboard</h2>
            <p class="text-sm text-slate-500">Selamat mengerjakan, semoga sukses.</p>
        </div>

        <!-- Statistik -->
        <div class="mb-6 grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-brand-600">{{ activeCount }}</div>
                <div class="text-xs text-slate-500">Sedang Berjalan</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-success-600">{{ completedCount }}</div>
                <div class="text-xs text-slate-500">Selesai</div>
            </div>
            <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                <div class="text-2xl font-bold text-slate-700">{{ exams.length }}</div>
                <div class="text-xs text-slate-500">Total Ujian</div>
            </div>
        </div>

        <!-- Daftar ujian -->
        <div v-if="exams.length === 0" class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-slate-500">Belum ada ujian yang ditugaskan untuk Anda.</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="exam in exams"
                :key="exam.id"
                class="rounded-xl bg-white p-4 shadow-sm"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-slate-900">{{ exam.title }}</h3>
                    <span
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="statusClass(exam)"
                    >
                        {{ exam.attempt_status_label || exam.status_label }}
                    </span>
                </div>

                <p v-if="exam.description" class="mb-3 line-clamp-2 text-sm text-slate-500">
                    {{ exam.description }}
                </p>

                <div class="mb-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                    <div>🗓 {{ formatDate(exam.start_at) }}</div>
                    <div>⏱ {{ exam.duration_minutes }} menit</div>
                    <div>📝 {{ exam.questions_count }} soal</div>
                    <div v-if="exam.attempt_score !== null">
                        🏆 Nilai: {{ exam.attempt_score }}
                    </div>
                </div>

                <p v-if="!exam.is_joinable && !exam.attempt_status" class="mb-2 text-xs text-warning-700">
                    {{ exam.unavailable_reason }}
                </p>

                <div class="flex gap-2">
                    <Link
                        :href="route('student.exams.show', exam.id)"
                        class="flex h-11 flex-1 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700"
                    >
                        Detail
                    </Link>
                    <Link
                        v-if="primaryAction(exam)"
                        :href="exam.attempt_status === 'submitted'
                            ? route('student.history')
                            : route(primaryAction(exam).route, exam.id)"
                        class="flex h-11 flex-1 items-center justify-center rounded-xl bg-brand-600 text-sm font-semibold text-white"
                    >
                        {{ primaryAction(exam).label }}
                    </Link>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>
