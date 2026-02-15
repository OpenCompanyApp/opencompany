<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-950 px-4">
    <div class="w-full max-w-md">
      <!-- Preview icon -->
      <div class="flex justify-center mb-8">
        <WorkspaceIcon :icon="form.icon" :color="form.color" size="lg" />
      </div>

      <div class="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-sm p-6">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white text-center mb-1">
          Create your workspace
        </h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 text-center mb-6">
          Set up your team's workspace to get started.
        </p>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              Workspace name
            </label>
            <input
              v-model="form.name"
              type="text"
              placeholder="e.g., Acme Corp"
              class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              autofocus
              @input="generateSlug"
            />
          </div>

          <!-- Icon & Color picker -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Icon & color
            </label>
            <div class="space-y-3">
              <!-- Color palette -->
              <div class="flex items-center gap-2">
                <button
                  v-for="c in colorOptions"
                  :key="c.name"
                  type="button"
                  :class="[
                    'w-6 h-6 rounded-full transition-transform',
                    c.bg,
                    form.color === c.name ? 'ring-2 ring-offset-2 ring-offset-white dark:ring-offset-neutral-900 ring-neutral-900 dark:ring-white scale-110' : 'hover:scale-110'
                  ]"
                  @click="form.color = c.name"
                />
              </div>

              <!-- Icon grid -->
              <div class="flex flex-wrap gap-1">
                <button
                  v-for="ic in iconOptions"
                  :key="ic"
                  type="button"
                  :class="[
                    'p-2 rounded-lg transition-colors',
                    form.icon === ic
                      ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-900 dark:text-white'
                      : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                  ]"
                  @click="form.icon = ic"
                >
                  <Icon :name="ic" class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
              URL slug
            </label>
            <div class="flex items-center gap-0">
              <span class="px-3 py-2 bg-neutral-100 dark:bg-neutral-800 border border-r-0 border-neutral-200 dark:border-neutral-700 rounded-l-lg text-sm text-neutral-500 dark:text-neutral-400">
                /w/
              </span>
              <input
                v-model="form.slug"
                type="text"
                placeholder="acme-corp"
                class="flex-1 px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-r-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
              />
            </div>
          </div>

          <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
            {{ error }}
          </div>

          <button
            type="submit"
            :disabled="!form.name.trim() || !form.slug.trim() || submitting"
            class="w-full px-4 py-2.5 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ submitting ? 'Creating...' : 'Create Workspace' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Icon from '@/Components/shared/Icon.vue'
import WorkspaceIcon from '@/Components/shared/WorkspaceIcon.vue'

const form = ref({
  name: '',
  slug: '',
  icon: 'ph:buildings',
  color: 'blue',
})
const error = ref('')
const submitting = ref(false)

const colorOptions = [
  { name: 'neutral', bg: 'bg-neutral-500' },
  { name: 'blue', bg: 'bg-blue-500' },
  { name: 'green', bg: 'bg-green-500' },
  { name: 'yellow', bg: 'bg-yellow-500' },
  { name: 'orange', bg: 'bg-orange-500' },
  { name: 'red', bg: 'bg-red-500' },
  { name: 'purple', bg: 'bg-purple-500' },
  { name: 'pink', bg: 'bg-pink-500' },
]

const iconOptions = [
  'ph:buildings',
  'ph:rocket',
  'ph:lightning',
  'ph:star',
  'ph:diamond',
  'ph:cube',
  'ph:hexagon',
  'ph:globe',
  'ph:atom',
  'ph:crown',
  'ph:shield-check',
  'ph:code',
  'ph:palette',
  'ph:tree',
  'ph:mountains',
  'ph:coffee',
  'ph:fire',
  'ph:sparkle',
  'ph:heart',
  'ph:puzzle-piece',
]

const generateSlug = () => {
  form.value.slug = form.value.name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

const handleSubmit = async () => {
  if (!form.value.name.trim() || !form.value.slug.trim()) return

  submitting.value = true
  error.value = ''

  try {
    const response = await axios.post('/api/workspaces', {
      name: form.value.name.trim(),
      slug: form.value.slug.trim(),
      icon: form.value.icon,
      color: form.value.color,
    })

    router.visit(`/w/${response.data.slug}`)
  } catch (e: any) {
    const data = e?.response?.data
    error.value = data?.message || Object.values(data?.errors || {}).flat().join(', ') || 'Failed to create workspace'
  } finally {
    submitting.value = false
  }
}
</script>
