<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{
  email: string
  token: string
}>()

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('password.store'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation')
    },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Reset Password" />

    <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-6">
      Set new password
    </h1>

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
        label="New password"
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

      <Button
        type="submit"
        full-width
        :loading="form.processing"
      >
        Reset password
      </Button>
    </form>
  </GuestLayout>
</template>
