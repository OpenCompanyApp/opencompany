<template>
  <Modal
    v-model:open="isOpen"
    :title="disk ? 'Edit Disk' : 'Add Storage Disk'"
    icon="ph:hard-drives"
    size="md"
  >
    <div class="space-y-4">
      <SettingsField label="Name">
        <input
          v-model="form.name"
          type="text"
          class="settings-input"
          placeholder="e.g. S3 Production"
        />
      </SettingsField>

      <SettingsField label="Driver">
        <select v-model="form.driver" class="settings-input" :disabled="!!disk">
          <option value="local">Local</option>
          <option value="s3">Amazon S3</option>
          <option value="sftp">SFTP</option>
        </select>
      </SettingsField>

      <!-- S3 Config -->
      <template v-if="form.driver === 's3'">
        <SettingsField label="Access Key">
          <input v-model="form.config.key" type="password" class="settings-input" placeholder="AWS Access Key ID" />
        </SettingsField>
        <SettingsField label="Secret Key">
          <input v-model="form.config.secret" type="password" class="settings-input" placeholder="AWS Secret Access Key" />
        </SettingsField>
        <SettingsField label="Region">
          <input v-model="form.config.region" type="text" class="settings-input" placeholder="us-east-1" />
        </SettingsField>
        <SettingsField label="Bucket">
          <input v-model="form.config.bucket" type="text" class="settings-input" placeholder="my-bucket" />
        </SettingsField>
        <SettingsField label="Endpoint" description="Custom endpoint for S3-compatible services (e.g. MinIO, DigitalOcean Spaces).">
          <input v-model="form.config.endpoint" type="url" class="settings-input" placeholder="https://s3.example.com" />
        </SettingsField>
      </template>

      <!-- SFTP Config -->
      <template v-if="form.driver === 'sftp'">
        <SettingsField label="Host">
          <input v-model="form.config.host" type="text" class="settings-input" placeholder="sftp.example.com" />
        </SettingsField>
        <SettingsField label="Port">
          <input v-model="form.config.port" type="text" class="settings-input" placeholder="22" />
        </SettingsField>
        <SettingsField label="Username">
          <input v-model="form.config.username" type="text" class="settings-input" placeholder="user" />
        </SettingsField>
        <SettingsField label="Password">
          <input v-model="form.config.password" type="password" class="settings-input" placeholder="Password" />
        </SettingsField>
        <SettingsField label="Root Path" description="Base directory on the server for file storage.">
          <input v-model="form.config.root" type="text" class="settings-input" placeholder="/var/files" />
        </SettingsField>
      </template>
    </div>

    <template #footer>
      <div class="flex items-center justify-between">
        <button
          v-if="disk"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors disabled:opacity-40"
          :disabled="testing"
          @click="handleTest"
        >
          <Icon :name="testing ? 'ph:spinner' : 'ph:plugs-connected'" :class="['w-3.5 h-3.5', testing && 'animate-spin']" />
          {{ testResult ? testResult : 'Test Connection' }}
        </button>
        <div v-else />
        <div class="flex gap-2">
          <button
            class="px-4 py-1.5 rounded-lg text-xs font-medium text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            @click="isOpen = false"
          >
            Cancel
          </button>
          <button
            class="px-4 py-1.5 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-medium hover:opacity-90 transition-opacity disabled:opacity-40"
            :disabled="!canSave || saving"
            @click="handleSave"
          >
            {{ saving ? 'Saving...' : (disk ? 'Save' : 'Create') }}
          </button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Icon from '@/Components/shared/Icon.vue'
import SettingsField from './SettingsField.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import type { WorkspaceDisk } from '@/types'

const props = defineProps<{
  disk: WorkspaceDisk | null
}>()

const emit = defineEmits<{
  saved: []
}>()

const isOpen = defineModel<boolean>('open', { default: false })
const { createDisk, updateDisk, testDisk } = useApi()
const toast = useToast()

const saving = ref(false)
const testing = ref(false)
const testResult = ref('')

const form = reactive({
  name: '',
  driver: 'local' as string,
  config: {} as Record<string, string>,
})

const canSave = computed(() => {
  if (!form.name.trim()) return false
  if (form.driver === 's3' && (!form.config.key || !form.config.secret || !form.config.region || !form.config.bucket)) return false
  if (form.driver === 'sftp' && (!form.config.host || !form.config.username)) return false
  return true
})

watch(isOpen, (val) => {
  if (val) {
    testResult.value = ''
    if (props.disk) {
      form.name = props.disk.name
      form.driver = props.disk.driver
      form.config = { ...(props.disk.config || {}) }
    } else {
      form.name = ''
      form.driver = 'local'
      form.config = {}
    }
  }
})

const handleSave = async () => {
  saving.value = true
  try {
    if (props.disk) {
      await updateDisk(props.disk.id, {
        name: form.name,
        config: form.driver !== 'local' ? form.config : undefined,
      })
      toast.success('Disk updated.')
    } else {
      await createDisk({
        name: form.name,
        driver: form.driver,
        config: form.driver !== 'local' ? form.config : undefined,
      })
      toast.success('Disk created.')
    }
    emit('saved')
  } catch (e: any) {
    toast.error(e.response?.data?.error || e.response?.data?.message || 'Failed to save disk.')
  } finally {
    saving.value = false
  }
}

const handleTest = async () => {
  if (!props.disk) return
  testing.value = true
  testResult.value = ''
  try {
    const res = await testDisk(props.disk.id)
    testResult.value = res.data?.success ? 'Connected!' : (res.data?.message || 'Failed')
  } catch (e: any) {
    testResult.value = 'Failed'
  } finally {
    testing.value = false
    setTimeout(() => { testResult.value = '' }, 3000)
  }
}
</script>

<style scoped>
.settings-input {
  width: 100%;
  padding: 0.5rem 1rem;
  border-radius: 0.75rem;
  border: 1px solid;
  font-size: 0.875rem;
  transition: border-color 0.15s;
  outline: none;
  background-color: rgb(250 250 250);
  border-color: rgb(229 229 229);
  color: rgb(23 23 23);
}

:is(.dark) .settings-input {
  background-color: rgb(38 38 38);
  border-color: rgb(64 64 64);
  color: white;
}

.settings-input:focus {
  border-color: rgb(163 163 163);
}
</style>
