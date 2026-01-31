<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import Checkbox from '@/Components/shared/Checkbox.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps<{
  canResetPassword?: boolean
  status?: string
}>()

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), {
    onFinish: () => {
      form.reset('password')
    },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Log in" />

    <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-6">
      Welcome back
    </h1>

    <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
      {{ status }}
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <Input
        v-model="form.email"
        type="email"
        label="Email"
        :error="form.errors.email"
        autofocus
        autocomplete="username"
      />

      <Input
        v-model="form.password"
        type="password"
        label="Password"
        :error="form.errors.password"
        autocomplete="current-password"
      />

      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer">
          <Checkbox v-model:checked="form.remember" />
          <span class="text-sm text-neutral-600 dark:text-neutral-400">Remember me</span>
        </label>

        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="text-sm text-neutral-500 hover:text-neutral-900 dark:hover:text-white transition-colors"
        >
          Forgot password?
        </Link>
      </div>

      <Button
        type="submit"
        full-width
        :loading="form.processing"
      >
        Log in
      </Button>
    </form>

    <template #footer>
      Don't have an account?
      <Link :href="route('register')" class="text-neutral-900 dark:text-white hover:underline ml-1">
        Sign up
      </Link>
    </template>
  </GuestLayout>
</template>
