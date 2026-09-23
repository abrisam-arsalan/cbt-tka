<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    type: String,
    type_label: String,
    validRows: { type: Array, default: () => [] },
    errorRows: { type: Array, default: () => [] },
    validCount: Number,
    errorCount: Number,
});

const importing = ref(false);

const execute = () => {
    if (!confirm(`Impor ${props.validCount} soal yang valid ke ujian ini?`)) return;
    importing.value = true;
    router.post(route('admin.exams.import.execute', props.exam.id));
};

const rowText = (row) => row.question_text ?? row.stimulus ?? '(tanpa teks)';
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Pratinjau Impor</h2>
                <p class="text-sm text-slate-500">{{ exam.title }} · {{ type_label }}</p>
            </div>
            <button
                class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="validCount === 0 || importing"
                @click="execute"
            >
                {{ importing ? 'Mengimpor...' : `Impor ${validCount} Soal` }}
            </button>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-success-50 p-4 text-center">
                <div class="text-2xl font-bold text-success-600">{{ validCount }}</div>
                <div class="text-xs text-slate-500">Baris Valid</div>
            </div>
            <div class="rounded-xl bg-danger-50 p-4 text-center">
                <div class="text-2xl font-bold text-danger-600">{{ errorCount }}</div>
                <div class="text-xs text-slate-500">Baris Error</div>
            </div>
        </div>

        <!-- Baris error -->
        <div v-if="errorRows.length > 0" class="mb-4 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="mb-2 font-semibold text-danger-700">Baris yang ditolak</h3>
            <div class="space-y-2">
                <div v-for="err in errorRows" :key="err.row" class="rounded-lg bg-danger-50 p-3 text-sm">
                    <p class="font-medium text-danger-700">Baris {{ err.row }}: {{ rowText(err.raw) }}</p>
                    <ul class="mt-1 list-inside list-disc text-xs text-danger-600">
                        <li v-for="message in err.errors" :key="message">{{ message }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Baris valid -->
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Pertanyaan</th>
                        <th class="px-4 py-3">Kunci</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in validRows" :key="index" class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 text-slate-500">{{ index + 1 }}</td>
                        <td class="max-w-md truncate px-4 py-3">{{ rowText(row) }}</td>
                        <td class="px-4 py-3 font-medium">{{ row.correct ?? '-' }}</td>
                    </tr>
                    <tr v-if="validRows.length === 0">
                        <td colspan="3" class="px-4 py-8 text-center text-slate-500">Tidak ada baris valid.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
