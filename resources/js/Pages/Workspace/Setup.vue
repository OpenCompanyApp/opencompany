<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'

const page = usePage()
const isLoggedIn = !!(page.props.auth as any)?.user

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  workspace_name: '',
})

function submit() {
  form.post('/setup')
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

      <form @submit.prevent="submit" class="space-y-4">
        <Input
          v-model="form.name"
          label="Your name"
          :error="form.errors.name"
          autofocus
          autocomplete="name"
        />

        <Input
          v-model="form.email"
          type="email"
          label="Email"
          :error="form.errors.email"
          autocomplete="email"
        />

        <Input
          v-model="form.password"
          type="password"
          label="Password"
          :error="form.errors.password"
          autocomplete="new-password"
        />

        <Input
          v-model="form.password_confirmation"
          type="password"
          label="Confirm password"
          :error="form.errors.password_confirmation"
          autocomplete="new-password"
        />

        <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

        <Input
          v-model="form.workspace_name"
          label="Workspace name"
          :error="form.errors.workspace_name"
          placeholder="e.g., Acme Corp"
        />

        <Button
          type="submit"
          full-width
          :loading="form.processing"
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

      <form @submit.prevent="submit" class="space-y-4">
        <Input
          v-model="form.workspace_name"
          label="Workspace name"
          :error="form.errors.workspace_name"
          placeholder="e.g., Acme Corp"
          autofocus
        />

        <Button
          type="submit"
          full-width
          :loading="form.processing"
          :disabled="!form.workspace_name.trim()"
        >
          Create workspace
        </Button>
      </form>
    </template>
  </GuestLayout>
</template>
