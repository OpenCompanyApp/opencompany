<template>
  <aside class="w-60 bg-olympus-bg border-r border-olympus-border flex flex-col shrink-0">
    <!-- Search -->
    <div class="p-3">
      <SharedSearchInput
        v-model="searchQuery"
        placeholder="Search channels..."
        size="sm"
        clearable
      />
    </div>

    <div class="flex-1 overflow-y-auto px-3 pb-3">
      <!-- Loading State -->
      <template v-if="loading">
        <div class="mb-4">
          <SharedSkeleton custom-class="h-3 w-24 mb-3 ml-2" />
          <div class="space-y-1">
            <div v-for="i in 3" :key="`agent-${i}`" class="flex items-center gap-2 px-2 py-2">
              <SharedSkeleton variant="avatar" custom-class="w-6 h-6" />
              <SharedSkeleton custom-class="h-3 w-28" />
            </div>
          </div>
        </div>
        <div>
          <SharedSkeleton custom-class="h-3 w-20 mb-3 ml-2" />
          <div class="space-y-1">
            <div v-for="i in 4" :key="`pub-${i}`" class="flex items-center gap-2 px-2 py-2">
              <SharedSkeleton custom-class="w-5 h-5" rounded="md" />
              <SharedSkeleton custom-class="h-3 w-24" />
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <!-- Agent Channels -->
        <div v-if="agentChannels.length > 0" class="mb-4">
          <SharedSectionHeader
            title="Agent Channels"
            :count="agentChannels.length"
          />
          <div class="space-y-0.5">
            <ChatChannelItem
              v-for="channel in agentChannels"
              :key="channel.id"
              :channel="channel"
              :selected="selectedChannel.id === channel.id"
              @click="$emit('select', channel)"
            />
          </div>
        </div>

        <!-- Public Channels -->
        <div v-if="publicChannels.length > 0">
          <SharedSectionHeader
            title="Channels"
            :count="publicChannels.length"
            :action="{ icon: 'ph:plus', label: 'Add channel', onClick: () => $emit('create') }"
          />
          <div class="space-y-0.5">
            <ChatChannelItem
              v-for="channel in publicChannels"
              :key="channel.id"
              :channel="channel"
              :selected="selectedChannel.id === channel.id"
              @click="$emit('select', channel)"
            />
          </div>
        </div>

        <!-- No Results -->
        <SharedEmptyState
          v-if="searchQuery && publicChannels.length === 0 && agentChannels.length === 0"
          icon="ph:magnifying-glass"
          title="No channels found"
          :description="`No results for '${searchQuery}'`"
          size="sm"
        />
      </template>
    </div>
  </aside>
</template>

<script setup lang="ts">
import type { Channel } from '~/types'

const props = withDefaults(defineProps<{
  channels: Channel[]
  selectedChannel: Channel
  loading?: boolean
}>(), {
  loading: false,
})

defineEmits<{
  select: [channel: Channel]
  create: []
}>()

const searchQuery = ref('')

const publicChannels = computed(() =>
  props.channels
    .filter(c => c.type === 'public')
    .filter(c => c.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
)

const agentChannels = computed(() =>
  props.channels
    .filter(c => c.type === 'agent')
    .filter(c => c.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
)
</script>
