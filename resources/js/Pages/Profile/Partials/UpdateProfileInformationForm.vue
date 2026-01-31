<script setup lang="ts">
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

defineProps<{
  mustVerifyEmail?: boolean
  status?: string
}>()

const user = usePage().props.auth.user

const form = useForm({
  name: user.name,
  email: user.email,
})
</script>

<template>
  <section>
    <header class="mb-6">
      <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">
        Profile Information
      </h2>
      <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
        Update your account's profile information and email address.
      </p>
    </header>

    <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-4">
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

      <div v-if="mustVerifyEmail && user.email_verified_at === null" class="text-sm">
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

      <div class="flex items-center gap-3 pt-2">
        <Button type="submit" :loading="form.processing">
          Save
        </Button>

        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p
            v-if="form.recentlySuccessful"
            class="text-sm text-neutral-500 dark:text-neutral-400"
          >
            Saved.
          </p>
        </Transition>
      </div>
    </form>
  </section>
</template>
