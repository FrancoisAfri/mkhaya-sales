<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps<{
    form: any; roles: { id: number; name: string }[]; canEditDirectly: boolean;
    avatarPreview: string | null; dragOver: boolean;
}>();

const emit = defineEmits<{
    (e: "avatar-file", event: Event): void; (e: "drop", event: DragEvent): void;
    (e: "drag-over", value: boolean): void; (e: "click-upload"): void;
}>();


const formattedRoles = computed(() => props.roles.map((role) => ({ title: role.name.charAt(0).toUpperCase() + role.name.slice(1), value: role.name })));
</script>

<template>
    <div class="space-y-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="section-label">{{ t('profile_edit_tabs.personal_information') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <VTextField v-model="form.first_name" :error-messages="form.errors.first_name" :label="t('profile_edit_tabs.first_name')" variant="outlined" density="compact" />
                <VTextField v-model="form.surname" :error-messages="form.errors.surname" :label="t('profile_edit_tabs.surname')" variant="outlined" density="compact" />
                <VTextField v-model="form.initial" :label="t('profile_edit_tabs.initial')" variant="outlined" density="compact" />
                <VTextField v-model="form.known_as" :label="t('profile_edit_tabs.known_as')" variant="outlined" density="compact" />
                <VTextField v-model="form.id_number" :label="t('profile_edit_tabs.id_number')" variant="outlined" density="compact" />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div v-if="canEditDirectly" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <p class="section-label">{{ t('profile_edit_tabs.account_settings') }}</p>
                <div class="space-y-4">
                    <VSelect v-model="form.role" :items="formattedRoles" item-title="title" item-value="value" :label="t('profile_edit_tabs.role')" variant="outlined" density="compact" />
                    <VSwitch v-model="form.status" :true-value="1" :false-value="0" :label="form.status === 1 ? t('profile_edit_tabs.active') : t('profile_edit_tabs.inactive')" color="success" hide-details inset />
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <p class="section-label">{{ t('profile_edit_tabs.profile_picture') }}</p>
                <div class="dropzone" :class="{ 'dropzone--over': dragOver, 'dropzone--filled': !!avatarPreview }" @click="emit('click-upload')" @dragover.prevent="emit('drag-over', true)" @dragleave.prevent="emit('drag-over', false)" @drop.prevent="emit('drop', $event)">
                    <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-32 object-cover rounded-xl" alt="Profile" />
                    <div v-else class="flex flex-col items-center gap-2 py-6"><i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-300" /><p class="text-xs text-red-500 mt-1">{{ t('profile_edit_tabs.profile_picture_required') }}</p></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.section-label { font-size: 11px; font-weight: 700; letter-spacing: 0.6px; color: #94a3b8; text-transform: uppercase; margin: 0 0 16px; }
.dropzone { border: 2px dashed #cbd5e1; border-radius: 12px; cursor: pointer; overflow: hidden; transition: border-color 0.2s, background 0.2s; background: repeating-linear-gradient(-45deg, #f8fafc, #f8fafc 10px, #f1f5f9 10px, #f1f5f9 20px); }
.dropzone:hover, .dropzone--over { border-color: #6366f1; background: #eef2ff; }
.dropzone--filled { border-style: solid; border-color: #e2e8f0; background: #f8fafc; }
</style>
