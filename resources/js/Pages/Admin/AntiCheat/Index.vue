<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    logs: Object,
    exams: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    filters: Object,
});

const form = useForm({
    exam_id: props.filters.exam_id,
    type: props.filters.type,
});

const submit = () => form.get(route('admin.anti-cheat.index'), { preserveState: true, replace: true });

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

        <h2 class="mb-4 text-xl font-bold text-slate-900">Log Anti-Cheat</h2>

        <form @submit.prevent="submit" class="mb-4 flex flex-wrap gap-2">
            <select v-model="form.exam_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Ujian</option>
                <option v-for="exam in exams" :key="exam.id" :value="exam.id">{{ exam.title }}</option>
            </select>
            <select v-model="form.type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Jenis</option>
                <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Pesan</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatTime(log.created_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ log.student_name }}</div>
                            <div class="text-xs text-slate-400">{{ log.exam_title }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="typeClass(log.type)">{{ log.type_label }}</span>
                        </td>
                        <td class="max-w-xs truncate px-4 py-3 text-slate-600">{{ log.message }}</td>
                        <td class="px-4 py-3">
                            <Link :href="route('admin.anti-cheat.show', log.attempt_id)" class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Detail</Link>
                        </td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Tidak ada log anti-cheat.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
