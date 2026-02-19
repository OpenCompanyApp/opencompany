<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'

const page = usePage()
const isLoggedIn = !!(page.props.auth as any)?.user

const submitting = ref(false)
const error = ref('')

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  workspace_name: '',
})

function slugify(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function createWorkspace(name: string) {
  const slug = slugify(name)
  const response = await fetch('/api/workspaces', {
    method: 'POST',
    body: JSON.stringify({
      name: name.trim(),
      slug,
      icon: 'ph:buildings',
      color: 'blue',
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
}

async function handleSetup() {
  submitting.value = true
  error.value = ''

  try {
    if (!isLoggedIn) {
      const response = await fetch('/register', {
        method: 'POST',
        body: JSON.stringify({
          name: form.value.name.trim(),
          email: form.value.email.trim(),
          password: form.value.password,
          password_confirmation: form.value.password_confirmation,
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
    }

    await createWorkspace(form.value.workspace_name)
  } catch (e: any) {
    error.value = e?.message || 'Something went wrong'
  } finally {
    submitting.value = false
  }
}

async function handleWorkspaceOnly() {
  submitting.value = true
  error.value = ''

  try {
    await createWorkspace(form.value.workspace_name)
  } catch (e: any) {
    error.value = e?.message || 'Failed to create workspace'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <GuestLayout>
    <Head title="Setup" />

    <!-- Full setup: account + workspace -->
    <template v-if="!isLoggedIn">
      <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-1">
        Welcome to OpenCompany
      </h1>
      <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">
        Create your admin account and workspace.
      </p>

      <form @submit.prevent="handleSetup" class="space-y-4">
        <Input
          v-model="form.name"
          label="Your name"
          :error="''"
          autofocus
          autocomplete="name"
        />

        <Input
          v-model="form.email"
          type="email"
          label="Email"
          :error="''"
          autocomplete="email"
        />

        <Input
          v-model="form.password"
          type="password"
          label="Password"
          :error="''"
          autocomplete="new-password"
        />

        <Input
          v-model="form.password_confirmation"
          type="password"
          label="Confirm password"
          :error="''"
          autocomplete="new-password"
        />

        <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

        <Input
          v-model="form.workspace_name"
          label="Workspace name"
          :error="''"
          placeholder="e.g., Acme Corp"
        />

        <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
          {{ error }}
        </div>

        <Button
          type="submit"
          full-width
          :loading="submitting"
          :disabled="!form.name.trim() || !form.email.trim() || !form.password || !form.workspace_name.trim()"
        >
          Get started
        </Button>
      </form>
    </template>

    <!-- Workspace only (already logged in, no workspace) -->
    <template v-else>
      <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-1">
        Create your workspace
      </h1>
      <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">
        Name your workspace to get started.
      </p>

      <form @submit.prevent="handleWorkspaceOnly" class="space-y-4">
        <Input
          v-model="form.workspace_name"
          label="Workspace name"
          :error="''"
          placeholder="e.g., Acme Corp"
          autofocus
        />

        <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
          {{ error }}
        </div>

        <Button
          type="submit"
          full-width
          :loading="submitting"
          :disabled="!form.workspace_name.trim()"
        >
          Create workspace
        </Button>
      </form>
    </template>
  </GuestLayout>
</template>
