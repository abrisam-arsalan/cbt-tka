<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    edit: Boolean,
    class: { type: Object, default: null },
});

const form = useForm({
    name: props.class?.name ?? '',
    grade: props.class?.grade ?? '',
    academic_year: props.class?.academic_year ?? '',
    description: props.class?.description ?? '',
    is_active: props.class?.is_active ?? true,
});

const submit = () => {
    if (props.edit) {
        form.put(route('admin.classes.update', props.class.id));
    } else {
        form.post(route('admin.classes.store'));
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
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Nama Rombel</label>
                    <input v-model="form.name" type="text" required placeholder="Contoh: 7A, 8B, 9C" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p class="mt-1 text-xs text-slate-400">Rombel = rombongan belajar per kelas, mis. 7A untuk jenjang 7.</p>
                    <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Jenjang</label>
                        <select v-model="form.grade" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="">— pilih jenjang —</option>
                            <option value="7">7 (Satu SMP)</option>
                            <option value="8">8 (Dua SMP)</option>
                            <option value="9">9 (Tiga SMP)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Tahun Ajaran</label>
                        <input v-model="form.academic_year" type="text" placeholder="2026/2027" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Deskripsi (opsional)</label>
                    <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                    Kelas aktif
                </label>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
