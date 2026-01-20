<template>
  <div class="h-full flex">
    <!-- Channel List Sidebar -->
    <ChatChannelList
      :channels="channels"
      :selected-channel="selectedChannel"
      @select="selectedChannel = $event"
    />

    <!-- Main Chat Area -->
    <ChatArea
      :channel="selectedChannel"
      :messages="channelMessages"
      class="flex-1"
    />

    <!-- Channel Info Sidebar -->
    <ChatChannelInfo
      :channel="selectedChannel"
      :viewers="channelViewers"
    />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const { channels, messages, humans, agents } = useMockData()

const selectedChannel = ref(channels[2]) // Start with #client-bloom-beauty

// Handle channel query param
watch(() => route.query.channel, (channelId) => {
  if (channelId) {
    const found = channels.find(c => c.id === channelId)
    if (found) selectedChannel.value = found
  }
}, { immediate: true })

const channelMessages = computed(() =>
  messages.filter(m => m.channelId === selectedChannel.value.id)
)

// Mock viewers for presence
const channelViewers = computed(() => [
  humans[0],
  agents[0],
])
</script>
