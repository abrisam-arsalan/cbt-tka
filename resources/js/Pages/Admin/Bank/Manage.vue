<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    batch: Object,
    questions: { type: Array, default: () => [] },
});

const typeTone = {
    pg: 'bg-brand-100 text-brand-700',
    pgk: 'bg-violet-100 text-violet-700',
    boolean: 'bg-emerald-100 text-emerald-700',
    matching: 'bg-amber-100 text-amber-700',
};

const destroyQuestion = (question) => {
    if (!confirm(`Hapus soal #${question.order}?`)) return;
    router.delete(route('admin.bank.questions.destroy', { batch: props.batch.id, question: question.id }));
};

const destroyBatch = () => {
    if (!confirm(`Hapus seluruh bank "${props.batch.name}"? Ujian yang sudah menyalin isinya tidak terpengaruh.`)) return;
    router.delete(route('admin.bank.destroy', props.batch.id), { onSuccess: () => router.visit(route('admin.bank.index')) });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4">
            <Link :href="route('admin.bank.index')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Semua Bank Soal
            </Link>
        </div>

        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ batch.name }}</h2>
                <p class="text-sm text-slate-500">
                    {{ batch.class_name ? `Kelas ${batch.class_name} · ` : '' }}{{ questions.length }} soal · diunggah {{ batch.created_at_label }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('admin.bank.questions.create', batch.id)" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                    + Tambah Soal
                </Link>
                <button class="inline-flex h-11 items-center justify-center rounded-xl bg-red-50 px-4 text-sm font-semibold text-red-700 transition hover:bg-red-100" @click="destroyBatch">Hapus Bank</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Pertanyaan</th>
                        <th class="px-4 py-3">Kunci</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="question in questions" :key="question.id" class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 text-slate-400">{{ question.order }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="typeTone[question.type]">{{ question.type_label }}</span>
                        </td>
                        <td class="max-w-md px-4 py-3">
                            <p class="line-clamp-2 text-slate-700">{{ question.question_text }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="question.has_valid_key" class="text-xs font-semibold text-emerald-600">✓ Lengkap</span>
                            <span v-else class="text-xs font-semibold text-red-600">✗ Belum ada</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="question.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ question.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Link :href="route('admin.bank.questions.edit', { batch: batch.id, question: question.id })" class="rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-100">Ubah</Link>
                                <button class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100" @click="destroyQuestion(question)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="questions.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada soal di bank ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
