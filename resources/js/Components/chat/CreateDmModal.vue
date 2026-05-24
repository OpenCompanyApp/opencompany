<template>
  <Modal
    :open="open"
    title="New Direct Message"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4">
      <!-- Search Users -->
      <div>
        <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Select a person</label>
        <div class="relative">
          <Icon name="ph:magnifying-glass" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
          <input
            ref="searchInputRef"
            v-model="search"
            type="text"
            placeholder="Search by name..."
            class="w-full rounded-lg border border-neutral-200 bg-white py-2 pl-9 pr-4 text-sm text-neutral-950 outline-none placeholder:text-neutral-400 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:placeholder:text-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-100/10"
          >
        </div>
      </div>

      <!-- User List -->
      <div class="-mx-2 max-h-64 overflow-y-auto px-2">
        <div v-if="loading" class="space-y-2 py-2">
          <div v-for="i in 4" :key="i" class="flex items-center gap-3 p-2 animate-pulse">
            <div class="h-8 w-8 rounded-full bg-neutral-200 dark:bg-neutral-700" />
            <div class="h-4 flex-1 rounded bg-neutral-200 dark:bg-neutral-700" />
          </div>
        </div>

        <div v-else-if="filteredUsers.length > 0" class="space-y-0.5">
          <button
            v-for="user in filteredUsers"
            :key="user.id"
            type="button"
            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
            :disabled="creating"
            @click="startDm(user)"
          >
            <div
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium"
              :class="user.type === 'agent' ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white'"
            >
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ user.name }}</p>
              <p class="text-xs capitalize text-neutral-500 dark:text-neutral-400">{{ user.type }}</p>
            </div>
          </button>
        </div>

        <div v-else class="py-8 text-center">
          <p class="text-sm text-neutral-500 dark:text-neutral-400">
            {{ search ? 'No users found' : 'No users available' }}
          </p>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'
import type { User } from '@/types'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'dm-created': [channelId: string]
}>()

const page = usePage()
const currentUserId = computed(() => (page.props.auth as any)?.user?.id ?? '')
const { fetchUsers, createDirectMessage } = useApi()

const search = ref('')
const allUsers = ref<User[]>([])
const loading = ref(false)
const creating = ref(false)
const searchInputRef = ref<HTMLInputElement>()

const filteredUsers = computed(() => {
  // Exclude current user
  let users = allUsers.value.filter(u => u.id !== currentUserId.value)
  if (search.value) {
    const query = search.value.toLowerCase()
    users = users.filter(u => u.name.toLowerCase().includes(query))
  }
  return users
})

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    search.value = ''
    loading.value = true
    const result = fetchUsers()
    await result.promise
    allUsers.value = result.data.value ?? []
    loading.value = false
    nextTick(() => searchInputRef.value?.focus())
  }
}, { immediate: true })

async function startDm(user: User) {
  if (creating.value) return
  creating.value = true
  try {
    const response = await createDirectMessage(currentUserId.value, user.id)
    const dm = response.data ?? response
    const channelId = dm.channel_id ?? dm.channel?.id ?? dm.id
    emit('dm-created', channelId)
    emit('update:open', false)
  } catch (error) {
    console.error('Failed to create DM:', error)
  } finally {
    creating.value = false
  }
}
</script>
