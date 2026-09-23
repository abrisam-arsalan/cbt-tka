<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    title: String,
    templates: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
});

const btnPrimary = 'bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition duration-150 flex items-center justify-center text-center w-full sm:w-auto';
const btnPrimarySm = 'bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';
const btnDangerSm = 'bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded-md shadow-sm transition duration-150 flex items-center justify-center text-center whitespace-nowrap';

const destroy = (template) => {
    if (!confirm(`Hapus template "${template.name}"?`)) return;
    router.delete(route('admin.templates.destroy', template.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold text-gray-900">Template Soal</h2>
            <Link :href="route('admin.templates.create')" :class="btnPrimary">
                + Unggah Template
            </Link>
        </div>

        <!-- Unduh template bawaan -->
        <div class="mb-4 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="mb-2 font-semibold text-gray-800">Unduh Template Bawaan (CSV)</h3>
            <div class="flex flex-wrap gap-2">
                <a
                    v-for="option in typeOptions"
                    :key="option.value"
                    :href="route('admin.templates.download', option.value)"
                    class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100"
                >
                    ⬇ {{ option.label }}
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="template in templates" :key="template.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ template.name }}</td>
                        <td class="px-4 py-3">{{ template.question_type_label }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="template.is_builtin ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                                {{ template.is_builtin ? 'Bawaan' : 'Custom' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="template.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                {{ template.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.templates.edit', template.id)" :class="btnPrimarySm">Edit</Link>
                                <button v-if="!template.is_builtin" :class="btnDangerSm" @click="destroy(template)">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="templates.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada template.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
