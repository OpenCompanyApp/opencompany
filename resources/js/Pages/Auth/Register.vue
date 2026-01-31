<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('register'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation')
    },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Create account" />

    <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-6">
      Create an account
    </h1>

    <form @submit.prevent="submit" class="space-y-4">
      <Input
        v-model="form.name"
        type="text"
        label="Name"
        :error="form.errors.name"
        autofocus
        autocomplete="name"
      />

      <Input
        v-model="form.email"
        type="email"
        label="Email"
        :error="form.errors.email"
        autocomplete="username"
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

      <Button
        type="submit"
        full-width
        :loading="form.processing"
      >
        Create account
      </Button>
    </form>

    <template #footer>
      Already have an account?
      <Link :href="route('login')" class="text-neutral-900 dark:text-white hover:underline ml-1">
        Log in
      </Link>
    </template>
  </GuestLayout>
</template>
