<template>
  <div
    data-test="integration-card"
    :data-integration-id="integration.id"
    :class="[
      'flex flex-col p-4 rounded-lg border bg-white dark:bg-neutral-900 transition-colors cursor-pointer',
      integration.installed
        ? 'border-green-200 dark:border-green-900/50'
        : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700',
    ]"
    @click="handleClick"
  >
    <div class="flex items-start gap-3">
      <div
        :class="[
          'w-9 h-9 rounded-lg flex items-center justify-center shrink-0',
          integration.installed
            ? 'bg-green-100 dark:bg-green-900/30'
            : 'bg-neutral-100 dark:bg-neutral-800',
        ]"
      >
        <Icon
          :name="integration.icon"
          :class="[
            'w-4.5 h-4.5',
            integration.installed
              ? 'text-green-600 dark:text-green-400'
              : 'text-neutral-600 dark:text-neutral-400',
          ]"
        />
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1 flex-wrap">
          <p class="text-sm font-medium text-neutral-900 dark:text-white leading-tight">{{ integration.name }}</p>
          <Icon
            v-if="integration.badge === 'built-in'"
            name="ph:shield-check-fill"
            class="w-3.5 h-3.5 text-blue-500 dark:text-blue-400 shrink-0"
            title="Built-in"
          />
          <Icon
            v-else-if="integration.badge === 'verified'"
            name="ph:seal-check-fill"
            class="w-3.5 h-3.5 text-green-500 dark:text-green-400 shrink-0"
            title="Officially supported"
          />
          <span
            v-else-if="integration.badge === 'mcp'"
            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400"
            title="Remote MCP Server"
          >
            <Icon name="ph:plugs-connected" class="w-3 h-3" />
            MCP
          </span>
          <span
            v-else-if="integration.catalog"
            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400"
            title="Catalog integration"
          >
            Catalog
          </span>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">{{ integration.description }}</p>
        <p v-if="integration.catalog" class="text-[11px] text-neutral-400 dark:text-neutral-500 mt-1">
          <span v-if="integration.toolCount">{{ integration.toolCount }} tool{{ integration.toolCount === 1 ? '' : 's' }}</span>
          <span v-if="integration.toolCount && integration.packageInstalled === false"> · </span>
          <span v-if="integration.packageInstalled === false">package not installed</span>
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
      <template v-if="!integration.installed">
        <button
          type="button"
          data-test="integration-card-action"
          class="w-full py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-white transition-colors"
          @click.stop="$emit('install', integration)"
        >
          {{ actionLabel }}
        </button>
      </template>
      <template v-else>
        <span class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1 mr-auto">
          <Icon name="ph:check-circle-fill" class="w-3.5 h-3.5" />
          Installed
        </span>
        <button
          v-if="canConfigure"
          type="button"
          data-test="integration-configure"
          class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
          title="Configure"
          @click.stop="$emit('configure', integration)"
        >
          <Icon name="ph:gear" class="w-4 h-4" />
        </button>
        <button
          type="button"
          data-test="integration-uninstall"
          class="p-1 text-neutral-400 hover:text-red-500 transition-colors"
          title="Uninstall"
          @click.stop="$emit('uninstall', integration)"
        >
          <Icon name="ph:trash" class="w-4 h-4" />
        </button>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

export interface Integration {
  id: string
  name: string
  icon: string
  description: string
  category?: string
  installed: boolean
  badge?: string
  catalog?: boolean
  packageInstalled?: boolean
  configured?: boolean
  enabled?: boolean
  runnable?: boolean
  docsUrl?: string | null
  configurable?: boolean
  type?: 'native' | 'mcp'
  mcpServerId?: string
  toolCount?: number
  suggestedMcpConfig?: {
    url: string
    auth_type: 'none' | 'bearer' | 'header'
    icon: string
    description: string
  }
}

// IntegrationCard is intentionally action-oriented: installed, configurable,
// catalog-only, and MCP entries all share the same card shell but emit different
// page-level actions.
const props = defineProps<{
  integration: Integration
}>()

const emit = defineEmits<{
  install: [integration: Integration]
  uninstall: [integration: Integration]
  configure: [integration: Integration]
  click: [integration: Integration]
}>()

const handleClick = () => {
  // Clicking an uninstalled card starts setup directly; installed cards prefer
  // configure when there is meaningful configuration to edit.
  if (!props.integration.installed) {
    emit('install', props.integration)
    return
  }

  if (canConfigure.value) {
    emit('configure', props.integration)
    return
  }

  emit('click', props.integration)
}

const canConfigure = computed(() => {
  const integration = props.integration

  // AI providers and Codex have dynamic config screens even when the catalog
  // metadata does not mark them configurable.
  return Boolean(
    integration.configurable
      || integration.type === 'mcp'
      || integration.category === 'ai-models'
      || integration.id === 'codex',
  )
})

const actionLabel = computed(() => {
  const integration = props.integration

  // Catalog entries without installed packages cannot be installed from inside
  // OpenCompany; route users to docs/catalog instead.
  if (integration.catalog && integration.packageInstalled === false) {
    return integration.docsUrl ? 'Docs' : 'Catalog'
  }

  if (integration.configurable || integration.category === 'ai-models' || integration.id === 'codex') {
    return 'Configure'
  }

  return 'Install'
})
</script>
