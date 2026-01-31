<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
  password: '',
})

const submit = () => {
  form.post(route('password.confirm'), {
    onFinish: () => {
      form.reset()
    },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Confirm Password" />

    <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-2">
      Confirm password
    </h1>

    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
      This is a secure area. Please confirm your password before continuing.
    </p>

    <form @submit.prevent="submit" class="space-y-4">
      <Input
        v-model="form.password"
        type="password"
        label="Password"
        :error="form.errors.password"
        autofocus
        autocomplete="current-password"
      />

      <Button
        type="submit"
        full-width
        :loading="form.processing"
      >
        Confirm
      </Button>
    </form>
  </GuestLayout>
</template>
