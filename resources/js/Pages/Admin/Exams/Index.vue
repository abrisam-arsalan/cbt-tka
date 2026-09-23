<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    exams: { type: Array, default: () => [] },
});

const btnPrimary = 'bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto';
const btnPrimarySm = 'bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const statusClass = (status) => ({
    draft: 'bg-gray-100 text-gray-600',
    active: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-gray-100 text-gray-500',
}[status] || 'bg-gray-100 text-gray-600');

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const destroy = (exam) => {
    if (!confirm(`Hapus ujian "${exam.title}"? Seluruh soal, peserta, dan attempt ikut terhapus.`)) return;
    router.delete(route('admin.exams.destroy', exam.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold text-gray-900">Manajemen Ujian</h2>
            <Link :href="route('admin.exams.create')" :class="btnPrimary">
                + Buat Ujian
            </Link>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Judul</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Jadwal</th>
                        <th class="px-4 py-3">Soal</th>
                        <th class="px-4 py-3">Peserta</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="exam in exams" :key="exam.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.exams.show', exam.id)" class="font-medium text-blue-700 hover:underline">
                                {{ exam.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(exam.status)">
                                {{ exam.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(exam.start_at) }}</td>
                        <td class="px-4 py-3">{{ exam.questions_count }}</td>
                        <td class="px-4 py-3">{{ exam.participants_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.exams.show', exam.id)" :class="btnPrimarySm">Kelola</Link>
                                <button :class="btnDangerSm" @click="destroy(exam)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="exams.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada ujian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
