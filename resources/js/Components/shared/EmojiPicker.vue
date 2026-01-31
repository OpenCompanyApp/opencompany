<template>
  <Popover v-model:open="isOpen">
    <slot />

    <template #content>
      <div class="w-72 p-3">
        <!-- Quick Reactions -->
        <div class="flex items-center gap-1 pb-3 border-b border-neutral-100 dark:border-neutral-800">
          <button
            v-for="emoji in quickReactions"
            :key="emoji"
            type="button"
            class="w-8 h-8 flex items-center justify-center text-xl rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="selectEmoji(emoji)"
          >
            {{ emoji }}
          </button>
        </div>

        <!-- Category Tabs -->
        <div class="flex items-center gap-1 py-2 border-b border-neutral-100 dark:border-neutral-800">
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            :class="[
              'p-1.5 rounded-lg transition-colors text-neutral-500 dark:text-neutral-300',
              activeCategory === cat.id ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'hover:bg-neutral-50 dark:hover:bg-neutral-800'
            ]"
            :title="cat.name"
            @click="activeCategory = cat.id"
          >
            <span class="text-base">{{ cat.icon }}</span>
          </button>
        </div>

        <!-- Emoji Grid -->
        <div class="py-2 max-h-48 overflow-y-auto">
          <div class="grid grid-cols-8 gap-0.5">
            <button
              v-for="emoji in currentEmojis"
              :key="emoji"
              type="button"
              class="w-8 h-8 flex items-center justify-center text-xl rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
              @click="selectEmoji(emoji)"
            >
              {{ emoji }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </Popover>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Popover from '@/Components/shared/Popover.vue'

withDefaults(defineProps<{
  side?: 'top' | 'right' | 'bottom' | 'left'
  align?: 'start' | 'center' | 'end'
}>(), {
  side: 'top',
  align: 'start',
})

const emit = defineEmits<{
  select: [emoji: string]
}>()

const isOpen = ref(false)
const activeCategory = ref('smileys')

const quickReactions = ['👍', '❤️', '😂', '😮', '😢', '🔥']

const categories = [
  { id: 'smileys', name: 'Smileys & People', icon: '😀' },
  { id: 'nature', name: 'Animals & Nature', icon: '🐱' },
  { id: 'food', name: 'Food & Drink', icon: '🍕' },
  { id: 'activities', name: 'Activities', icon: '⚽' },
  { id: 'travel', name: 'Travel & Places', icon: '✈️' },
  { id: 'objects', name: 'Objects', icon: '💡' },
  { id: 'symbols', name: 'Symbols', icon: '❤️' },
]

const emojisByCategory: Record<string, string[]> = {
  smileys: [
    '😀', '😃', '😄', '😁', '😅', '😂', '🤣', '😊',
    '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘',
    '😗', '😙', '😚', '😋', '😛', '😜', '🤪', '😝',
    '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐',
    '😑', '😶', '😏', '😒', '🙄', '😬', '😮', '😯',
    '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥',
    '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩',
    '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿',
  ],
  nature: [
    '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼',
    '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🐔',
    '🐧', '🐦', '🐤', '🦆', '🦅', '🦉', '🦇', '🐺',
    '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐞',
    '🌸', '💐', '🌷', '🌹', '🥀', '🌺', '🌻', '🌼',
    '🌿', '🍀', '🍁', '🍂', '🍃', '🌲', '🌳', '🌴',
  ],
  food: [
    '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓',
    '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝',
    '🍅', '🥑', '🥦', '🥬', '🥒', '🌶️', '🌽', '🥕',
    '🍕', '🍔', '🍟', '🌭', '🥪', '🌮', '🌯', '🥗',
    '🍜', '🍲', '🍣', '🍱', '🍰', '🎂', '🍩', '🍪',
    '☕', '🍵', '🥤', '🍺', '🍷', '🥂', '🍾', '🧃',
  ],
  activities: [
    '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉',
    '🥏', '🎱', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏',
    '🎯', '🎮', '🎲', '🧩', '🎭', '🎨', '🎬', '🎤',
    '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕',
    '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🎗️', '🎪',
  ],
  travel: [
    '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑',
    '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🏍️', '🛵',
    '🚲', '🛴', '🚨', '🚔', '🚍', '🚘', '🚖', '✈️',
    '🛫', '🛬', '🛩️', '🚀', '🛸', '🚁', '🛶', '⛵',
    '🚤', '🛥️', '🛳️', '⛴️', '🚢', '🗼', '🏰', '🏯',
  ],
  objects: [
    '💡', '🔦', '🏮', '📱', '💻', '🖥️', '🖨️', '⌨️',
    '🖱️', '🖲️', '💽', '💾', '💿', '📀', '📼', '📷',
    '📸', '📹', '🎥', '📞', '☎️', '📠', '📺', '📻',
    '🎙️', '🎚️', '🎛️', '⏱️', '⏲️', '⏰', '🕰️', '⌛',
    '📡', '🔋', '🔌', '💵', '💴', '💶', '💷', '💰',
  ],
  symbols: [
    '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
    '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
    '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️',
    '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈',
    '✅', '❌', '❓', '❔', '❕', '❗', '⭕', '🔴',
    '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤',
  ],
}

const currentEmojis = computed(() => {
  return emojisByCategory[activeCategory.value] || []
})

const selectEmoji = (emoji: string) => {
  emit('select', emoji)
  isOpen.value = false
}
</script>
