<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    templateRoute: { type: String, required: true },
    importRoute: { type: String, required: true },
    columns: { type: Array, default: () => [] },
    note: { type: String, default: '' },
    errors: { type: Array, default: () => [] },
});

const open = ref(props.errors.length > 0);
const fileInput = ref(null);
const fileName = ref('');

const form = useForm({ file: null });

const onFileChange = (event) => {
    const selected = event.target.files?.[0] ?? null;
    form.file = selected;
    fileName.value = selected ? selected.name : '';
};

const submit = () => {
    if (!form.file) {
        alert('Pilih file CSV atau XLSX terlebih dahulu.');
        return;
    }
    form.post(route(props.importRoute), {
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
            fileName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};
</script>

<template>
    <div class="mb-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
        <button
            type="button"
            class="flex w-full items-center justify-between px-5 py-4 text-left"
            @click="open = !open"
        >
            <span class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Tambah Banyak Sekaligus (Impor File)</span>
                    <span class="block text-xs text-slate-500">Unduh template, isi, lalu unggah CSV/XLSX</span>
                </span>
            </span>
            <svg class="h-5 w-5 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-show="open" class="border-t border-slate-100 px-5 pb-5 pt-4">
            <!-- Petunjuk kolom -->
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Kolom template</p>
            <div class="mb-3 flex flex-wrap gap-1.5">
                <span v-for="col in columns" :key="col" class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600">{{ col }}</span>
            </div>
            <p v-if="note" class="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800">{{ note }}</p>

            <!-- Aksi -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <!-- <a> biasa, bukan Inertia Link: unduhan file harus ditangani
                     browser secara native (tanpa intersepsi JS/CSP). -->
                <a
                    :href="route(templateRoute)"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Unduh Template
                </a>

                <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.xlsx,.xls,.txt"
                        class="block w-full cursor-pointer rounded-xl border border-slate-200 text-sm text-slate-600 file:mr-3 file:rounded-l-xl file:border-0 file:bg-brand-50 file:py-3 file:pl-4 file:pr-3 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
                        @change="onFileChange"
                    />
                    <button
                        type="button"
                        :disabled="form.processing || !form.file"
                        class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-50"
                        @click="submit"
                    >
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ form.processing ? 'Mengimpor...' : 'Impor' }}
                    </button>
                </div>
            </div>

            <!-- Detail error -->
            <div v-if="errors.length" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="mb-2 text-sm font-semibold text-red-700">{{ errors.length }} baris tidak dapat diimpor:</p>
                <ul class="max-h-60 space-y-2 overflow-y-auto text-sm">
                    <li v-for="err in errors" :key="err.row" class="rounded-lg bg-white p-2.5 shadow-sm">
                        <span class="font-semibold text-slate-700">Baris {{ err.row }}</span>
                        <span v-if="err.raw?.name || err.raw?.username" class="text-slate-500"> — {{ err.raw.name || err.raw.username }}</span>
                        <ul class="mt-1 list-inside list-disc text-red-600">
                            <li v-for="(msg, i) in err.errors" :key="i">{{ msg }}</li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
