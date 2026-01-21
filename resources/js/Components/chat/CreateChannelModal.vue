<template>
  <DialogRoot :open="open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/50 z-50" />
      <DialogContent
        class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-gray-800 rounded-lg shadow-xl z-50 w-full max-w-lg max-h-[80vh] flex flex-col"
        @escape-key-down="emit('update:open', false)"
      >
        <DialogTitle class="text-lg font-semibold text-white p-4 border-b border-gray-700 flex items-center justify-between">
          <span>Create a Channel</span>
          <DialogClose class="text-gray-400 hover:text-white">
            <Icon name="heroicons:x-mark" class="w-5 h-5" />
          </DialogClose>
        </DialogTitle>

        <form class="flex-1 overflow-y-auto p-4 space-y-4" @submit.prevent="handleSubmit">
          <!-- Channel Type -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Channel Type</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="type in channelTypes"
                :key="type.value"
                type="button"
                class="flex flex-col items-center gap-2 p-3 rounded-lg border transition-colors"
                :class="channelType === type.value
                  ? 'bg-indigo-600/20 border-indigo-500 text-white'
                  : 'bg-gray-700/50 border-gray-600 text-gray-300 hover:border-gray-500'"
                @click="channelType = type.value"
              >
                <Icon :name="type.icon" class="w-5 h-5" />
                <span class="text-xs font-medium">{{ type.label }}</span>
              </button>
            </div>
            <p class="mt-2 text-xs text-gray-400">{{ selectedTypeDescription }}</p>
          </div>

          <!-- Channel Name -->
          <div>
            <label for="channel-name" class="block text-sm font-medium text-gray-300 mb-2">
              Channel Name
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <Icon :name="channelTypeIcon" class="w-4 h-4" />
              </span>
              <input
                id="channel-name"
                v-model="channelName"
                type="text"
                placeholder="e.g. marketing, engineering"
                class="w-full bg-gray-700 border border-gray-600 rounded-lg pl-9 pr-4 py-2 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :class="{ 'border-red-500 focus:ring-red-500': nameError }"
              />
            </div>
            <p v-if="nameError" class="mt-1 text-xs text-red-400">{{ nameError }}</p>
            <p v-else class="mt-1 text-xs text-gray-400">
              Names must be lowercase, without spaces. Use hyphens for separation.
            </p>
          </div>

          <!-- Description -->
          <div>
            <label for="channel-description" class="block text-sm font-medium text-gray-300 mb-2">
              Description <span class="text-gray-500">(optional)</span>
            </label>
            <textarea
              id="channel-description"
              v-model="description"
              rows="3"
              placeholder="What's this channel about?"
              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
            />
          </div>

          <!-- Add Members -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
              Add Members <span class="text-gray-500">(optional)</span>
            </label>

            <!-- Search Users -->
            <div class="relative mb-2">
              <Icon name="heroicons:magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                v-model="memberSearch"
                type="text"
                placeholder="Search users to add..."
                class="w-full bg-gray-700 border border-gray-600 rounded-lg pl-9 pr-4 py-2 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <!-- Selected Members -->
            <div v-if="selectedMembers.length > 0" class="flex flex-wrap gap-2 mb-2">
              <span
                v-for="member in selectedMembers"
                :key="member.id"
                class="inline-flex items-center gap-1 px-2 py-1 bg-gray-700 rounded-full text-xs text-white"
              >
                <img
                  v-if="member.avatar"
                  :src="member.avatar"
                  :alt="member.name"
                  class="w-4 h-4 rounded-full"
                />
                <span
                  v-else
                  class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-medium"
                  :class="member.type === 'agent' ? 'bg-purple-600' : 'bg-blue-600'"
                >
                  {{ member.name.charAt(0).toUpperCase() }}
                </span>
                {{ member.name }}
                <button
                  type="button"
                  class="ml-1 text-gray-400 hover:text-white"
                  @click="removeMember(member)"
                >
                  <Icon name="heroicons:x-mark" class="w-3 h-3" />
                </button>
              </span>
            </div>

            <!-- Available Users -->
            <div v-if="memberSearch" class="max-h-32 overflow-y-auto bg-gray-700/50 rounded-lg border border-gray-600">
              <button
                v-for="user in filteredUsers"
                :key="user.id"
                type="button"
                class="w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-600/50 text-left"
                @click="addMember(user)"
              >
                <div class="relative flex-shrink-0">
                  <img
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                    class="w-6 h-6 rounded-full"
                  />
                  <div
                    v-else
                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium"
                    :class="user.type === 'agent' ? 'bg-purple-600' : 'bg-blue-600'"
                  >
                    {{ user.name.charAt(0).toUpperCase() }}
                  </div>
                </div>
                <span class="text-sm text-white">{{ user.name }}</span>
                <span class="text-xs text-gray-400 capitalize">{{ user.type }}</span>
              </button>
              <p v-if="filteredUsers.length === 0" class="px-3 py-2 text-sm text-gray-400 text-center">
                No users found
              </p>
            </div>
          </div>
        </form>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-700 flex items-center justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors"
            @click="emit('update:open', false)"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            :disabled="!isValid || creating"
            @click="handleSubmit"
          >
            <span v-if="creating" class="flex items-center gap-2">
              <Icon name="heroicons:arrow-path" class="w-4 h-4 animate-spin" />
              Creating...
            </span>
            <span v-else>Create Channel</span>
          </button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogClose } from 'reka-ui'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'
