<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Automation</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-300 mt-1">
            Create task templates and automation rules for your workflows
          </p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 mt-4">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
            activeTab === tab.id
              ? 'bg-neutral-900 text-white'
              : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-800'
          ]"
          @click="activeTab = tab.id"
        >
          <Icon :name="tab.icon" class="w-4 h-4 inline mr-2" />
          {{ tab.label }}
        </button>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Task Templates Tab -->
      <div v-if="activeTab === 'templates'" class="space-y-6">
        <!-- Create Template Button -->
        <div class="flex justify-between items-center">
          <h2 class="text-lg font-medium text-neutral-900 dark:text-white">Task Templates</h2>
          <button class="btn-primary" @click="showCreateTemplate = true">
            <Icon name="ph:plus" class="w-4 h-4 mr-2" />
            Create Template
          </button>
        </div>

        <!-- Templates List -->
        <div class="grid gap-4">
          <div
            v-for="template in templates"
            :key="template.id"
            class="p-4 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="font-medium text-neutral-900 dark:text-white">{{ template.name }}</h3>
                  <span
                    :class="[
                      'px-2 py-0.5 text-xs rounded-full',
                      template.isActive
                        ? 'bg-green-500/20 text-green-400'
                        : 'bg-neutral-50 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-300'
                    ]"
                  >
                    {{ template.isActive ? 'Active' : 'Inactive' }}
                  </span>
                </div>
                <p v-if="template.description" class="text-sm text-neutral-500 dark:text-neutral-300 mt-1">
                  {{ template.description }}
                </p>
                <div class="flex items-center gap-4 mt-3 text-xs text-neutral-500 dark:text-neutral-300">
                  <span class="flex items-center gap-1">
                    <Icon name="ph:text-t" class="w-3.5 h-3.5" />
                    {{ template.defaultTitle }}
                  </span>
                  <span class="flex items-center gap-1">
                    <Icon name="ph:flag" class="w-3.5 h-3.5" />
                    {{ template.defaultPriority }}
                  </span>
                  <span v-if="template.defaultAssignee" class="flex items-center gap-1">
                    <Icon name="ph:user" class="w-3.5 h-3.5" />
                    {{ template.defaultAssignee.name }}
                  </span>
                  <span v-if="template.estimatedCost" class="flex items-center gap-1">
                    <Icon name="ph:coins" class="w-3.5 h-3.5" />
                    ${{ template.estimatedCost }}
                  </span>
                </div>
                <div v-if="template.tags?.length" class="flex gap-1 mt-2">
                  <span
                    v-for="tag in template.tags"
                    :key="tag"
                    class="px-2 py-0.5 text-xs bg-neutral-100 dark:bg-neutral-700 rounded-full text-neutral-500 dark:text-neutral-300"
                  >
                    {{ tag }}
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button
                  class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-900 dark:text-white transition-colors"
                  @click="handleCreateTaskFromTemplate(template)"
                >
                  <Icon name="ph:play" class="w-4 h-4" />
                </button>
                <button
                  class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-500 dark:text-neutral-300 transition-colors"
                  @click="handleEditTemplate(template)"
                >
                  <Icon name="ph:pencil" class="w-4 h-4" />
                </button>
                <button
                  class="p-2 rounded-lg hover:bg-neutral-100 text-red-400 transition-colors"
                  @click="handleDeleteTemplate(template.id)"
                >
                  <Icon name="ph:trash" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="templates.length === 0" class="text-center py-12 text-neutral-500 dark:text-neutral-300">
            <Icon name="ph:file-dashed" class="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>No task templates yet</p>
            <p class="text-sm mt-1">Create a template to get started</p>
          </div>
        </div>
      </div>

      <!-- Automation Rules Tab -->
      <div v-if="activeTab === 'rules'" class="space-y-6">
        <!-- Create Rule Button -->
        <div class="flex justify-between items-center">
          <h2 class="text-lg font-medium text-neutral-900 dark:text-white">Automation Rules</h2>
          <button class="btn-primary" @click="showCreateRule = true">
            <Icon name="ph:plus" class="w-4 h-4 mr-2" />
            Create Rule
          </button>
        </div>

        <!-- Rules List -->
        <div class="grid gap-4">
          <div
            v-for="rule in rules"
            :key="rule.id"
            class="p-4 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="font-medium text-neutral-900 dark:text-white">{{ rule.name }}</h3>
                  <span
                    :class="[
                      'px-2 py-0.5 text-xs rounded-full',
                      rule.isActive
                        ? 'bg-green-500/20 text-green-400'
                        : 'bg-neutral-50 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-300'
                    ]"
                  >
                    {{ rule.isActive ? 'Active' : 'Inactive' }}
                  </span>
                </div>
                <p v-if="rule.description" class="text-sm text-neutral-500 dark:text-neutral-300 mt-1">
                  {{ rule.description }}
                </p>
                <div class="flex items-center gap-4 mt-3">
                  <span class="flex items-center gap-1 px-2 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-700 text-xs text-neutral-700 dark:text-neutral-200">
                    <Icon name="ph:lightning" class="w-3.5 h-3.5 text-yellow-400" />
                    {{ formatTriggerType(rule.triggerType) }}
                  </span>
                  <Icon name="ph:arrow-right" class="w-4 h-4 text-neutral-500 dark:text-neutral-300" />
                  <span class="flex items-center gap-1 px-2 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-700 text-xs text-neutral-700 dark:text-neutral-200">
                    <Icon name="ph:gear" class="w-3.5 h-3.5 text-neutral-900 dark:text-white" />
                    {{ formatActionType(rule.actionType) }}
                  </span>
                </div>
                <div v-if="rule.template" class="mt-2 text-xs text-neutral-500 dark:text-neutral-300">
                  Using template: <span class="text-neutral-700 dark:text-neutral-200">{{ rule.template.name }}</span>
                </div>
                <div v-if="rule.triggerCount > 0" class="mt-2 text-xs text-neutral-500 dark:text-neutral-300">
                  Triggered {{ rule.triggerCount }} times
                  <span v-if="rule.lastTriggeredAt">
                    - Last: {{ formatDate(rule.lastTriggeredAt) }}
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button
                  :class="[
                    'p-2 rounded-lg transition-colors',
                    rule.isActive
                      ? 'hover:bg-neutral-100 text-green-400'
                      : 'hover:bg-neutral-100 text-neutral-500'
                  ]"
                  @click="handleToggleRule(rule)"
                >
                  <Icon :name="rule.isActive ? 'ph:toggle-right-fill' : 'ph:toggle-left'" class="w-5 h-5" />
                </button>
                <button
                  class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-500 dark:text-neutral-300 transition-colors"
                  @click="handleEditRule(rule)"
                >
                  <Icon name="ph:pencil" class="w-4 h-4" />
                </button>
                <button
                  class="p-2 rounded-lg hover:bg-neutral-100 text-red-400 transition-colors"
                  @click="handleDeleteRule(rule.id)"
                >
                  <Icon name="ph:trash" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="rules.length === 0" class="text-center py-12 text-neutral-500 dark:text-neutral-300">
            <Icon name="ph:robot" class="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>No automation rules yet</p>
            <p class="text-sm mt-1">Create a rule to automate your workflows</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Template Modal -->
    <Modal v-model:open="showCreateTemplate" title="Create Task Template">
      <form class="space-y-4" @submit.prevent="handleSaveTemplate">
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Template Name</label>
          <input
            v-model="templateForm.name"
            type="text"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
            placeholder="e.g., Bug Report, Feature Request"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Description</label>
          <textarea
            v-model="templateForm.description"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500 resize-none"
            rows="2"
            placeholder="Describe when to use this template"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Default Task Title</label>
          <input
            v-model="templateForm.defaultTitle"
            type="text"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
            placeholder="e.g., [Bug] {summary}"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Default Description</label>
          <textarea
            v-model="templateForm.defaultDescription"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500 resize-none"
            rows="3"
            placeholder="Default task description..."
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Default Priority</label>
            <select
              v-model="templateForm.defaultPriority"
              class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Estimated Cost</label>
            <input
              v-model.number="templateForm.estimatedCost"
              type="number"
              step="0.01"
              class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
              placeholder="0.00"
            />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Default Assignee</label>
          <select
            v-model="templateForm.defaultAssigneeId"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
          >
            <option value="">No default assignee</option>
            <option v-for="user in users" :key="user.id" :value="user.id">
              {{ user.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Tags (comma separated)</label>
          <input
            v-model="templateForm.tagsString"
            type="text"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
            placeholder="bug, frontend, urgent"
          />
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button
            type="button"
            class="px-4 py-2 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
            @click="showCreateTemplate = false"
          >
            Cancel
          </button>
          <button type="submit" class="btn-primary">
            {{ editingTemplate ? 'Update' : 'Create' }} Template
          </button>
        </div>
      </form>
    </Modal>

    <!-- Create Rule Modal -->
    <Modal v-model:open="showCreateRule" title="Create Automation Rule">
      <form class="space-y-4" @submit.prevent="handleSaveRule">
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Rule Name</label>
          <input
            v-model="ruleForm.name"
            type="text"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
            placeholder="e.g., Auto-assign bug reports"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Description</label>
          <textarea
            v-model="ruleForm.description"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500 resize-none"
            rows="2"
            placeholder="Describe what this rule does"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Trigger</label>
            <select
              v-model="ruleForm.triggerType"
              class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
              required
            >
              <option value="">Select trigger...</option>
              <option value="task_created">Task Created</option>
              <option value="task_completed">Task Completed</option>
              <option value="task_assigned">Task Assigned</option>
              <option value="approval_granted">Approval Granted</option>
              <option value="approval_rejected">Approval Rejected</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Action</label>
            <select
              v-model="ruleForm.actionType"
              class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
              required
            >
              <option value="">Select action...</option>
              <option value="create_task">Create Task</option>
              <option value="assign_task">Assign Task</option>
              <option value="send_notification">Send Notification</option>
              <option value="update_task">Update Task</option>
              <option value="spawn_agent">Spawn Agent</option>
            </select>
          </div>
        </div>
        <div v-if="ruleForm.actionType === 'create_task'">
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1">Task Template</label>
          <select
            v-model="ruleForm.templateId"
            class="w-full bg-white dark:bg-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 outline-none border border-neutral-200 dark:border-neutral-600 focus:border-neutral-300 dark:focus:border-neutral-500"
          >
            <option value="">Select template...</option>
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button
            type="button"
            class="px-4 py-2 text-sm text-neutral-500 dark:text-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
            @click="showCreateRule = false"
          >
            Cancel
          </button>
          <button type="submit" class="btn-primary">
            {{ editingRule ? 'Update' : 'Create' }} Rule
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { User } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

interface TaskTemplate {
  id: string
  name: string
  description: string | null
  defaultTitle: string
  defaultDescription: string | null
  defaultPriority: string
  defaultAssigneeId: string | null
  estimatedCost: number | null
  tags: string[] | null
  isActive: boolean
  createdAt: Date
  defaultAssignee?: User
}

interface AutomationRule {
  id: string
  name: string
  description: string | null
  triggerType: string
  triggerConditions: Record<string, unknown> | null
  actionType: string
  actionConfig: Record<string, unknown> | null
  templateId: string | null
  isActive: boolean
  triggerCount: number
  lastTriggeredAt: Date | null
  createdAt: Date
  template?: TaskTemplate
}

const {
  fetchUsers,
  fetchTaskTemplates,
  createTaskTemplate,
  updateTaskTemplate,
  deleteTaskTemplate,
  createTaskFromTemplate,
  fetchAutomationRules,
  createAutomationRule,
  updateAutomationRule,
  deleteAutomationRule,
} = useApi()

const tabs = [
  { id: 'templates', label: 'Task Templates', icon: 'ph:file-text' },
  { id: 'rules', label: 'Automation Rules', icon: 'ph:robot' },
]

const activeTab = ref('templates')
const showCreateTemplate = ref(false)
const showCreateRule = ref(false)
const editingTemplate = ref<TaskTemplate | null>(null)
const editingRule = ref<AutomationRule | null>(null)

// Fetch data
const { data: usersData } = fetchUsers()
const { data: templatesData, refresh: refreshTemplates } = fetchTaskTemplates(false)
const { data: rulesData, refresh: refreshRules } = fetchAutomationRules(false)

const users = computed<User[]>(() => usersData.value ?? [])
const templates = computed<TaskTemplate[]>(() => templatesData.value ?? [])
const rules = computed<AutomationRule[]>(() => rulesData.value ?? [])

// Template form
const templateForm = ref({
  name: '',
  description: '',
  defaultTitle: '',
  defaultDescription: '',
  defaultPriority: 'medium',
  defaultAssigneeId: '',
  estimatedCost: null as number | null,
  tagsString: '',
})

// Rule form
const ruleForm = ref({
  name: '',
  description: '',
  triggerType: '',
  actionType: '',
  templateId: '',
})

const resetTemplateForm = () => {
  templateForm.value = {
    name: '',
    description: '',
    defaultTitle: '',
    defaultDescription: '',
    defaultPriority: 'medium',
    defaultAssigneeId: '',
    estimatedCost: null,
    tagsString: '',
  }
  editingTemplate.value = null
}

const resetRuleForm = () => {
  ruleForm.value = {
    name: '',
    description: '',
    triggerType: '',
    actionType: '',
    templateId: '',
  }
  editingRule.value = null
}

const handleEditTemplate = (template: TaskTemplate) => {
  editingTemplate.value = template
  templateForm.value = {
    name: template.name,
    description: template.description ?? '',
    defaultTitle: template.defaultTitle,
    defaultDescription: template.defaultDescription ?? '',
    defaultPriority: template.defaultPriority,
    defaultAssigneeId: template.defaultAssigneeId ?? '',
    estimatedCost: template.estimatedCost,
    tagsString: template.tags?.join(', ') ?? '',
  }
  showCreateTemplate.value = true
}

const handleEditRule = (rule: AutomationRule) => {
  editingRule.value = rule
  ruleForm.value = {
    name: rule.name,
    description: rule.description ?? '',
    triggerType: rule.triggerType,
    actionType: rule.actionType,
    templateId: rule.templateId ?? '',
  }
  showCreateRule.value = true
}

const handleSaveTemplate = async () => {
  const tags = templateForm.value.tagsString
    .split(',')
    .map(t => t.trim())
    .filter(t => t.length > 0)

  const data = {
    name: templateForm.value.name,
    description: templateForm.value.description || undefined,
    defaultTitle: templateForm.value.defaultTitle,
    defaultDescription: templateForm.value.defaultDescription || undefined,
    defaultPriority: templateForm.value.defaultPriority,
    defaultAssigneeId: templateForm.value.defaultAssigneeId || undefined,
    estimatedCost: templateForm.value.estimatedCost ?? undefined,
    tags: tags.length > 0 ? tags : undefined,
  }

  if (editingTemplate.value) {
    await updateTaskTemplate(editingTemplate.value.id, data)
  } else {
    await createTaskTemplate(data)
  }

  await refreshTemplates()
  showCreateTemplate.value = false
  resetTemplateForm()
}

const handleDeleteTemplate = async (id: string) => {
  if (!confirm('Delete this template?')) return
  await deleteTaskTemplate(id)
  await refreshTemplates()
}

const handleCreateTaskFromTemplate = async (template: TaskTemplate) => {
  await createTaskFromTemplate(template.id)
  router.visit('/tasks')
}

const handleSaveRule = async () => {
  const data = {
    name: ruleForm.value.name,
    description: ruleForm.value.description || undefined,
    triggerType: ruleForm.value.triggerType,
    actionType: ruleForm.value.actionType,
    templateId: ruleForm.value.templateId || undefined,
  }

  if (editingRule.value) {
    await updateAutomationRule(editingRule.value.id, data)
  } else {
    await createAutomationRule(data)
  }

  await refreshRules()
  showCreateRule.value = false
  resetRuleForm()
}

const handleDeleteRule = async (id: string) => {
  if (!confirm('Delete this rule?')) return
  await deleteAutomationRule(id)
  await refreshRules()
}

const handleToggleRule = async (rule: AutomationRule) => {
  await updateAutomationRule(rule.id, { isActive: !rule.isActive })
  await refreshRules()
}

const formatTriggerType = (type: string) => {
  const map: Record<string, string> = {
    task_created: 'Task Created',
    task_completed: 'Task Completed',
    task_assigned: 'Task Assigned',
    approval_granted: 'Approval Granted',
    approval_rejected: 'Approval Rejected',
    schedule: 'Scheduled',
  }
  return map[type] || type
}

const formatActionType = (type: string) => {
  const map: Record<string, string> = {
    create_task: 'Create Task',
    assign_task: 'Assign Task',
    send_notification: 'Send Notification',
    update_task: 'Update Task',
    spawn_agent: 'Spawn Agent',
  }
  return map[type] || type
}

const formatDate = (date: Date | string) => {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

// Reset forms when modals close
watch(showCreateTemplate, (open) => {
  if (!open) resetTemplateForm()
})

watch(showCreateRule, (open) => {
  if (!open) resetRuleForm()
})
</script>
