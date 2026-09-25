<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    batches: { type: Array, default: () => [] },
});

const destroy = (batch) => {
    if (!confirm(`Hapus bank "${batch.name}"? Seluruh soal di bank ini ikut terhapus (ujian yang sudah menyalinnya tidak terpengaruh).`)) return;
    router.delete(route('admin.bank.destroy', batch.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Bank Soal</h2>
                <p class="text-sm text-slate-500">Kelompok soal hasil impor yang bisa dipilih ulang untuk ujian.</p>
            </div>
            <Link :href="route('admin.bank.create')" class="flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                Impor Bank Baru
            </Link>
        </div>

        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Mata Pelajaran</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Soal</th>
                        <th class="px-4 py-3">Tanggal Upload</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="batch in batches" :key="batch.id" class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.bank.show', batch.id)" class="font-semibold text-slate-800 hover:text-brand-700">{{ batch.name }}</Link>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ batch.class_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ batch.questions_count }} soal</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ batch.created_at_label }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Link :href="route('admin.bank.show', batch.id)" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">Kelola</Link>
                                <button class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100" @click="destroy(batch)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="batches.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                            Belum ada bank soal. Klik <span class="font-semibold text-brand-700">Impor Bank Baru</span> untuk mulai.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
