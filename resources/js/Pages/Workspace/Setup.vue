<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-950 px-4">
    <div class="w-full max-w-md">
      <!-- Preview icon -->
      <div class="flex justify-center mb-8">
        <WorkspaceIcon
          v-if="step === 'workspace'"
          :icon="workspaceForm.icon"
          :color="workspaceForm.color"
          size="lg"
        />
        <div v-else class="w-12 h-12 rounded-xl bg-neutral-900 dark:bg-white flex items-center justify-center">
          <span class="text-white dark:text-neutral-900 font-bold text-xl">O</span>
        </div>
      </div>

      <div class="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-sm p-6">
        <!-- Step 1: Create Account (if not logged in) -->
        <template v-if="step === 'account'">
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white text-center mb-1">
            Welcome to OpenCompany
          </h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 text-center mb-6">
            Create your admin account to get started.
          </p>

          <form @submit.prevent="handleCreateAccount" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Your name
              </label>
              <input
                v-model="accountForm.name"
                type="text"
                placeholder="Full name"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
                autofocus
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Email
              </label>
              <input
                v-model="accountForm.email"
                type="email"
                placeholder="you@example.com"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Password
              </label>
              <input
                v-model="accountForm.password"
                type="password"
                placeholder="Choose a password"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Confirm password
              </label>
              <input
                v-model="accountForm.password_confirmation"
                type="password"
                placeholder="Confirm password"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              />
            </div>

            <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
              {{ error }}
            </div>

            <button
              type="submit"
              :disabled="!accountForm.name.trim() || !accountForm.email.trim() || !accountForm.password || submitting"
              class="w-full px-4 py-2.5 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Creating...' : 'Continue' }}
            </button>
          </form>
        </template>

        <!-- Step 2: Create Workspace -->
        <template v-else-if="step === 'workspace'">
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white text-center mb-1">
            Create your workspace
          </h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 text-center mb-6">
            Set up your team's workspace to get started.
          </p>

          <form @submit.prevent="handleCreateWorkspace" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Workspace name
              </label>
              <input
                v-model="workspaceForm.name"
                type="text"
                placeholder="e.g., Acme Corp"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
                autofocus
                @input="generateSlug"
              />
            </div>

            <!-- Icon & Color picker -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Icon & color
              </label>
              <div class="space-y-3">
                <!-- Color palette -->
                <div class="flex items-center gap-2">
                  <button
                    v-for="c in colorOptions"
                    :key="c.name"
                    type="button"
                    :class="[
                      'w-6 h-6 rounded-full transition-transform',
                      c.bg,
                      workspaceForm.color === c.name ? 'ring-2 ring-offset-2 ring-offset-white dark:ring-offset-neutral-900 ring-neutral-900 dark:ring-white scale-110' : 'hover:scale-110'
                    ]"
                    @click="workspaceForm.color = c.name"
                  />
                </div>

                <!-- Icon grid -->
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="ic in iconOptions"
                    :key="ic"
                    type="button"
                    :class="[
                      'p-2 rounded-lg transition-colors',
                      workspaceForm.icon === ic
                        ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-900 dark:text-white'
                        : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                    ]"
                    @click="workspaceForm.icon = ic"
                  >
                    <Icon :name="ic" class="w-5 h-5" />
                  </button>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                URL slug
              </label>
              <div class="flex items-center gap-0">
                <span class="px-3 py-2 bg-neutral-100 dark:bg-neutral-800 border border-r-0 border-neutral-200 dark:border-neutral-700 rounded-l-lg text-sm text-neutral-500 dark:text-neutral-400">
                  /w/
                </span>
                <input
                  v-model="workspaceForm.slug"
                  type="text"
                  placeholder="acme-corp"
                  class="flex-1 px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-r-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
                />
              </div>
            </div>

            <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
              {{ error }}
            </div>

            <button
              type="submit"
              :disabled="!workspaceForm.name.trim() || !workspaceForm.slug.trim() || submitting"
              class="w-full px-4 py-2.5 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Creating...' : 'Create Workspace' }}
            </button>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import WorkspaceIcon from '@/Components/shared/WorkspaceIcon.vue'

const page = usePage()
const isLoggedIn = !!(page.props.auth as any)?.user

const step = ref<'account' | 'workspace'>(isLoggedIn ? 'workspace' : 'account')
const submitting = ref(false)
const error = ref('')

const accountForm = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const workspaceForm = ref({
  name: '',
  slug: '',
  icon: 'ph:buildings',
  color: 'blue',
})

const colorOptions = [
  { name: 'neutral', bg: 'bg-neutral-500' },
  { name: 'blue', bg: 'bg-blue-500' },
  { name: 'green', bg: 'bg-green-500' },
  { name: 'yellow', bg: 'bg-yellow-500' },
  { name: 'orange', bg: 'bg-orange-500' },
  { name: 'red', bg: 'bg-red-500' },
  { name: 'purple', bg: 'bg-purple-500' },
  { name: 'pink', bg: 'bg-pink-500' },
]

const iconOptions = [
  'ph:buildings',
  'ph:rocket',
  'ph:lightning',
  'ph:star',
  'ph:diamond',
  'ph:cube',
  'ph:hexagon',
  'ph:globe',
  'ph:atom',
  'ph:crown',
  'ph:shield-check',
  'ph:code',
  'ph:palette',
  'ph:tree',
  'ph:mountains',
  'ph:coffee',
  'ph:fire',
  'ph:sparkle',
  'ph:heart',
  'ph:puzzle-piece',
]

const generateSlug = () => {
  workspaceForm.value.slug = workspaceForm.value.name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

const handleCreateAccount = async () => {
  if (!accountForm.value.name.trim() || !accountForm.value.email.trim() || !accountForm.value.password) return

  submitting.value = true
  error.value = ''

  try {
    const response = await fetch('/register', {
      method: 'POST',
      body: JSON.stringify({
        name: accountForm.value.name.trim(),
        email: accountForm.value.email.trim(),
        password: accountForm.value.password,
        password_confirmation: accountForm.value.password_confirmation,
        _setup: true,
      }),
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || Object.values(data.errors || {}).flat().join(', ') || 'Failed to create account')
    }

    // Account created, move to workspace step
    step.value = 'workspace'
  } catch (e: any) {
    error.value = e?.message || 'Failed to create account'
  } finally {
    submitting.value = false
  }
}

const handleCreateWorkspace = async () => {
  if (!workspaceForm.value.name.trim() || !workspaceForm.value.slug.trim()) return

  submitting.value = true
  error.value = ''

  try {
    const response = await fetch('/api/workspaces', {
      method: 'POST',
      body: JSON.stringify({
        name: workspaceForm.value.name.trim(),
        slug: workspaceForm.value.slug.trim(),
        icon: workspaceForm.value.icon,
        color: workspaceForm.value.color,
      }),
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || Object.values(data.errors || {}).flat().join(', ') || 'Failed to create workspace')
    }

    const data = await response.json()
    router.visit(`/w/${data.slug}`)
  } catch (e: any) {
    error.value = e?.message || 'Failed to create workspace'
  } finally {
    submitting.value = false
  }
}

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}
</script>