import type { User, ChannelType } from '@/types'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'channel-created': [channelId: string]
}>()

const { fetchUsers, createChannel, addChannelMember } = useApi()

// Form state
const channelType = ref<ChannelType>('public')
const channelName = ref('')
const description = ref('')
const memberSearch = ref('')
const selectedMembers = ref<User[]>([])
const creating = ref(false)
const allUsers = ref<User[]>([])

// Channel types
const channelTypes = [
  { value: 'public' as ChannelType, label: 'Public', icon: 'ph:hash', description: 'Anyone in the organization can join and view messages' },
  { value: 'private' as ChannelType, label: 'Private', icon: 'ph:lock-simple', description: 'Only invited members can see and join this channel' },
  { value: 'agent' as ChannelType, label: 'Agent', icon: 'ph:robot', description: 'A dedicated channel for AI agents to collaborate' },
]

// Computed
const selectedTypeDescription = computed(() => {
  return channelTypes.find(t => t.value === channelType.value)?.description ?? ''
})

const channelTypeIcon = computed(() => {
  return channelTypes.find(t => t.value === channelType.value)?.icon ?? 'ph:hash'
})

const nameError = computed(() => {
  if (!channelName.value) return null
  if (channelName.value.includes(' ')) return 'Channel names cannot contain spaces'
  if (channelName.value !== channelName.value.toLowerCase()) return 'Channel names must be lowercase'
  if (!/^[a-z0-9-]+$/.test(channelName.value)) return 'Only letters, numbers, and hyphens allowed'
  if (channelName.value.length < 2) return 'Name must be at least 2 characters'
  if (channelName.value.length > 50) return 'Name must be less than 50 characters'
  return null
})

const isValid = computed(() => {
  return channelName.value.length >= 2 && !nameError.value
})

const filteredUsers = computed(() => {
  if (!memberSearch.value) return []
  const query = memberSearch.value.toLowerCase()
  const selectedIds = new Set(selectedMembers.value.map(m => m.id))
  return allUsers.value
    .filter(u => !selectedIds.has(u.id))
    .filter(u => u.name.toLowerCase().includes(query) || u.type.toLowerCase().includes(query))
    .slice(0, 5)
})

// Fetch users when modal opens
watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    // Reset form
    channelType.value = 'public'
    channelName.value = ''
    description.value = ''
    memberSearch.value = ''
    selectedMembers.value = []

    // Fetch users
    const { data } = await fetchUsers()
    allUsers.value = data.value ?? []
  }
}, { immediate: true })

// Auto-format channel name
watch(channelName, (value) => {
  // Auto-replace spaces with hyphens
  if (value.includes(' ')) {
    channelName.value = value.replace(/\s+/g, '-').toLowerCase()
  }
})

function addMember(user: User) {
  if (!selectedMembers.value.find(m => m.id === user.id)) {
    selectedMembers.value.push(user)
  }
  memberSearch.value = ''
}

function removeMember(user: User) {
  selectedMembers.value = selectedMembers.value.filter(m => m.id !== user.id)
}

async function handleSubmit() {
  if (!isValid.value || creating.value) return

  creating.value = true
  try {
    const result = await createChannel({
      name: channelName.value,
      type: channelType.value,
      description: description.value || undefined,
      creatorId: 'h1', // Current user
      memberIds: ['h1', ...selectedMembers.value.map(m => m.id)],
    })

    emit('channel-created', (result as { id: string }).id)
    emit('update:open', false)
  } catch (error) {
    console.error('Failed to create channel:', error)
  } finally {
    creating.value = false
  }
}
</script>
