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
    <div class="flex min-h-screen flex-col bg-slate-100">
        <!-- Header -->
        <header class="safe-top sticky top-0 z-30 border-b border-brand-700 bg-brand-600 text-white shadow">
            <div class="flex items-center justify-between px-4 py-3">
                <h1 class="text-lg font-bold">CBT TKA Sekolah</h1>
                <div class="flex items-center gap-2">
                    <span class="text-sm">{{ user?.name }}</span>
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
        <nav class="safe-bottom fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200 bg-white">
            <div class="flex justify-around">
                <Link
                    v-for="item in navItems"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex flex-1 flex-col items-center gap-0.5 py-2 text-xs transition-colors"
                    :class="[
                        route().current(item.route + '*') || route().current(item.route)
                            ? 'font-semibold text-brand-600'
                            : 'font-normal text-slate-500',
                    ]"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span>{{ item.label }}</span>
                </Link>

                <button
                    @click="logout"
                    class="flex flex-1 flex-col items-center gap-0.5 py-2 text-xs font-normal text-slate-500"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Keluar</span>
                </button>
            </div>
        </nav>
    </div>
</template>