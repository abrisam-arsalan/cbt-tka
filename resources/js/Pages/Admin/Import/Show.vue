<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    typeOptions: { type: Array, default: () => [] },
});

const form = useForm({
    type: 'pg',
    file: null,
});

const submit = () => {
    form.post(route('admin.exams.import.preview', props.exam.id), { forceFormData: true });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-lg">
            <h2 class="mb-1 text-xl font-bold text-slate-900">Impor Soal</h2>
            <p class="mb-4 text-sm text-slate-500">Ujian: {{ exam.title }}</p>

            <form @submit.prevent="submit" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Tipe Soal</label>
                    <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">File (CSV / XLSX)</label>
                    <input type="file" accept=".csv,.xlsx,.xls" required class="w-full text-sm" @input="form.file = $event.target.files[0]" />
                    <p v-if="form.errors.file" class="mt-1 text-xs text-danger-600">{{ form.errors.file }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        Unduh template terlebih dahulu dari menu Template Soal agar format kolom benar.
                    </p>
                </div>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? 'Memproses...' : 'Pratinjau' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
