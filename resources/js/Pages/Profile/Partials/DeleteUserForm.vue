<script setup lang="ts">
import Input from '@/Components/shared/Input.vue'
import Button from '@/Components/shared/Button.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'

const confirmingUserDeletion = ref(false)
const passwordInput = ref<HTMLInputElement | null>(null)

const form = useForm({
  password: '',
})

const confirmUserDeletion = () => {
  confirmingUserDeletion.value = true
  nextTick(() => passwordInput.value?.focus())
}

const deleteUser = () => {
  form.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onError: () => passwordInput.value?.focus(),
    onFinish: () => {
      form.reset()
    },
  })
}

const closeModal = () => {
  confirmingUserDeletion.value = false
  form.clearErrors()
  form.reset()
}
</script>

<template>
  <section>
    <header class="mb-6">
      <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">
        Delete Account
      </h2>
      <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
        Once your account is deleted, all of its resources and data will be
        permanently deleted. Before deleting your account, please download any
        data or information that you wish to retain.
      </p>
    </header>

    <Button variant="danger" @click="confirmUserDeletion">
      Delete Account
    </Button>

    <Modal
      v-model:open="confirmingUserDeletion"
      title="Are you sure you want to delete your account?"
      @close="closeModal"
    >
      <p class="text-sm text-neutral-600 dark:text-neutral-400">
        Once your account is deleted, all of its resources and data will be
        permanently deleted. Please enter your password to confirm you would
        like to permanently delete your account.
      </p>

      <div class="mt-4">
        <Input
          ref="passwordInput"
          v-model="form.password"
          type="password"
          label="Password"
          placeholder="Enter your password"
          :error="form.errors.password"
          @keyup.enter="deleteUser"
        />
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button variant="secondary" @click="closeModal">
            Cancel
          </Button>
          <Button
            variant="danger"
            :loading="form.processing"
            @click="deleteUser"
          >
            Delete Account
          </Button>
        </div>
      </template>
    </Modal>
  </section>
</template>
