<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    classes: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    importErrors: { type: Array, default: () => [] },
});

const fileInput = ref(null);
const fileName = ref('');

const form = useForm({
    subject_id: '',
    subject_code: '',
    tingkat: '',
    file: null,
});

// Kode mapel mengikuti pilihan dropdown (huruf+angka, maks 8).
const selectedSubject = computed(() =>
    props.subjects.find((s) => String(s.id) === String(form.subject_id)) ?? null,
);

const onSubjectChange = () => {
    const s = selectedSubject.value;
    form.subject_code = s ? String(s.kode ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8) : '';
};

const onFileChange = (event) => {
    const selected = event.target.files?.[0] ?? null;
    form.file = selected;
    fileName.value = selected ? selected.name : '';
};

const submit = () => {
    if (!form.subject_id) {
        alert('Pilih mata pelajaran dari portal terlebih dahulu.');
        return;
    }
    if (!form.tingkat) {
        alert('Pilih kelas (7, 8, atau 9) terlebih dahulu.');
        return;
    }
    if (!form.file) {
        alert('Pilih file CSV, XLSX, atau ZIP terlebih dahulu.');
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
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Mata Pelajaran</label>
                        <select
                            v-model="form.subject_id"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                            @change="onSubjectChange"
                        >
                            <option value="" disabled>— pilih dari portal —</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">
                                {{ s.nama }}{{ s.kode ? ` (${s.kode})` : '' }}
                            </option>
                        </select>
                        <p v-if="!subjects.length" class="mt-1 text-xs text-amber-600">
                            Daftar mapel portal tidak tersedia (portal mati atau token belum diset) — hubungi admin sistem.
                        </p>
                        <p v-if="form.errors.subject_id" class="mt-1 text-xs text-danger-600">{{ form.errors.subject_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Kelas</label>
                        <select
                            v-model="form.tingkat"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                        >
                            <option value="" disabled>— pilih kelas —</option>
                            <option value="7">Kelas 7</option>
                            <option value="8">Kelas 8</option>
                            <option value="9">Kelas 9</option>
                        </select>
                        <p v-if="form.errors.tingkat" class="mt-1 text-xs text-danger-600">{{ form.errors.tingkat }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">File CSV / XLSX / ZIP</label>
                        <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls,.txt,.zip" class="block w-full cursor-pointer rounded-xl border border-slate-200 text-sm text-slate-600 file:mr-3 file:rounded-l-xl file:border-0 file:bg-brand-50 file:py-3 file:pl-4 file:pr-3 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100" @change="onFileChange" />
                        <p class="mt-1 text-xs text-slate-400">
                            Untuk soal bergambar: isikan nama file (mis. <span class="font-mono">peta.png</span>) di kolom
                            <span class="font-mono">media_url</span> dan unggah sebagai <strong>ZIP</strong> berisi template +
                            gambar-gambarnya (maks 20 MB). URL gambar http(s) juga diterima.
                        </p>
                        <p v-if="form.errors.file" class="mt-1 text-xs text-danger-600">{{ form.errors.file }}</p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a :href="route('admin.bank.template')" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Unduh Template
                        </a>
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
