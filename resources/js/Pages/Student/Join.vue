<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

const props = defineProps({
    title: String,
    exam: Object,
});

const form = useForm({
    token: '',
});

const submit = () => form.post(route('student.exam.join.attempt', props.exam.id));
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <div class="mx-auto max-w-md">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-slate-900">{{ exam.title }}</h2>
                <p class="text-sm text-slate-500">Masukkan token sesi yang diumumkan pengawas di depan ruang ujian.</p>
            </div>

            <form
                @submit.prevent="submit"
                class="rounded-2xl bg-white p-6 shadow-sm"
            >
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Token Sesi
                </label>
                <input
                    v-model="form.token"
                    type="text"
                    inputmode="latin"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    required
                    placeholder="XXXX-XXXX"
                    class="mb-1 block w-full rounded-xl border border-slate-300 px-4 py-4 text-center text-2xl font-bold tracking-widest uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                    :class="{ 'border-danger-500': form.errors.token }"
                />
                <p class="mb-4 text-center text-xs text-slate-400">
                    Contoh format: ABCD-1234
                </p>
                <p v-if="form.errors.token" class="mb-3 text-sm text-danger-600">
                    {{ form.errors.token }}
                </p>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex h-12 w-full items-center justify-center rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Memeriksa...' : 'Gabung Ujian' }}
                </button>
            </form>

            <div class="mt-4 rounded-xl bg-brand-50 p-4 text-xs text-brand-800">
                <p class="mb-1 font-semibold">Petunjuk:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    <li>Token sesi sama untuk semua peserta dan berganti otomatis tiap 30 menit.</li>
                    <li>Minta token terbaru ke pengawas bila token di layar sudah kedaluwarsa.</li>
                    <li>Tanda hubung (-) boleh diabaikan.</li>
                </ul>
            </div>
        </div>
    </StudentLayout>
</template>
