<template>
  <Modal
    :open="open"
    title="Create a Channel"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-5" @submit.prevent="handleSubmit">
      <div>
        <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Channel Type</label>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="type in channelTypes"
            :key="type.value"
            type="button"
            class="flex flex-col items-center gap-2 rounded-lg border p-3 transition-colors"
            :class="channelType === type.value
              ? 'border-neutral-900 bg-neutral-100 text-neutral-950 dark:border-neutral-100 dark:bg-neutral-800 dark:text-white'
              : 'border-neutral-200 bg-white text-neutral-600 hover:border-neutral-300 hover:text-neutral-950 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:text-white'"
            @click="channelType = type.value"
          >
            <Icon :name="type.icon" class="h-5 w-5" />
            <span class="text-xs font-medium">{{ type.label }}</span>
          </button>
        </div>
        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ selectedTypeDescription }}</p>
      </div>

      <div>
        <label for="channel-name" class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">
          Channel Name
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500">
            <Icon :name="channelTypeIcon" class="h-4 w-4" />
          </span>
          <input
            id="channel-name"
            v-model="channelName"
            type="text"
            placeholder="e.g. marketing, engineering"
            class="w-full rounded-lg border border-neutral-200 bg-white py-2 pl-9 pr-4 text-sm text-neutral-950 outline-none placeholder:text-neutral-400 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:placeholder:text-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-100/10"
            :class="{ 'border-red-500 focus:ring-red-500': nameError }"
          >
        </div>
        <p v-if="nameError" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ nameError }}</p>
        <p v-else class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
          Names must be lowercase, without spaces. Use hyphens for separation.
        </p>
      </div>

      <div>
        <label for="channel-description" class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">
          Description <span class="text-neutral-400 dark:text-neutral-500">(optional)</span>
        </label>
        <textarea
          id="channel-description"
          v-model="description"
          rows="3"
          placeholder="What's this channel about?"
          class="w-full resize-none rounded-lg border border-neutral-200 bg-white px-4 py-2 text-sm text-neutral-950 outline-none placeholder:text-neutral-400 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:placeholder:text-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-100/10"
        />
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">
          Add Members <span class="text-neutral-400 dark:text-neutral-500">(optional)</span>
        </label>

        <div class="relative mb-2">
          <Icon name="ph:magnifying-glass" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
          <input
            v-model="memberSearch"
            type="text"
            placeholder="Search users to add..."
            class="w-full rounded-lg border border-neutral-200 bg-white py-2 pl-9 pr-4 text-sm text-neutral-950 outline-none placeholder:text-neutral-400 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:placeholder:text-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-100/10"
          >
        </div>

        <div v-if="selectedMembers.length > 0" class="mb-2 flex flex-wrap gap-2">
          <span
            v-for="member in selectedMembers"
            :key="member.id"
            class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-1 text-xs text-neutral-800 dark:bg-neutral-800 dark:text-white"
          >
            <img
              v-if="member.avatar"
              :src="member.avatar"
              :alt="member.name"
              class="h-4 w-4 rounded-full"
            >
            <span
              v-else
              class="flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-medium"
              :class="member.type === 'agent' ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white'"
            >
              {{ member.name.charAt(0).toUpperCase() }}
            </span>
            {{ member.name }}
            <button
              type="button"
              class="ml-1 text-neutral-400 hover:text-neutral-900 dark:hover:text-white"
              @click="removeMember(member)"
            >
              <Icon name="ph:x" class="h-3 w-3" />
            </button>
          </span>
        </div>

        <div v-if="memberSearch" class="max-h-32 overflow-y-auto rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
          <button
            v-for="user in filteredUsers"
            :key="user.id"
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-white dark:hover:bg-neutral-800"
            @click="addMember(user)"
          >
            <div class="relative shrink-0">
              <img
                v-if="user.avatar"
                :src="user.avatar"
                :alt="user.name"
                class="h-6 w-6 rounded-full"
              >
              <div
                v-else
                class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium text-white"
                :class="user.type === 'agent' ? 'bg-purple-600' : 'bg-blue-600'"
              >
                {{ user.name.charAt(0).toUpperCase() }}
              </div>
            </div>
            <span class="text-sm text-neutral-900 dark:text-white">{{ user.name }}</span>
            <span class="text-xs capitalize text-neutral-500 dark:text-neutral-400">{{ user.type }}</span>
          </button>
          <p v-if="filteredUsers.length === 0" class="px-3 py-2 text-center text-sm text-neutral-500 dark:text-neutral-400">
            No users found
          </p>
        </div>
      </div>
    </form>

    <div class="mt-5 flex items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-700">
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-neutral-500 transition-colors hover:text-neutral-950 dark:text-neutral-300 dark:hover:text-white"
        @click="emit('update:open', false)"
      >
        Cancel
      </button>
      <button
        type="submit"
        class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200"
        :disabled="!isValid || creating"
        @click="handleSubmit"
      >
        <span v-if="creating" class="flex items-center gap-2">
          <Icon name="ph:spinner" class="h-4 w-4 animate-spin" />
          Creating...
        </span>
        <span v-else>Create Channel</span>
      </button>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'
import type { User, ChannelType } from '@/types'

const page = usePage()
const currentUserId = computed(() => (page.props.auth as any)?.user?.id ?? '')

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
    const result = fetchUsers()
    await result.promise
    allUsers.value = result.data.value ?? []
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
      creatorId: currentUserId.value,
      memberIds: [currentUserId.value, ...selectedMembers.value.map(m => m.id)],
    })

    const channel = 'data' in result ? result.data : result
    emit('channel-created', channel.id)
    emit('update:open', false)
  } catch (error) {
    console.error('Failed to create channel:', error)
  } finally {
    creating.value = false
  }
}
</script>
