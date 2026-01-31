<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/shared/Card.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import { Head } from '@inertiajs/vue3'

defineProps<{
  mustVerifyEmail?: boolean
  status?: string
}>()
</script>

<template>
  <AppLayout>
    <Head title="Profile" />

    <div class="max-w-2xl mx-auto space-y-6">
      <Card>
        <UpdateProfileInformationForm
          :must-verify-email="mustVerifyEmail"
          :status="status"
        />
      </Card>

      <Card>
        <UpdatePasswordForm />
      </Card>

      <Card>
        <DeleteUserForm />
      </Card>
    </div>
  </AppLayout>
</template>
