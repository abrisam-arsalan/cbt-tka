<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    rows: { type: Array, default: () => [] },
});

const statusClass = (status) => ({
    in_progress: 'bg-brand-100 text-brand-700',
    locked: 'bg-yellow-100 text-yellow-700',
    expired: 'bg-gray-200 text-gray-600',
    submitted: 'bg-green-100 text-green-700',
}[status] || 'bg-gray-100 text-gray-600');

const formatRemaining = (seconds) => {
    if (seconds == null) return '-';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-gray-900">Monitoring Ujian</h2>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Ujian</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Progress</th>
                        <th class="px-4 py-3">Sisa Waktu</th>
                        <th class="px-4 py-3">⚠</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.attempt_id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ row.name }}</td>
                        <td class="px-4 py-3">{{ row.class_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ row.exam_title }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(row.status)">
                                {{ row.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="w-28">
                                <div class="mb-1 h-1.5 rounded bg-gray-200">
                                    <div class="h-1.5 rounded bg-brand-600" :style="{ width: row.progress_percent + '%' }"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ row.answered_count }}/{{ row.total_questions }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ formatRemaining(row.remaining_seconds) }}</td>
                        <td class="px-4 py-3">
                            <span :class="row.warnings_count > 0 ? 'font-bold text-red-600' : 'text-gray-300'">{{ row.warnings_count }}</span>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            Tidak ada siswa yang sedang mengerjakan ujian.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
