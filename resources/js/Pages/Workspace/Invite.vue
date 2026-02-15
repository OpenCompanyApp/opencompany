<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-950 px-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="flex justify-center mb-8">
        <div class="w-12 h-12 rounded-xl bg-neutral-900 dark:bg-white flex items-center justify-center">
          <span class="text-white dark:text-neutral-900 font-bold text-xl">O</span>
        </div>
      </div>

      <div class="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-sm p-6">
        <!-- Already accepted -->
        <template v-if="invitation.accepted_at">
          <div class="text-center">
            <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto mb-4">
              <Icon name="ph:check-circle-fill" class="w-6 h-6 text-green-600 dark:text-green-400" />
            </div>
            <h1 class="text-xl font-semibold text-neutral-900 dark:text-white mb-1">
              Invitation already accepted
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-4">
              You're already a member of <strong>{{ invitation.workspace.name }}</strong>.
            </p>
            <Link
              :href="`/w/${invitation.workspace.slug}`"
              class="inline-flex px-4 py-2 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors"
            >
              Go to workspace
            </Link>
          </div>
        </template>

        <!-- Accept invitation -->
        <template v-else>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white text-center mb-1">
            You've been invited
          </h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 text-center mb-6">
            <strong>{{ invitation.inviter.name }}</strong> invited you to join
            <strong>{{ invitation.workspace.name }}</strong> as a {{ invitation.role }}.
          </p>

          <!-- If user needs to create account -->
          <form v-if="needsAccount" @submit.prevent="handleAcceptWithAccount" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Your name
              </label>
              <input
                v-model="form.name"
                type="text"
                placeholder="Full name"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
                autofocus
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Email
              </label>
              <input
                :value="invitation.email"
                type="email"
                disabled
                class="w-full px-3 py-2 bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-500 dark:text-neutral-400 cursor-not-allowed"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Password
              </label>
              <input
                v-model="form.password"
                type="password"
                placeholder="Choose a password"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Confirm password
              </label>
              <input
                v-model="form.password_confirmation"
                type="password"
                placeholder="Confirm password"
                class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              />
            </div>

            <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
              {{ error }}
            </div>

            <button
              type="submit"
              :disabled="!form.name.trim() || !form.password || submitting"
              class="w-full px-4 py-2.5 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Joining...' : 'Create Account & Join' }}
            </button>
          </form>

          <!-- If user is logged in -->
          <div v-else class="space-y-4">
            <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
              {{ error }}
            </div>

            <button
              :disabled="submitting"
              class="w-full px-4 py-2.5 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="handleAccept"
            >
              {{ submitting ? 'Joining...' : 'Accept Invitation' }}
            </button>

            <button
              class="w-full px-4 py-2 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
              @click="handleDecline"
            >
              Decline
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import axios from 'axios'

interface Invitation {
  id: string
  email: string
  role: string
  accepted_at: string | null
  workspace: { id: string; name: string; slug: string }
  inviter: { id: string; name: string }
}

const props = defineProps<{
  invitation: Invitation
  token: string
}>()

const page = usePage()

const needsAccount = !(page.props.auth as any)?.user
const submitting = ref(false)
const error = ref('')

const form = ref({
  name: '',
  password: '',
  password_confirmation: '',
})

const handleAccept = async () => {
  submitting.value = true
  error.value = ''

  try {
    await axios.post(`/api/invitations/${props.token}/accept`)
    router.visit(`/w/${props.invitation.workspace.slug}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Failed to accept invitation'
  } finally {
    submitting.value = false
  }
}

const handleAcceptWithAccount = async () => {
  if (!form.value.name.trim() || !form.value.password) return

  submitting.value = true
  error.value = ''

  try {
    await axios.post(`/api/invitations/${props.token}/accept`, {
      name: form.value.name.trim(),
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })
    router.visit(`/w/${props.invitation.workspace.slug}`)
  } catch (e: any) {
    const data = e?.response?.data
    error.value = data?.message || Object.values(data?.errors || {}).flat().join(', ') || 'Failed to create account'
  } finally {
    submitting.value = false
  }
}

const handleDecline = () => {
  router.visit('/')
}
</script>
