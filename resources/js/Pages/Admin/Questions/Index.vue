<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    questions: { type: Array, default: () => [] },
});

const btnPrimary = 'bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto';
const btnPrimarySm = 'bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const destroy = (question) => {
    if (!confirm('Hapus soal ini?')) return;
    router.delete(route('admin.exams.questions.destroy', { exam: props.exam.id, question: question.id }));
};

const typeClass = (type) => ({
    pg: 'bg-brand-100 text-brand-700',
    pgk: 'bg-purple-100 text-purple-700',
    boolean: 'bg-cyan-100 text-cyan-700',
    matching: 'bg-amber-100 text-amber-700',
}[type] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Bank Soal</h2>
                <p class="text-sm text-gray-500">{{ exam.title }}</p>
            </div>
            <Link :href="route('admin.exams.questions.create', exam.id)" :class="btnPrimary">
                + Tambah Soal
            </Link>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Pertanyaan</th>
                        <th class="px-4 py-3">Kunci</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="question in questions" :key="question.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 text-gray-500">{{ question.order }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="typeClass(question.type)">
                                {{ question.type_label }}
                            </span>
                        </td>
                        <td class="max-w-xs truncate px-4 py-3">{{ question.question_text }}</td>
                        <td class="px-4 py-3">
                            <span :class="question.has_valid_key ? 'text-green-600' : 'text-red-600'">
                                {{ question.has_valid_key ? '✓ Lengkap' : '✗ Belum ada' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="question.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                {{ question.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.exams.questions.edit', { exam: exam.id, question: question.id })" :class="btnPrimarySm">Edit</Link>
                                <button :class="btnDangerSm" @click="destroy(question)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="questions.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada soal.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
