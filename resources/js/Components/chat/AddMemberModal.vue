<template>
  <Modal
    :open="open"
    :title="`Add Members to #${channel?.name}`"
    :ui="{
      width: 'max-w-md',
      background: 'bg-neutral-800',
      header: 'text-white border-b border-neutral-700',
      title: 'text-lg font-semibold text-white',
      close: 'text-neutral-400 hover:text-white',
    }"
    @update:open="emit('update:open', $event)"
  >
        <div class="p-4">
          <div class="relative">
            <Icon name="ph:magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search users..."
              class="w-full bg-neutral-700 border border-neutral-600 rounded-lg pl-9 pr-4 py-2 text-sm text-white placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-4">
          <div v-if="loading" class="flex items-center justify-center py-8">
            <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
          </div>

          <div v-else-if="filteredUsers.length === 0" class="text-center py-8 text-neutral-400">
            <Icon name="ph:users" class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p v-if="searchQuery">No users found matching "{{ searchQuery }}"</p>
            <p v-else>All users are already members of this channel</p>
          </div>

          <div v-else class="space-y-2">
            <button
              v-for="user in filteredUsers"
              :key="user.id"
              class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-neutral-700/50 transition-colors text-left"
              :class="{ 'bg-indigo-600/20 ring-1 ring-indigo-500': selectedUsers.has(user.id) }"
              @click="toggleUser(user)"
            >
              <div class="relative flex-shrink-0">
                <img
                  v-if="user.avatar"
                  :src="user.avatar"
                  :alt="user.name"
                  class="w-10 h-10 rounded-full"
                />
                <div
                  v-else
                  class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium"
                  :class="user.type === 'agent' ? 'bg-purple-600' : 'bg-blue-600'"
                >
                  {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <div
                  v-if="user.type === 'agent'"
                  class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-purple-500 rounded-full flex items-center justify-center"
                >
                  <Icon name="ph:cpu" class="w-2.5 h-2.5 text-white" />
                </div>
              </div>

              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-white truncate">{{ user.name }}</div>
                <div class="text-xs text-neutral-400 capitalize">{{ user.type }}</div>
              </div>

              <div
                v-if="selectedUsers.has(user.id)"
                class="w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center"
              >
                <Icon name="ph:check" class="w-3.5 h-3.5 text-white" />
              </div>
              <div
                v-else
                class="w-5 h-5 border border-neutral-500 rounded-full"
              />
            </button>
          </div>
        </div>

        <div class="p-4 border-t border-neutral-700 flex items-center justify-between">
          <span class="text-sm text-neutral-400">
            {{ selectedUsers.size }} user{{ selectedUsers.size === 1 ? '' : 's' }} selected
          </span>
          <div class="flex gap-2">
            <button
              class="px-4 py-2 text-sm font-medium text-neutral-300 hover:text-white transition-colors"
              @click="emit('update:open', false)"
            >
              Cancel
            </button>
            <button
              class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              :disabled="selectedUsers.size === 0 || adding"
              @click="handleAdd"
            >
              <span v-if="adding" class="flex items-center gap-2">
                <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
                Adding...
              </span>
              <span v-else>Add Members</span>
            </button>
          </div>
        </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'
import type { Channel, User } from '@/types'

const props = defineProps<{
  open: boolean
  channel: Channel | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'members-added': [userIds: string[]]
}>()

const { fetchUsers, addChannelMember } = useApi()

const searchQuery = ref('')
const selectedUsers = ref<Set<string>>(new Set())
const loading = ref(false)
const adding = ref(false)
const allUsers = ref<User[]>([])

// Fetch all users when modal opens
watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    loading.value = true
    selectedUsers.value = new Set()
    searchQuery.value = ''
    try {
      const { data, promise } = fetchUsers()
      await promise
      allUsers.value = (data.value ?? []).filter(u => u.type !== 'agent' || !('isEphemeral' in u && u.isEphemeral))
    } finally {
      loading.value = false
    }
  }
}, { immediate: true })

// Filter out users who are already members
const availableUsers = computed(() => {
  if (!props.channel) return allUsers.value
  const memberIds = new Set(props.channel.members.map(m => m.id))
  return allUsers.value.filter(u => !memberIds.has(u.id))
})

// Filter by search query
const filteredUsers = computed(() => {
  if (!searchQuery.value) return availableUsers.value
  const query = searchQuery.value.toLowerCase()
  return availableUsers.value.filter(u =>
    u.name.toLowerCase().includes(query) ||
    u.type.toLowerCase().includes(query)
  )
})

function toggleUser(user: User) {
  if (selectedUsers.value.has(user.id)) {
    selectedUsers.value.delete(user.id)
  } else {
    selectedUsers.value.add(user.id)
  }
}

async function handleAdd() {
  if (!props.channel || selectedUsers.value.size === 0) return

  adding.value = true
  try {
    const userIds = Array.from(selectedUsers.value)
    await Promise.all(
      userIds.map(userId => addChannelMember(props.channel!.id, userId))
    )
    emit('members-added', userIds)
    emit('update:open', false)
  } catch (error) {
    console.error('Failed to add members:', error)
  } finally {
    adding.value = false
  }
}
</script>
