<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps<{
  status?: string
}>()

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'))
}
</script>

<template>
  <GuestLayout>
    <Head title="Forgot Password" />

    <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-2">
      Forgot password?
    </h1>

    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
      No problem. Enter your email and we'll send you a reset link.
    </p>

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

      <Button
        type="submit"
        full-width
        :loading="form.processing"
      >
        Send reset link
      </Button>
    </form>

    <template #footer>
      <Link :href="route('login')" class="text-neutral-900 dark:text-white hover:underline">
        Back to login
      </Link>
    </template>
  </GuestLayout>
</template>
