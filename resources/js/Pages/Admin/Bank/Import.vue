<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    classes: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    importErrors: { type: Array, default: () => [] },
});

const fileInput = ref(null);
const fileName = ref('');

const form = useForm({
    name: '',
    class_id: '',
    file: null,
});

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
    form.post(route('admin.bank.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4">
            <Link :href="route('admin.bank.index')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Kembali ke Bank Soal
            </Link>
        </div>

        <div class="mx-auto max-w-2xl">
            <h2 class="text-xl font-bold text-slate-900">Impor Bank Soal</h2>
            <p class="mt-1 text-sm text-slate-500">Satu file boleh berisi SEMUA jenis soal. Kolom <span class="font-mono">jenis</span> menentukan tipe tiap baris.</p>

            <div class="mt-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                <!-- Kolom template -->
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Kolom template</p>
                <div class="mb-4 flex flex-wrap gap-1.5">
                    <span v-for="col in columns" :key="col" class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600">{{ col }}</span>
                </div>
                <p class="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800">
                    Nilai <span class="font-mono">jenis</span>: <span class="font-mono">pg</span>, <span class="font-mono">pgk</span>, <span class="font-mono">boolean</span>, <span class="font-mono">matching</span>. Kolom <span class="font-mono">kelas</span> diisi nama kelas yang sudah ada (opsional).
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Mata Pelajaran</label>
                        <input v-model="form.name" type="text" required placeholder="mis. Matematika" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" />
                        <p class="mt-1 text-xs text-slate-400">Tanggal otomatis ditambahkan di belakang nama (mis. "Matematika 2609").</p>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Kelas <span class="font-normal text-slate-400">(opsional)</span></label>
                        <select v-model="form.class_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                            <option value="">— tanpa kelas / campuran —</option>
                            <option v-for="c in classes" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">File CSV / XLSX</label>
                        <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls,.txt" class="block w-full cursor-pointer rounded-xl border border-slate-200 text-sm text-slate-600 file:mr-3 file:rounded-l-xl file:border-0 file:bg-brand-50 file:py-3 file:pl-4 file:pr-3 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100" @change="onFileChange" />
                        <p v-if="form.errors.file" class="mt-1 text-xs text-danger-600">{{ form.errors.file }}</p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Link :href="route('admin.bank.template')" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Unduh Template
                        </Link>
                        <button type="submit" :disabled="form.processing || !form.file" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-50">
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ form.processing ? 'Mengimpor...' : 'Impor Sekarang' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Detail error -->
            <div v-if="importErrors.length" class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4">
                <p class="mb-2 text-sm font-semibold text-red-700">{{ importErrors.length }} baris tidak dapat diimpor:</p>
                <ul class="max-h-72 space-y-2 overflow-y-auto text-sm">
                    <li v-for="err in importErrors" :key="err.row" class="rounded-lg bg-white p-2.5 shadow-sm">
                        <span class="font-semibold text-slate-700">Baris {{ err.row }}</span>
                        <span v-if="err.raw?.question_text" class="text-slate-500"> — {{ err.raw.question_text }}</span>
                        <ul class="mt-1 list-inside list-disc text-red-600">
                            <li v-for="(msg, i) in err.errors" :key="i">{{ msg }}</li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>
