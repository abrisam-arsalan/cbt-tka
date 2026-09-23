<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    groups: Object,
    presenceDriver: String,
});

const form = useForm({ settings: [] });

const initSettings = () => {
    const items = [];
    for (const [groupName, settings] of Object.entries(props.groups)) {
        for (const setting of settings) {
            items.push({
                key: setting.key,
                value: setting.type === 'bool' ? !!setting.value : setting.value ?? '',
                type: setting.type,
                label: setting.label,
                group: groupName,
                description: setting.description,
            });
        }
    }
    form.settings = items;
};
initSettings();

const submit = () => {
    form.put(route('admin.settings.update'));
};

const groupLabel = (name) => ({
    general: 'Umum',
    exam: 'Ujian',
    anti_cheat: 'Anti-Cheat',
    card: 'Kartu Ujian',
    appearance: 'Tampilan',
}[name] || name);
</script>

<template>
    <AdminLayout>
        <Head :title="title" />

        <h2 class="mb-1 text-xl font-bold text-slate-900">Pengaturan Sistem</h2>
        <p class="mb-4 text-sm text-slate-500">
            Presence driver aktif: <span class="font-semibold text-brand-700">{{ presenceDriver }}</span>
        </p>

        <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-4">
            <div v-for="(settings, groupName) in groups" :key="groupName" class="rounded-2xl bg-white p-6 shadow-sm">
                <h3 class="mb-4 font-semibold text-slate-800">{{ groupLabel(groupName) }}</h3>

                <div class="space-y-4">
                    <div v-for="setting in settings" :key="setting.key">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">{{ setting.label }}</label>

                        <input
                            v-if="setting.type === 'string'"
                            v-model="form.settings.find((s) => s.key === setting.key).value"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                        />
                        <input
                            v-else-if="setting.type === 'int'"
                            v-model.number="form.settings.find((s) => s.key === setting.key).value"
                            type="number"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                        />
                        <label v-else-if="setting.type === 'bool'" class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                v-model="form.settings.find((s) => s.key === setting.key).value"
                                type="checkbox"
                                class="h-4 w-4"
                            />
                            Aktif
                        </label>

                        <p v-if="setting.description" class="mt-1 text-xs text-slate-400">{{ setting.description }}</p>
                    </div>
                </div>
            </div>

            <button type="submit" :disabled="form.processing" class="h-12 w-full rounded-xl bg-brand-600 font-semibold text-white disabled:opacity-50">
                {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
            </button>
        </form>
    </AdminLayout>
</template>
