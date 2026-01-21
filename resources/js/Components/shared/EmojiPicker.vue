<template>
  <PopoverRoot v-model:open="isOpen">
    <PopoverTrigger as-child>
      <slot />
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        class="w-72 bg-white border border-gray-200 rounded-xl shadow-lg p-3 z-50 animate-in fade-in-0 zoom-in-95 duration-150"
        :side="side"
        :side-offset="8"
        :align="align"
      >
        <!-- Quick Reactions -->
        <div class="flex items-center gap-1 pb-3 border-b border-gray-100">
          <button
            v-for="emoji in quickReactions"
            :key="emoji"
            type="button"
            class="w-8 h-8 flex items-center justify-center text-xl rounded-lg hover:bg-gray-100 transition-colors"
            @click="selectEmoji(emoji)"
          >
            {{ emoji }}
          </button>
        </div>

        <!-- Category Tabs -->
        <div class="flex items-center gap-1 py-2 border-b border-gray-100">
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            :class="[
              'p-1.5 rounded-lg transition-colors text-gray-500',
              activeCategory === cat.id ? 'bg-gray-100 text-gray-900' : 'hover:bg-gray-50'
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
              class="w-8 h-8 flex items-center justify-center text-xl rounded hover:bg-gray-100 transition-colors"
              @click="selectEmoji(emoji)"
            >
              {{ emoji }}
            </button>
          </div>
        </div>

        <PopoverArrow class="fill-white" />
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  PopoverArrow,
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'

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
