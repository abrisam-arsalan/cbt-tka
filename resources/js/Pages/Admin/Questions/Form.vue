<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
    edit: Boolean,
    question: { type: Object, default: null },
    typeOptions: { type: Array, default: () => [] },
});

const form = useForm({
    type: props.question?.type ?? 'pg',
    stimulus: props.question?.stimulus ?? '',
    question_text: props.question?.question_text ?? '',
    media_url: props.question?.media_url ?? '',
    order: props.question?.order ?? 0,
    is_active: props.question?.is_active ?? true,
});

const options = reactive(
    (props.question?.options ?? []).map((o) => ({
        label: o.label,
        option_text: o.option_text,
        is_correct: o.is_correct,
    })),
);

const pairs = reactive(
    (props.question?.matching_pairs ?? []).map((p) => ({
        left_text: p.left_text,
        right_text: p.right_text,
    })),
);

const ensureOptions = () => {
    if (options.length === 0) {
        for (const label of ['A', 'B', 'C', 'D']) {
            options.push({ label, option_text: '', is_correct: false });
        }
    }
};
ensureOptions();

const ensurePairs = () => {
    if (pairs.length === 0) {
        for (let i = 0; i < 4; i++) {
            pairs.push({ left_text: '', right_text: '' });
        }
    }
};
ensurePairs();

const addOption = () => options.push({ label: String.fromCharCode(65 + options.length), option_text: '', is_correct: false });
const removeOption = (index) => options.splice(index, 1);
const addPair = () => pairs.push({ left_text: '', right_text: '' });
const removePair = (index) => pairs.splice(index, 1);

const submit = () => {
    const payload = {
        type: form.type,
        stimulus: form.stimulus,
        question_text: form.question_text,
        media_url: form.media_url,
        order: form.order,
        is_active: form.is_active,
        options: options.map((o) => ({ ...o })),
        matching_pairs: pairs.map((p) => ({ ...p })),
    };

    if (props.edit) {
        form.transform(() => payload).put(route('admin.exams.questions.update', { exam: props.exam.id, question: props.question.id }));
    } else {
        form.transform(() => payload).post(route('admin.exams.questions.store', props.exam.id));
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
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Tipe Soal</label>
                    <select v-model="form.type" :disabled="edit" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Stimulus / Bacaan (opsional)</label>
                    <textarea v-model="form.stimulus" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Pertanyaan</label>
                    <textarea v-model="form.question_text" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                    <p v-if="form.errors.question_text" class="mt-1 text-xs text-danger-600">{{ form.errors.question_text }}</p>
                </div>

                <!-- Opsi untuk PG / PGK / Boolean -->
                <div v-if="form.type === 'pg' || form.type === 'pgk' || form.type === 'boolean'">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-800">Opsi Jawaban</h3>
                        <button v-if="form.type !== 'boolean'" type="button" class="text-sm font-semibold text-brand-600" @click="addOption">+ Opsi</button>
                    </div>

                    <p v-if="form.type === 'pg'" class="mb-2 text-xs text-slate-400">Pilih tepat satu jawaban benar.</p>
                    <p v-else-if="form.type === 'pgk'" class="mb-2 text-xs text-slate-400">Pilih satu atau lebih jawaban benar.</p>
                    <p v-else class="mb-2 text-xs text-slate-400">Opsi Benar dan Salah sudah disiapkan. Pilih yang benar.</p>

                    <div v-for="(option, index) in options" :key="index" class="mb-2 flex items-center gap-2">
                        <span class="w-8 shrink-0 text-center text-sm font-semibold text-slate-500">{{ option.label }}</span>
                        <input
                            v-model="option.option_text"
                            type="text"
                            placeholder="Teks opsi"
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                        <label class="flex shrink-0 items-center gap-1 text-xs text-slate-600">
                            <input
                                v-model="option.is_correct"
                                :type="form.type === 'pgk' ? 'checkbox' : 'radio'"
                                :name="'correct_' + (edit ? props.question.id : 'new')"
                                class="h-4 w-4"
                            />
                            Benar
                        </label>
                        <button v-if="form.type !== 'boolean'" type="button" class="shrink-0 text-danger-500" @click="removeOption(index)">✕</button>
                    </div>
                    <p v-if="form.errors.options" class="mt-1 text-xs text-danger-600">{{ form.errors.options }}</p>
                </div>

                <!-- Pasangan untuk Matching -->
                <div v-if="form.type === 'matching'">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-800">Pasangan (kiri → kanan)</h3>
                        <button type="button" class="text-sm font-semibold text-brand-600" @click="addPair">+ Pasangan</button>
                    </div>

                    <div v-for="(pair, index) in pairs" :key="index" class="mb-2 flex items-center gap-2">
                        <input v-model="pair.left_text" type="text" placeholder="Kiri (premis)" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <span class="text-slate-400">→</span>
                        <input v-model="pair.right_text" type="text" placeholder="Kanan (jawaban)" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <button type="button" class="shrink-0 text-danger-500" @click="removePair(index)">✕</button>
                    </div>
                    <p v-if="form.errors.matching_pairs" class="mt-1 text-xs text-danger-600">{{ form.errors.matching_pairs }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                    Soal aktif (tampil di ujian)
                </label>

                <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Soal' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
