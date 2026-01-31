<script setup lang="ts">
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const passwordInput = ref<HTMLInputElement | null>(null)
const currentPasswordInput = ref<HTMLInputElement | null>(null)

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const updatePassword = () => {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
    },
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation')
        passwordInput.value?.focus()
      }
      if (form.errors.current_password) {
        form.reset('current_password')
        currentPasswordInput.value?.focus()
      }
    },
  })
}
</script>

<template>
  <section>
    <header class="mb-6">
      <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">
        Update Password
      </h2>
      <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
        Ensure your account is using a long, random password to stay secure.
      </p>
    </header>

    <form @submit.prevent="updatePassword" class="space-y-4">
      <Input
        ref="currentPasswordInput"
        v-model="form.current_password"
        type="password"
        label="Current password"
        :error="form.errors.current_password"
        autocomplete="current-password"
      />

      <Input
        ref="passwordInput"
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
