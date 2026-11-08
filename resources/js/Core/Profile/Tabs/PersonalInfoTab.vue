<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from 'vue-i18n'
import { useProfileStore } from "../Store/profileStore";

const { t } = useI18n()
const store = useProfileStore();
const p = computed(() => store.user?.person);

interface InfoRow {
    icon: string;
    label: string;
    value: string | number | null | undefined;
}

const get = (val: any, fallback = "—") => val ?? fallback;

const personalInfoItems = computed<InfoRow[]>(() => [
    { icon: "fa-user-tag", label: t('profile_tabs.title'), value: get(p.value?.title) },
    { icon: "fa-user", label: t('profile_tabs.first_name'), value: get(p.value?.first_name) },
    { icon: "fa-user", label: t('profile_tabs.surname'), value: get(p.value?.surname) },
    { icon: "fa-id-card", label: t('profile_tabs.known_as'), value: get(p.value?.known_as) },
    { icon: "fa-font", label: t('profile_tabs.initials'), value: get(p.value?.initial) },
    { icon: "fa-id-card", label: t('profile_tabs.id_number'), value: get(p.value?.id_number) },
]);

</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">
                <i class="fa-solid fa-user-circle mr-2" /> {{ t('profile_tabs.personal_information') }}
            </p>
            <div class="space-y-3">
                <div v-for="item in personalInfoItems" :key="item.label" class="flex justify-between items-start gap-3">
                    <span class="text-sm text-slate-500 min-w-[130px] flex items-center gap-2">
                        <i :class="`fa-solid ${item.icon} text-slate-400 w-4 text-center text-sm`" />{{ item.label }}:
                    </span>
                    <span class="text-sm font-medium text-slate-700 text-right flex-1">{{ item.value }}</span>
                </div>
            </div>
        </div>

        
    </div>
</template>
