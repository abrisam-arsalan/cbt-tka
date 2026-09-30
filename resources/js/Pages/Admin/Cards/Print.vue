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
            <div v-for="card in cards" :key="card.username + card.student_name" class="exam-card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-800">{{ card.school_name }}</p>
                        <p class="text-xs text-slate-500">{{ card.school_city ? card.school_city + ' · ' : '' }}Kartu Ujian</p>
                    </div>
                    <img v-if="card.logo_url" :src="card.logo_url" class="h-10 w-10 object-contain" alt="logo" />
                </div>

                <div class="mb-3">
                    <p class="text-xs text-slate-400">Nama Siswa</p>
                    <p class="font-semibold text-slate-900">{{ card.student_name }}</p>
                    <p class="text-xs text-slate-500">{{ card.class_name }}</p>
                    <p class="text-xs text-slate-500">{{ card.exam_title }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ card.duration_minutes }} menit · {{ card.start_at }}</p>
                </div>

                <!-- Akun login: username (NISN) dan PIN dicetak terpisah;
                     PIN angka, username NISN — keduanya berbeda. Token sesi
                     TIDAK dicetak: diumumkan pengawas, berlaku selama ujian. -->
                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-lg border border-slate-300 px-3 py-2 text-center">
                        <p class="text-xs text-slate-400">Username (NISN)</p>
                        <p class="font-mono text-sm font-bold text-slate-900">{{ card.username || '—' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-300 px-3 py-2 text-center">
                        <p class="text-xs text-slate-400">PIN Login</p>
                        <p class="font-mono text-sm font-bold text-slate-900">{{ card.login_pin || 'reset PIN' }}</p>
                    </div>
                </div>
                <p v-if="card.login_url" class="mt-1 break-all text-center text-[10px] text-slate-400">
                    Login dari HP/PC: {{ card.login_url }}
                </p>
            </div>
        </div>
    </div>
</template>
