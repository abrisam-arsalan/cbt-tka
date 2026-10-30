<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

defineProps({
    title: String,
    exam: Object,
    cards: { type: Array, default: () => [] },
});

onMounted(() => {
    // Otomatis buka dialog cetak browser.
    setTimeout(() => window.print(), 400);
});
</script>

<template>
    <div class="min-h-screen bg-white p-4">
        <Head :title="title" />

        <div class="no-print mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Cetak Kartu — {{ exam.title }}</h2>
            <div class="flex gap-2">
                <button class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white" @click="window.print()">
                    🖨 Cetak
                </button>
                <Link :href="route('admin.exams.cards.index', exam.id)" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">
                    Kembali
                </Link>
            </div>
        </div>

        <div class="print-grid grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div v-for="card in cards" :key="card.username + card.student_name" class="exam-card">
                <!-- Kepala kartu: pita brand -->
                <div class="exam-card-head">
                    <img v-if="card.logo_url" :src="card.logo_url" class="exam-card-logo" alt="logo" />
                    <div class="exam-card-school">
                        <p class="exam-card-school-name">{{ card.school_name }}</p>
                        <p class="exam-card-school-sub">Kartu Ujian</p>
                    </div>
                    <span class="exam-card-class">{{ card.class_name }}</span>
                </div>

                <!-- Identitas siswa -->
                <p class="exam-card-name">{{ card.student_name }}</p>
                <p class="exam-card-exam">{{ card.exam_title }} · {{ card.duration_minutes }} mnt · {{ card.start_at }}</p>

                <!-- Akun login: username (NISN) dan PIN dicetak terpisah;
                     PIN angka, username NISN — keduanya berbeda. Token sesi
                     TIDAK dicetak: diumumkan pengawas, berlaku selama ujian. -->
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
        </div>
    </div>
</template>
