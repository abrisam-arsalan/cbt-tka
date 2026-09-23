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
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto"
            >
                🖨 Cetak Massal
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.student_name + card.token"
                class="kartu-container overflow-hidden break-inside-avoid rounded-lg border bg-white p-4 shadow-sm"
            >
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-800">{{ card.school_name }}</p>
                        <p class="text-xs text-gray-500">Kartu Ujian</p>
                    </div>
                    <img v-if="card.logo_url" :src="card.logo_url" class="h-10 w-10 object-contain" alt="logo" />
                </div>

                <!-- QR code: ukuran tetap, tidak boleh overflow -->
                <div v-if="card.qr_svg" class="qr-box mx-auto mb-3 h-20 w-20 flex-shrink-0" v-html="card.qr_svg"></div>

                <div class="space-y-1 text-center">
                    <p class="font-semibold text-gray-900">{{ card.student_name }}</p>
                    <p class="text-xs text-gray-500">{{ card.class_name }}</p>
                    <p class="text-xs text-gray-500">{{ card.exam_title }}</p>
                    <p class="text-xs text-gray-400">{{ card.duration_minutes }} menit · {{ card.start_at }}</p>
                </div>

                <div class="mt-2 rounded-lg bg-gray-100 px-3 py-2 text-center">
                    <p class="text-xs text-gray-400">Token Ujian</p>
                    <p class="text-base font-bold tracking-widest text-gray-900">{{ card.token || '—' }}</p>
                </div>
            </div>

            <div v-if="cards.length === 0" class="col-span-full rounded-xl bg-white p-8 text-center text-gray-500 shadow-sm">
                Belum ada kartu. Tambahkan peserta dan generate token terlebih dahulu.
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* Pastikan QR SVG mengikuti ukuran kontainer (w-20 h-20), bukan atribut width/height bawaan. */
.qr-box :deep(svg) {
    width: 100% !important;
    height: 100% !important;
}

@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .kartu-container {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .no-print {
        display: none !important;
    }
}
</style>
