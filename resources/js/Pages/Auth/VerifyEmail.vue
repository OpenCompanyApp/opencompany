<script setup lang="ts">
import { computed } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Button from '@/Components/shared/Button.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps<{
  status?: string
}>()

const form = useForm({})

const submit = () => {
  form.post(route('verification.send'))
}

const verificationLinkSent = computed(
  () => props.status === 'verification-link-sent',
)
</script>

<template>
  <GuestLayout>
    <Head title="Email Verification" />

    <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-2">
      Verify your email
    </h1>

    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
      Thanks for signing up! Before getting started, please verify your email
      address by clicking the link we just sent you.
    </p>

    <div
      v-if="verificationLinkSent"
      class="mb-4 text-sm font-medium text-green-600 dark:text-green-400"
    >
      A new verification link has been sent to your email address.
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <Button
        type="submit"
        full-width
        :loading="form.processing"
      >
        Resend verification email
      </Button>
    </form>

    <template #footer>
      <Link
        :href="route('logout')"
        method="post"
        as="button"
        class="text-neutral-900 dark:text-white hover:underline"
      >
        Log out
      </Link>
    </template>
  </GuestLayout>
</template>
