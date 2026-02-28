<template>
  <Popover v-model:open="isOpen">
    <slot />

    <template #content>
      <div class="w-72 p-3">
        <!-- Search -->
        <div class="pb-2">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search emoji..."
            class="w-full px-2.5 py-1.5 text-sm bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg outline-none text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 focus:border-neutral-300 dark:focus:border-neutral-600 transition-colors"
          />
        </div>

        <!-- Quick Reactions (hidden when searching) -->
        <div v-if="!searchQuery" class="flex items-center gap-1 pb-3 border-b border-neutral-100 dark:border-neutral-800">
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

        <!-- Category Tabs (hidden when searching) -->
        <div v-if="!searchQuery" class="flex items-center gap-1 py-2 border-b border-neutral-100 dark:border-neutral-800">
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
          <div v-if="displayedEmojis.length" class="grid grid-cols-8 gap-0.5">
            <button
              v-for="emoji in displayedEmojis"
              :key="emoji"
              type="button"
              class="w-8 h-8 flex items-center justify-center text-xl rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
              @click="selectEmoji(emoji)"
            >
              {{ emoji }}
            </button>
          </div>
          <p v-else class="text-xs text-neutral-400 dark:text-neutral-500 text-center py-4">No emoji found</p>
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
const searchQuery = ref('')

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

// Simple emoji name lookup for search (common emoji keywords)
const emojiNames: Record<string, string> = {
  '😀': 'grinning happy smile', '😃': 'smiley happy', '😄': 'smile happy', '😁': 'grin beam',
  '😅': 'sweat smile awkward', '😂': 'joy laugh crying', '🤣': 'rofl rolling laugh',
  '😊': 'blush happy', '😇': 'innocent angel halo', '🙂': 'slight smile',
  '😍': 'heart eyes love', '🥰': 'love hearts face', '😘': 'kiss blowing',
  '😜': 'wink tongue playful', '🤔': 'thinking hmm', '😐': 'neutral expressionless',
  '😏': 'smirk', '🙄': 'eye roll', '😬': 'grimace', '😮': 'surprised open mouth',
  '😲': 'astonished shocked', '😳': 'flushed embarrassed', '🥺': 'pleading puppy',
  '😢': 'cry sad tear', '😭': 'sob crying loud', '😱': 'scream fear',
  '😤': 'angry huff steam', '😡': 'rage angry red', '😠': 'angry mad',
  '👍': 'thumbs up like yes approve', '👎': 'thumbs down dislike no', '👌': 'ok okay perfect',
  '✌️': 'peace victory', '👋': 'wave hello hi bye', '👊': 'fist bump',
  '❤️': 'heart love red', '🧡': 'orange heart', '💛': 'yellow heart',
  '💚': 'green heart', '💙': 'blue heart', '💜': 'purple heart',
  '🔥': 'fire hot flame lit', '✨': 'sparkles stars', '🎉': 'party tada celebration',
  '💯': 'hundred perfect score', '✅': 'check done yes', '❌': 'cross no wrong',
  '💡': 'idea light bulb', '🎯': 'target bullseye dart', '🏆': 'trophy winner champion',
  '💻': 'laptop computer', '📱': 'phone mobile', '☕': 'coffee cup hot',
  '🍕': 'pizza food', '🍔': 'hamburger burger food', '🐶': 'dog puppy',
  '🐱': 'cat kitten', '🚀': 'rocket launch space ship',
}

const allEmojis = computed(() => Object.values(emojisByCategory).flat())

const searchedEmojis = computed(() => {
  if (!searchQuery.value) return []
  const q = searchQuery.value.toLowerCase()
  return allEmojis.value.filter(emoji => {
    const name = emojiNames[emoji] || ''
    return name.includes(q) || emoji === q
  })
})

const displayedEmojis = computed(() => {
  if (searchQuery.value) return searchedEmojis.value
  return emojisByCategory[activeCategory.value] || []
})

const selectEmoji = (emoji: string) => {
  emit('select', emoji)
  isOpen.value = false
  searchQuery.value = ''
}
</script>
