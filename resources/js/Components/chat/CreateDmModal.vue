<template>
  <Modal
    :open="open"
    title="New Direct Message"
    :ui="{
      width: 'max-w-md',
      background: 'bg-neutral-800',
      header: 'text-white border-b border-neutral-700',
      title: 'text-lg font-semibold text-white',
      close: 'text-neutral-400 hover:text-white',
    }"
    @update:open="emit('update:open', $event)"
  >
    <div class="p-4 space-y-4">
      <!-- Search Users -->
      <div>
        <label class="block text-sm font-medium text-neutral-300 mb-2">Select a person</label>
        <div class="relative">
          <Icon name="ph:magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
          <input
            ref="searchInputRef"
            v-model="search"
            type="text"
            placeholder="Search by name..."
            class="w-full bg-neutral-700 border border-neutral-600 rounded-lg pl-9 pr-4 py-2 text-sm text-white placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
      </div>

      <!-- User List -->
      <div class="max-h-64 overflow-y-auto -mx-4 px-4">
        <div v-if="loading" class="space-y-2 py-2">
          <div v-for="i in 4" :key="i" class="flex items-center gap-3 p-2 animate-pulse">
            <div class="w-8 h-8 rounded-full bg-neutral-700" />
            <div class="flex-1 h-4 rounded bg-neutral-700" />
          </div>
        </div>

        <div v-else-if="filteredUsers.length > 0" class="space-y-0.5">
          <button
            v-for="user in filteredUsers"
            :key="user.id"
            type="button"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors hover:bg-neutral-700/50"
            :disabled="creating"
            @click="startDm(user)"
          >
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium shrink-0"
              :class="user.type === 'agent' ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white'"
            >
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-white truncate">{{ user.name }}</p>
              <p class="text-xs text-neutral-400 capitalize">{{ user.type }}</p>
            </div>
          </button>
        </div>

        <div v-else class="py-8 text-center">
          <p class="text-sm text-neutral-400">
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
