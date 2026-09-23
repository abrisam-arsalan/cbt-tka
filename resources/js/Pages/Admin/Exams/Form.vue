<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    edit: Boolean,
    exam: { type: Object, default: null },
    statusOptions: { type: Array, default: () => [] },
    antiCheatActionOptions: { type: Array, default: () => [] },
});

const toLocalInput = (value) => {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const form = useForm({
    title: props.exam?.title ?? '',
    description: props.exam?.description ?? '',
    duration_minutes: props.exam?.duration_minutes ?? 60,
    start_at: props.exam?.start_at ? toLocalInput(props.exam.start_at) : '',
    end_at: props.exam?.end_at ? toLocalInput(props.exam.end_at) : '',
    anti_cheat_enabled: props.exam?.anti_cheat_enabled ?? false,
    anti_cheat_max_warnings: props.exam?.anti_cheat_max_warnings ?? 3,
    anti_cheat_action: props.exam?.anti_cheat_action ?? 'log_only',
    shuffle_questions: props.exam?.shuffle_questions ?? false,
    shuffle_options: props.exam?.shuffle_options ?? false,
    offline_grace_minutes: props.exam?.offline_grace_minutes ?? 10,
});

const submit = () => {
    if (props.edit) {
        form.put(route('admin.exams.update', props.exam.id));
    } else {
        form.post(route('admin.exams.store'));
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-2xl">
            <h2 class="mb-4 text-xl font-bold text-slate-900">{{ title }}</h2>

            <form @submit.prevent="submit" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Judul Ujian</label>
                    <input v-model="form.title" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-danger-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Deskripsi</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Durasi (menit)</label>
                        <input v-model.number="form.duration_minutes" type="number" min="1" max="600" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.duration_minutes" class="mt-1 text-xs text-danger-600">{{ form.errors.duration_minutes }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Grace Period (menit)</label>
                        <input v-model.number="form.offline_grace_minutes" type="number" min="0" max="120" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p class="mt-1 text-xs text-slate-400">Toleransi jawaban offline setelah waktu habis.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Mulai</label>
                        <input v-model="form.start_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Selesai</label>
                        <input v-model="form.end_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="form.errors.end_at" class="mt-1 text-xs text-danger-600">{{ form.errors.end_at }}</p>
                    </div>
                </div>

                <hr class="border-slate-100" />

                <div class="rounded-xl bg-slate-50 p-4">
                    <label class="mb-2 flex items-center gap-2 font-semibold text-slate-800">
                        <input v-model="form.anti_cheat_enabled" type="checkbox" class="h-4 w-4" />
                        Aktifkan Anti-Cheat
                    </label>

                    <div v-if="form.anti_cheat_enabled" class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Tindakan Saat Batas Terlampaui</label>
                            <select v-model="form.anti_cheat_action" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                <option v-for="option in antiCheatActionOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Batas Peringatan</label>
                            <input v-model.number="form.anti_cheat_max_warnings" type="number" min="0" max="10" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.shuffle_questions" type="checkbox" class="h-4 w-4" />
                        Acak urutan soal
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.shuffle_options" type="checkbox" class="h-4 w-4" />
                        Acak urutan opsi
                    </label>
                </div>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Ujian' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
