<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const navItems = [
    { label: 'Beranda', route: 'student.dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1' },
    { label: 'Ujian', route: 'student.exams.index', icon: 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
    { label: 'Riwayat', route: 'student.history', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-slate-50">
        <!-- Header -->
        <header class="safe-top sticky top-0 z-30 bg-gradient-to-r from-brand-500 to-orange-600 text-white shadow-md shadow-brand-500/20">
            <div class="flex items-center justify-between gap-2 px-4 py-3.5">
                <div class="flex shrink-0 items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white p-0.5 shadow-sm">
                        <img src="/images/logo.png" alt="Logo" class="h-full w-full object-contain" />
                    </span>
                    <h1 class="text-base font-bold tracking-tight">Panglima CBT</h1>
                </div>
                <div class="flex min-w-0 items-center justify-end gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-xs font-bold">
                        {{ (user?.name || '?').trim().slice(0, 1).toUpperCase() }}
                    </span>
                    <span class="truncate text-sm font-medium">{{ user?.name }}</span>
                </div>
            </div>
        </header>

        <!-- Flash -->
        <div v-if="flash?.success || flash?.error || flash?.warning" class="px-4 pt-3">
            <div v-if="flash?.success" class="rounded-lg bg-success-100 px-4 py-2 text-sm text-success-700">{{ flash.success }}</div>
            <div v-if="flash?.error" class="rounded-lg bg-danger-100 px-4 py-2 text-sm text-danger-700">{{ flash.error }}</div>
            <div v-if="flash?.warning" class="rounded-lg bg-warning-100 px-4 py-2 text-sm text-warning-700">{{ flash.warning }}</div>
        </div>

        <!-- Content -->
        <main class="flex-1 pb-20">
            <div class="mx-auto max-w-4xl px-4 py-4">
                <slot />
            </div>
        </main>

        <!-- Bottom Navigation -->
        <nav class="safe-bottom fixed bottom-0 left-0 right-0 z-30 border-t border-slate-100 bg-white/95 backdrop-blur-md shadow-[0_-2px_12px_rgba(0,0,0,0.04)]">
            <div class="flex justify-around">
                <Link
                    v-for="item in navItems"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex flex-1 flex-col items-center gap-1 py-2.5 text-[11px] transition-colors"
                    :class="[
                        route().current(item.route + '*') || route().current(item.route)
                            ? 'font-semibold text-brand-600'
                            : 'font-medium text-slate-400',
                    ]"
                >
                    <span
                        class="flex h-8 w-12 items-center justify-center rounded-full transition-colors"
                        :class="route().current(item.route + '*') || route().current(item.route) ? 'bg-brand-50' : ''"
                    >
                        <svg class="h-6 w-6" fill="none" :stroke="route().current(item.route + '*') || route().current(item.route) ? 'currentColor' : 'currentColor'" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                    </span>
                    <span>{{ item.label }}</span>
                </Link>

                <button
                    @click="logout"
                    class="flex flex-1 flex-col items-center gap-1 py-2.5 text-[11px] font-medium text-slate-400 transition-colors hover:text-danger-600"
                >
                    <span class="flex h-8 w-12 items-center justify-center rounded-full">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </span>
                    <span>Keluar</span>
                </button>
            </div>
        </nav>
    </div>
</template>