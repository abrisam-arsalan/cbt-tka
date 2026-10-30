<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    exam: Object,
    cards: { type: Array, default: () => [] },
});
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="no-print mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Kartu Ujian</h2>
                <p class="text-sm text-gray-500">{{ exam.title }}</p>
            </div>
            <Link
                :href="route('admin.exams.cards.print', exam.id)"
                class="bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto"
            >
                🖨 Cetak Massal
            </Link>
        </div>

        <div class="preview-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.username + card.student_name"
                class="exam-card"
            >
                <div class="exam-card-head">
                    <img v-if="card.logo_url" :src="card.logo_url" class="exam-card-logo" alt="logo" />
                    <div class="exam-card-school">
                        <p class="exam-card-school-name">{{ card.school_name }}</p>
                        <p class="exam-card-school-sub">Kartu Ujian</p>
                    </div>
                    <span class="exam-card-class">{{ card.class_name }}</span>
                </div>

                <p class="exam-card-name">{{ card.student_name }}</p>
                <p class="exam-card-exam">{{ card.exam_title }} · {{ card.duration_minutes }} mnt · {{ card.start_at }}</p>

                <div class="exam-card-creds">
                    <div class="exam-card-cred">
                        <p class="exam-card-cred-label">Username (NISN)</p>
                        <p class="exam-card-cred-value">{{ card.username || '—' }}</p>
                    </div>
                    <div class="exam-card-cred exam-card-cred-pin">
                        <p class="exam-card-cred-label">PIN Login</p>
                        <p class="exam-card-cred-value">{{ card.login_pin || 'reset PIN' }}</p>
                    </div>
                </div>
            </div>

            <div v-if="cards.length === 0" class="col-span-full rounded-xl bg-white p-8 text-center text-gray-500 shadow-sm">
                Belum ada kartu. Tambahkan peserta terlebih dahulu (menu Peserta).
            </div>
        </div>
    </AdminLayout>
</template>
