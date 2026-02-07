<template>
  <Head title="Profile" />

  <div class="h-full overflow-hidden flex flex-col">
      <div class="max-w-5xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
        <!-- Header -->
        <header class="mb-4 md:mb-6 shrink-0">
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Profile</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
            Manage your account settings and preferences
          </p>
        </header>

        <!-- Mobile Nav -->
        <div class="flex gap-1.5 overflow-x-auto pb-3 -mx-4 px-4 md:hidden shrink-0" style="-ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
          <button
            v-for="section in sections"
            :key="'mobile-' + section.id"
            type="button"
            :class="[
              'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
              activeSection === section.id
                ? section.id === 'danger' ? 'bg-red-600 text-white' : 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : section.id === 'danger' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
            ]"
            @click="activeSection = section.id"
          >
            <Icon :name="section.icon" class="w-3.5 h-3.5" />
            {{ section.name }}
          </button>
        </div>

        <!-- Sidebar + Content -->
        <div class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
          <!-- Desktop Sidebar -->
          <nav class="hidden md:flex w-52 shrink-0 flex-col gap-1">
            <button
              v-for="section in sections.filter(s => s.id !== 'danger')"
              :key="section.id"
              type="button"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
                activeSection === section.id
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
              ]"
              @click="activeSection = section.id"
            >
              <Icon :name="section.icon" class="w-4 h-4" />
              {{ section.name }}
            </button>

            <!-- Divider -->
            <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

            <!-- Danger Zone -->
            <button
              type="button"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
                activeSection === 'danger'
                  ? 'bg-red-600 text-white'
                  : 'text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
              ]"
              @click="activeSection = 'danger'"
            >
              <Icon name="ph:warning" class="w-4 h-4" />
              Danger Zone
            </button>
          </nav>

          <!-- Main Content -->
          <main class="flex-1 min-w-0 overflow-y-auto">
            <!-- Profile Information -->
            <template v-if="activeSection === 'profile'">
              <SettingsSection title="Profile Information" icon="ph:user" description="Update your account's profile information and email address.">
                <form @submit.prevent="submitProfile" class="space-y-4">
                  <SettingsField label="Name" :error="profileForm.errors.name">
                    <input
                      v-model="profileForm.name"
                      type="text"
                      class="settings-input"
                      placeholder="Your name"
                      autocomplete="name"
                    />
                  </SettingsField>

                  <SettingsField label="Email" :error="profileForm.errors.email">
                    <input
                      v-model="profileForm.email"
                      type="email"
                      class="settings-input"
                      placeholder="your@email.com"
                      autocomplete="username"
                    />
                  </SettingsField>

                  <div v-if="mustVerifyEmail && !user.email_verified_at" class="text-sm">
                    <p class="text-neutral-600 dark:text-neutral-400">
                      Your email address is unverified.
                      <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-neutral-900 dark:text-white underline hover:no-underline"
                      >
                        Click here to re-send the verification email.
                      </Link>
                    </p>
                    <p
                      v-show="status === 'verification-link-sent'"
                      class="mt-2 font-medium text-green-600 dark:text-green-400"
                    >
                      A new verification link has been sent to your email address.
                    </p>
                  </div>
                </form>

                <template #actions>
                  <SaveButton :saving="profileForm.processing" :saved="profileForm.recentlySuccessful" @click="submitProfile" />
                </template>
              </SettingsSection>
            </template>

            <!-- Password -->
            <template v-if="activeSection === 'password'">
              <SettingsSection title="Update Password" icon="ph:lock" description="Ensure your account is using a long, random password to stay secure.">
                <form @submit.prevent="submitPassword" class="space-y-4">
                  <SettingsField label="Current Password" :error="passwordForm.errors.current_password">
                    <input
                      ref="currentPasswordInput"
                      v-model="passwordForm.current_password"
                      type="password"
                      class="settings-input"
                      placeholder="Current password"
                      autocomplete="current-password"
                    />
                  </SettingsField>

                  <SettingsField label="New Password" :error="passwordForm.errors.password">
                    <input
                      ref="passwordInput"
                      v-model="passwordForm.password"
                      type="password"
                      class="settings-input"
                      placeholder="New password"
                      autocomplete="new-password"
                    />
                  </SettingsField>

                  <SettingsField label="Confirm Password" :error="passwordForm.errors.password_confirmation">
                    <input
                      v-model="passwordForm.password_confirmation"
                      type="password"
                      class="settings-input"
                      placeholder="Confirm new password"
                      autocomplete="new-password"
                    />
                  </SettingsField>
                </form>

                <template #actions>
                  <SaveButton :saving="passwordForm.processing" :saved="passwordForm.recentlySuccessful" @click="submitPassword" />
                </template>
              </SettingsSection>
            </template>

            <!-- Appearance -->
            <template v-if="activeSection === 'appearance'">
              <SettingsSection title="Appearance" icon="ph:palette" description="Customize how the application looks.">
                <SettingsField label="Theme" description="Select your preferred color scheme">
                  <div class="flex gap-2">
                    <button
                      v-for="option in themeOptions"
                      :key="option.value"
                      type="button"
                      :class="[
                        'flex items-center gap-2 px-4 py-2.5 rounded-xl border text-sm font-medium transition-colors',
                        colorMode === option.value
                          ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 border-neutral-900 dark:border-white'
                          : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600',
                      ]"
                      @click="setColorMode(option.value)"
                    >
                      <Icon :name="option.icon" class="w-4 h-4" />
                      {{ option.label }}
                    </button>
                  </div>
                </SettingsField>
              </SettingsSection>
            </template>

            <!-- Danger Zone -->
            <template v-if="activeSection === 'danger'">
              <SettingsSection title="Danger Zone" icon="ph:warning" variant="danger">
                <div class="space-y-3">
                  <div class="flex items-center justify-between py-2">
                    <div>
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">Delete Account</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                        Permanently delete your account and all associated data. This action cannot be undone.
                      </p>
                    </div>
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150 shrink-0"
                      @click="confirmingDeletion = true"
                    >
                      Delete Account
                    </button>
                  </div>
                </div>
              </SettingsSection>
            </template>
          </main>
        </div>

        <!-- Delete Account Confirmation Modal -->
        <Modal
          v-model:open="confirmingDeletion"
          title="Are you sure you want to delete your account?"
          @close="closeDeletionModal"
        >
          <template #body>
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
              Once your account is deleted, all of its resources and data will be
              permanently deleted. Please enter your password to confirm you would
              like to permanently delete your account.
            </p>

            <div class="mt-4">
              <SettingsField label="Password" :error="deleteForm.errors.password">
                <input
                  ref="deletePasswordInput"
                  v-model="deleteForm.password"
                  type="password"
                  class="settings-input"
                  placeholder="Enter your password"
                  @keyup.enter="deleteAccount"
                />
              </SettingsField>
            </div>
          </template>

          <template #footer>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                @click="closeDeletionModal"
              >
                Cancel
              </button>
              <button
                type="button"
                class="px-3 py-1.5 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700"
                :disabled="deleteForm.processing"
                @click="deleteAccount"
              >
                <span v-if="deleteForm.processing" class="flex items-center gap-1">
                  <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                  Deleting...
                </span>
                <span v-else>Delete Account</span>
              </button>
            </div>
          </template>
        </Modal>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useColorMode } from '@/composables/useColorMode'

