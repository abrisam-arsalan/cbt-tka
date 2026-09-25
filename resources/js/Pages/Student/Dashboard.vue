<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudentLayout from '@/Layouts/StudentLayout.vue';

const props = defineProps({
    title: String,
    exams: { type: Array, default: () => [] },
    activeCount: { type: Number, default: 0 },
    completedCount: { type: Number, default: 0 },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const firstName = computed(() => (user.value?.name || 'Siswa').trim().split(/\s+/)[0]);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 18) return 'Selamat sore';
    return 'Selamat malam';
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const statusClass = (exam) => {
    if (exam.attempt_status === 'submitted') return 'bg-emerald-100 text-emerald-700';
    if (exam.attempt_status === 'in_progress') return 'bg-brand-100 text-brand-700';
    return {
        draft: 'bg-slate-100 text-slate-600',
        active: 'bg-emerald-100 text-emerald-700',
        paused: 'bg-amber-100 text-amber-700',
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

        <!-- Greeting -->
        <div class="mb-5">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ greeting }}, {{ firstName }}! 👋</h2>
            <p class="mt-1 text-sm text-slate-500">Semoga sukses mengerjakan ujian hari ini.</p>
        </div>

        <!-- Statistik -->
        <div class="mb-6 grid grid-cols-3 gap-3">
            <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100">
                <div class="text-2xl font-bold text-brand-600">{{ activeCount }}</div>
                <div class="text-xs text-slate-500">Berjalan</div>
            </div>
            <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100">
                <div class="text-2xl font-bold text-emerald-600">{{ completedCount }}</div>
                <div class="text-xs text-slate-500">Selesai</div>
            </div>
            <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100">
                <div class="text-2xl font-bold text-slate-800">{{ exams.length }}</div>
                <div class="text-xs text-slate-500">Total</div>
            </div>
        </div>

        <!-- Daftar ujian -->
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">Ujian Saya</h3>

        <div v-if="exams.length === 0" class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-100">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <p class="text-sm text-slate-500">Belum ada ujian yang ditugaskan untuk Anda.</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="exam in exams"
                :key="exam.id"
                class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100"
            >
                <div class="p-4">
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
                        <div class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            {{ formatDate(exam.start_at) }}
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ exam.duration_minutes }} menit
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                            {{ exam.questions_count }} soal
                        </div>
                        <div v-if="exam.attempt_score !== null" class="flex items-center gap-1.5 font-semibold text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0" /></svg>
                            Nilai: {{ exam.attempt_score }}
                        </div>
                    </div>

                    <p v-if="!exam.is_joinable && !exam.attempt_status" class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        {{ exam.unavailable_reason }}
                    </p>
                </div>

                <div class="flex gap-2 border-t border-slate-100 p-3">
                    <Link
                        :href="route('student.exams.show', exam.id)"
                        class="flex h-11 flex-1 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                    >
                        Detail
                    </Link>
                    <Link
                        v-if="primaryAction(exam)"
                        :href="exam.attempt_status === 'submitted'
                            ? route('student.history')
                            : route(primaryAction(exam).route, exam.id)"
                        class="flex h-11 flex-1 items-center justify-center rounded-xl bg-brand-600 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                    >
                        {{ primaryAction(exam).label }}
                    </Link>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>
