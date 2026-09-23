<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    users: Object,
    filters: Object,
    roleOptions: { type: Array, default: () => [] },
});

const btnPrimary = 'bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto';
const btnPrimarySm = 'bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const filterForm = useForm({
    role: props.filters.role,
    search: props.filters.search,
});

const submitFilter = () => {
    filterForm.get(route('admin.users.index'), { preserveState: true, replace: true });
};

const destroy = (user) => {
    if (!confirm(`Hapus user "${user.name}"? Tindakan ini tidak bisa dibatalkan.`)) return;
    router.delete(route('admin.users.destroy', user.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold text-gray-900">Manajemen User</h2>
            <Link :href="route('admin.users.create')" :class="btnPrimary">
                + Tambah User
            </Link>
        </div>

        <!-- Filter -->
        <form @submit.prevent="submitFilter" class="mb-4 flex flex-wrap gap-2">
            <select v-model="filterForm.role" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <input
                v-model="filterForm.search"
                type="text"
                placeholder="Cari nama / username..."
                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
            />
            <button type="submit" :class="btnPrimarySm">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ user.username }}</td>
                        <td class="px-4 py-3">{{ user.name }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="user.role === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ user.role_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ user.class_name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.users.edit', user.id)" :class="btnPrimarySm">Edit</Link>
                                <button :class="btnDangerSm" @click="destroy(user)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada user.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.links && users.links.length > 3" class="mt-4 flex justify-center gap-1">
            <Link
                v-for="link in users.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'"
                class="rounded-lg px-3 py-1.5 text-sm"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
