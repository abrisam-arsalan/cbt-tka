<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    stats: Object,
    activeExams: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-slate-900">Dashboard</h2>

        <!-- Statistik -->
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-2xl font-bold text-brand-600">{{ stats.users }}</div>
                <div class="text-xs text-slate-500">Total User</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-2xl font-bold text-brand-600">{{ stats.classes }}</div>
                <div class="text-xs text-slate-500">Kelas</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-2xl font-bold text-brand-600">{{ stats.exams }}</div>
                <div class="text-xs text-slate-500">Ujian</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-2xl font-bold text-success-600">{{ stats.active_exams }}</div>
                <div class="text-xs text-slate-500">Ujian Aktif</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-2xl font-bold text-brand-600">{{ stats.attempts }}</div>
                <div class="text-xs text-slate-500">Attempt</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Ujian aktif -->
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-semibold text-slate-900">Ujian Aktif</h3>
                <div v-if="activeExams.length === 0" class="text-sm text-slate-500">
                    Tidak ada ujian yang sedang aktif.
                </div>
                <div v-else class="space-y-2">
                    <Link
                        v-for="exam in activeExams"
                        :key="exam.id"
                        :href="route('admin.exams.monitoring.index', exam.id)"
                        class="flex items-center justify-between rounded-lg border border-slate-100 p-3 hover:bg-slate-50"
                    >
                        <div>
                            <div class="text-sm font-medium text-slate-800">{{ exam.title }}</div>
                            <div class="text-xs text-slate-400">
                                {{ exam.active_attempts_count }} siswa sedang mengerjakan
                            </div>
                        </div>
                        <span class="text-slate-400">→</span>
                    </Link>
                </div>
            </div>

            <!-- Log terbaru -->
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-semibold text-slate-900">Aktivitas Terbaru</h3>
                <div v-if="recentLogs.length === 0" class="text-sm text-slate-500">
                    Belum ada aktivitas.
                </div>
                <div v-else class="space-y-2">
                    <div v-for="(log, index) in recentLogs" :key="index" class="flex items-start gap-2 text-sm">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-400"></span>
                        <div>
                            <p class="text-slate-700">{{ log.description || log.action }}</p>
                            <p class="text-xs text-slate-400">
                                {{ log.actor }} · {{ formatDate(log.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
