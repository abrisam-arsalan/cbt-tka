<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    logs: Object,
    filters: Object,
    actionOptions: { type: Array, default: () => [] },
});

const form = useForm({
    action: props.filters.action,
    search: props.filters.search,
});

const submit = () => form.get(route('admin.audit-logs.index'), { preserveState: true, replace: true });

const formatTime = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-4 text-xl font-bold text-slate-900">Audit Log Admin</h2>

        <form @submit.prevent="submit" class="mb-4 flex flex-wrap gap-2">
            <select v-model="form.action" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Aksi</option>
                <option v-for="action in actionOptions" :key="action" :value="action">{{ action }}</option>
            </select>
            <input v-model="form.search" type="text" placeholder="Cari deskripsi..." class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            <button type="submit" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Aktor</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Deskripsi</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatTime(log.created_at) }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ log.actor_name }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ log.action }}</span>
                        </td>
                        <td class="max-w-md px-4 py-3 text-slate-600">{{ log.description }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ log.ip_address }}</td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada log.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="logs.links && logs.links.length > 3" class="mt-4 flex justify-center gap-1">
            <Link
                v-for="link in logs.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="link.active ? 'bg-brand-600 text-white' : 'bg-white text-slate-600'"
                class="rounded-lg px-3 py-1.5 text-sm"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
