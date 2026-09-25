<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    mode: String,
    exams: { type: Array, default: () => [] },
});

// Tindakan dan link target bergantung pada mode hub.
const actionFor = (exam) => {
    switch (props.mode) {
        case 'cards':
            return { label: 'Lihat Kartu', href: route('admin.exams.cards.index', exam.id) };
        case 'participants':
            return { label: 'Kelola Peserta', href: route('admin.exams.participants.index', exam.id) };
        case 'questions':
        default:
            return { label: 'Kelola Soal', href: route('admin.exams.questions.index', exam.id) };
    }
};

const countLabel = () => {
    switch (props.mode) {
        case 'cards':
            return 'peserta';
        case 'participants':
            return 'peserta';
        case 'questions':
        default:
            return 'soal';
    }
};

const statusClass = (status) => ({
    draft: 'bg-gray-100 text-gray-600',
    active: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-gray-100 text-gray-500',
}[status] || 'bg-gray-100 text-gray-600');

const btnPrimarySm = 'bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-gray-900">{{ title }}</h2>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Ujian</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Soal</th>
                        <th class="px-4 py-3">Peserta</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="exam in exams" :key="exam.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.exams.show', exam.id)" class="font-medium text-brand-700 hover:underline">
                                {{ exam.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(exam.status)">
                                {{ exam.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ exam.questions_count }}</td>
                        <td class="px-4 py-3">{{ exam.participants_count }}</td>
                        <td class="px-4 py-3">
                            <Link :href="actionFor(exam).href" :class="btnPrimarySm">{{ actionFor(exam).label }}</Link>
                        </td>
                    </tr>
                    <tr v-if="exams.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            Belum ada ujian. Buat ujian terlebih dahulu.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
