<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    attempt: Object,
    logs: { type: Array, default: () => [] },
});

const typeClass = (type) => ({
    visibility_hidden: 'bg-danger-100 text-danger-700',
    window_blur: 'bg-danger-100 text-danger-700',
    returned: 'bg-brand-100 text-brand-700',
    limit_exceeded: 'bg-warning-100 text-warning-700',
    action_taken: 'bg-purple-100 text-purple-700',
    admin_action: 'bg-slate-100 text-slate-600',
}[type] || 'bg-slate-100 text-slate-600');

const formatTime = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-slate-900">Detail Anti-Cheat</h2>

        <div class="mb-4 rounded-xl bg-white p-4 shadow-sm">
            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><span class="text-slate-500">Siswa:</span> {{ attempt.student_name }}</div>
                <div><span class="text-slate-500">Kelas:</span> {{ attempt.class_name }}</div>
                <div><span class="text-slate-500">Ujian:</span> {{ attempt.exam_title }}</div>
                <div><span class="text-slate-500">Status:</span> {{ attempt.status_label }}</div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div>
                    <span class="text-slate-500">Peringatan:</span>
                    <span class="font-bold" :class="attempt.warnings_count >= attempt.max_warnings ? 'text-danger-600' : 'text-slate-800'">
                        {{ attempt.warnings_count }} / {{ attempt.max_warnings }}
                    </span>
                </div>
                <div><span class="text-slate-500">Tindakan:</span> {{ attempt.anti_cheat_action }}</div>
                <div><span class="text-slate-500">Ditegakkan:</span> {{ attempt.anti_cheat_enforced ? 'Ya' : 'Tidak' }}</div>
                <div v-if="attempt.score !== null"><span class="text-slate-500">Skor:</span> {{ attempt.score }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Pesan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id" class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatTime(log.created_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="typeClass(log.type)">{{ log.type_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ log.message }}</td>
                    </tr>
                    <tr v-if="logs.length === 0">
                        <td colspan="3" class="px-4 py-8 text-center text-slate-500">Tidak ada catatan kejadian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