type ColorMode = 'light' | 'dark' | 'system'

defineProps<{
  mustVerifyEmail?: boolean
  status?: string
}>()

const user = usePage().props.auth.user as { name: string; email: string; email_verified_at?: string }

// --- Sidebar sections ---
const sections = [
  { id: 'profile', name: 'Profile', icon: 'ph:user' },
  { id: 'password', name: 'Password', icon: 'ph:lock' },
  { id: 'appearance', name: 'Appearance', icon: 'ph:palette' },
  { id: 'danger', name: 'Danger Zone', icon: 'ph:warning' },
]

const activeSection = ref('profile')

// --- Profile Form ---
const profileForm = useForm({
  name: user.name,
  email: user.email,
})

const submitProfile = () => {
  profileForm.patch(route('profile.update'), {
    preserveScroll: true,
  })
}

// --- Password Form ---
const passwordInput = ref<HTMLInputElement | null>(null)
const currentPasswordInput = ref<HTMLInputElement | null>(null)

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const submitPassword = () => {
  passwordForm.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => {
      passwordForm.reset()
    },
    onError: () => {
      if (passwordForm.errors.password) {
        passwordForm.reset('password', 'password_confirmation')
        passwordInput.value?.focus()
      }
      if (passwordForm.errors.current_password) {
        passwordForm.reset('current_password')
        currentPasswordInput.value?.focus()
      }
    },
  })
}

// --- Appearance ---
const { colorMode, setColorMode } = useColorMode()

const themeOptions: Array<{ value: ColorMode; label: string; icon: string }> = [
  { value: 'system', label: 'System', icon: 'ph:monitor' },
  { value: 'light', label: 'Light', icon: 'ph:sun' },
  { value: 'dark', label: 'Dark', icon: 'ph:moon' },
]

// --- Delete Account ---
const confirmingDeletion = ref(false)
const deletePasswordInput = ref<HTMLInputElement | null>(null)

const deleteForm = useForm({
  password: '',
})

const deleteAccount = () => {
  deleteForm.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => closeDeletionModal(),
    onError: () => deletePasswordInput.value?.focus(),
    onFinish: () => deleteForm.reset(),
  })
}

const closeDeletionModal = () => {
  confirmingDeletion.value = false
  deleteForm.clearErrors()
  deleteForm.reset()
}

// Focus password input when modal opens
const openDeletionModal = () => {
  confirmingDeletion.value = true
  nextTick(() => deletePasswordInput.value?.focus())
}

// --- SaveButton component (inline) ---
const SaveButton = {
  props: {
    saving: Boolean,
    saved: Boolean,
  },
  emits: ['click'],
  template: `
    <button
      type="button"
      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors duration-150"
      :class="saved
        ? 'text-green-600 dark:text-green-400'
        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'"
      :disabled="saving"
      @click="$emit('click')"
    >
      <svg v-if="saving" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="opacity-25" /><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
      <svg v-else-if="saved" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
      <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" /><polyline points="17,21 17,13 7,13 7,21" /><polyline points="7,3 7,8 15,8" /></svg>
      {{ saving ? 'Saving...' : saved ? 'Saved' : 'Save' }}
    </button>
  `,
}
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
