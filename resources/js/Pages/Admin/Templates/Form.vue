<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    edit: Boolean,
    template: { type: Object, default: null },
    typeOptions: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    question_type: props.template?.question_type ?? 'pg',
    file: null,
});

const submit = () => {
    if (props.edit) {
        form.post(route('admin.templates.update', props.template.id), { forceFormData: true });
    } else {
        form.post(route('admin.templates.store'), { forceFormData: true });
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-lg">
            <h2 class="mb-4 text-xl font-bold text-slate-900">{{ title }}</h2>

            <form @submit.prevent="submit" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Nama Template</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Deskripsi</label>
                    <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Tipe Soal</label>
                    <select v-model="form.question_type" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">File Template (CSV / XLSX)</label>
                    <input type="file" accept=".csv,.xlsx,.xls,.txt" class="w-full text-sm" @input="form.file = $event.target.files[0]" />
                    <p class="mt-1 text-xs text-slate-400">Biarkan kosong bila hanya menyimpan metadata template.</p>
                    <p v-if="form.errors.file" class="mt-1 text-xs text-danger-600">{{ form.errors.file }}</p>
                </div>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
