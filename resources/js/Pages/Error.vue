<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: Number,
});

const title = computed(() => {
    return ({
        403: 'Akses Ditolak',
        404: 'Halaman Tidak Ditemukan',
        500: 'Kesalahan Server',
        503: 'Layanan Sedang Diperbaiki',
    }[props.status] || 'Kesalahan');
});

const message = computed(() => {
    return ({
        403: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
        404: 'Halaman yang Anda cari tidak tersedia.',
        500: 'Terjadi kesalahan di server. Silakan coba beberapa saat lagi.',
        503: 'Aplikasi sedang dalam pemeliharaan.',
    }[props.status] || 'Terjadi kesalahan yang tidak diketahui.');
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-lg">
            <div class="mb-4 text-6xl">{{ status }}</div>
            <h1 class="mb-2 text-xl font-bold text-slate-900">{{ title }}</h1>
            <p class="mb-6 text-slate-600">{{ message }}</p>
            <a
                :href="route('home')"
                class="inline-block rounded-xl bg-brand-600 px-6 py-3 font-semibold text-white"
            >
                Kembali ke Beranda
            </a>
        </div>
    </div>
</template>