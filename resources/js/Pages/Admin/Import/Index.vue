<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    exams: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
});

const btnPrimarySm = 'bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const statusClass = (status) => ({
    draft: 'bg-gray-100 text-gray-600',
    active: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-gray-100 text-gray-500',
}[status] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-1 text-xl font-bold text-gray-900">Impor Soal</h2>
        <p class="mb-4 text-sm text-gray-500">
            Pilih ujian tujuan, lalu unggah file CSV / XLSX. Unduh template format kolom terlebih dahulu dari menu
            <Link :href="route('admin.templates.index')" class="font-medium text-brand-700 hover:underline">Template &amp; Format</Link>.
        </p>

        <div class="mb-4 rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm text-brand-800">
            Tipe soal yang didukung:
            <span v-for="(option, index) in typeOptions" :key="option.value">
                {{ option.label }}<span v-if="index < typeOptions.length - 1">, </span>
            </span>.
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Ujian</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Soal Saat Ini</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="exam in exams" :key="exam.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ exam.title }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(exam.status)">
                                {{ exam.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ exam.questions_count }}</td>
                        <td class="px-4 py-3">
                            <Link :href="route('admin.exams.import.show', exam.id)" :class="btnPrimarySm">Impor Soal</Link>
                        </td>
                    </tr>
                    <tr v-if="exams.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                            Belum ada ujian. Buat ujian terlebih dahulu.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
