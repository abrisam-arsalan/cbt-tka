<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    rows: { type: Array, default: () => [] },
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-gray-900">Hasil Ujian</h2>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Ujian</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3">Benar / Salah / Kosong</th>
                        <th class="px-4 py-3">Dikumpulkan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ row.name }}</td>
                        <td class="px-4 py-3">{{ row.class_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ row.exam_title }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="(row.score ?? 0) >= 75 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ row.score ?? '-' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ row.correct_count }} / {{ row.wrong_count }} / {{ row.unanswered_count }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(row.submitted_at) }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada hasil ujian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
