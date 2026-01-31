<template>
  <Slideover
    v-model:open="isOpen"
    side="right"
    size="lg"
    :show-close="false"
  >
    <template #header>
      <div class="flex items-start justify-between gap-4 w-full">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-2">
            <span
              :class="[
                'px-2 py-0.5 text-xs font-medium rounded-full',
                priorityClasses[task.priority]
              ]"
            >
              {{ task.priority }}
            </span>
            <span
              :class="[
                'px-2 py-0.5 text-xs font-medium rounded-full',
                statusClasses[task.status]
              ]"
            >
              {{ statusLabels[task.status] }}
            </span>
          </div>
          <input
            v-if="editing"
            v-model="editedTask.title"
            class="w-full text-xl font-semibold text-neutral-900 dark:text-white bg-transparent border-b border-neutral-200 dark:border-neutral-700 focus:border-neutral-900 dark:focus:border-white outline-none pb-1"
            placeholder="Task title"
          />
          <h2 v-else class="text-xl font-semibold text-neutral-900 dark:text-white truncate">
            {{ task.title }}
          </h2>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-if="!editing"
            class="p-2 text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-lg transition-colors"
            @click="startEditing"
          >
            <Icon name="ph:pencil-simple" class="w-5 h-5" />
          </button>
          <button
            class="p-2 text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-lg transition-colors"
            @click="isOpen = false"
          >
            <Icon name="ph:x" class="w-5 h-5" />
          </button>
        </div>
      </div>
    </template>

    <template #body>
      <!-- Content -->
      <div class="space-y-6">
            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                Description
              </label>
              <textarea
                v-if="editing"
                v-model="editedTask.description"
                rows="4"
                class="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-700 dark:text-neutral-200 resize-none focus:border-neutral-300 dark:focus:border-neutral-600 focus:outline-none"
                placeholder="Add a description..."
              />
              <p v-else class="text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
                {{ task.description || 'No description' }}
              </p>
            </div>

            <!-- Assignee -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                Assignee
              </label>
              <div v-if="task.assignee" class="flex items-center gap-3 p-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
                <AgentAvatar :user="task.assignee" size="md" />
                <div>
                  <Link
                    :href="task.assignee.type === 'agent' ? `/agent/${task.assignee.id}` : `/profile/${task.assignee.id}`"
                    class="font-medium text-neutral-900 dark:text-white hover:text-neutral-900 dark:hover:text-white transition-colors"
                  >
                    {{ task.assignee.name }}
                  </Link>
                  <p class="text-sm text-neutral-500 dark:text-neutral-400 capitalize">
                    {{ task.assignee.type }}
                  </p>
                </div>
              </div>
              <p v-else class="text-neutral-500 dark:text-neutral-400">Unassigned</p>
            </div>

            <!-- Status & Priority -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                  Status
                </label>
                <select
                  v-if="editing"
                  v-model="editedTask.status"
                  class="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-700 dark:text-neutral-200 focus:border-neutral-300 dark:focus:border-neutral-600 focus:outline-none"
                >
                  <option value="backlog">Backlog</option>
                  <option value="in_progress">In Progress</option>
                  <option value="done">Done</option>
                </select>
                <div v-else class="flex items-center gap-2">
                  <span :class="['w-2 h-2 rounded-full', statusDots[task.status]]" />
                  <span class="text-neutral-700 dark:text-neutral-300">{{ statusLabels[task.status] }}</span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                  Priority
                </label>
                <select
                  v-if="editing"
                  v-model="editedTask.priority"
                  class="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-700 dark:text-neutral-200 focus:border-neutral-300 dark:focus:border-neutral-600 focus:outline-none"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
                <div v-else class="flex items-center gap-2">
                  <span :class="['w-2 h-2 rounded-full', priorityDots[task.priority]]" />
                  <span class="text-neutral-700 dark:text-neutral-300 capitalize">{{ task.priority }}</span>
                </div>
              </div>
            </div>

            <!-- Cost -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                  Estimated Cost
                </label>
                <input
                  v-if="editing"
                  v-model.number="editedTask.estimatedCost"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-700 dark:text-neutral-200 focus:border-neutral-300 dark:focus:border-neutral-600 focus:outline-none"
                  placeholder="0.00"
                />
                <p v-else class="text-neutral-700 dark:text-neutral-300">
                  {{ task.estimatedCost ? `$${parseFloat(String(task.estimatedCost)).toFixed(2)}` : 'Not set' }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                  Actual Cost
                </label>
                <p class="text-neutral-700 dark:text-neutral-300">
                  {{ task.cost ? `$${parseFloat(String(task.cost)).toFixed(2)}` : 'Not tracked' }}
                </p>
              </div>
            </div>

            <!-- Collaborators -->
            <div v-if="task.collaborators && task.collaborators.length > 0">
              <label class="block text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">
                Collaborators
              </label>
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="collab in task.collaborators"
                  :key="collab.id"
                  class="flex items-center gap-2 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 rounded-lg"
                >
                  <AgentAvatar :user="collab" size="xs" />
                  <Link
                    :href="collab.type === 'agent' ? `/agent/${collab.id}` : `/profile/${collab.id}`"
                    class="text-sm text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white transition-colors"
                  >
                    {{ collab.name }}
                  </Link>
                </div>
              </div>
            </div>

            <!-- Timestamps -->
            <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-neutral-500 dark:text-neutral-400">Created</span>
                  <p class="text-neutral-700 dark:text-neutral-300">
                    {{ formatDate(task.createdAt) }}
                  </p>
                </div>
                <div v-if="task.completedAt">
                  <span class="text-neutral-500 dark:text-neutral-400">Completed</span>
                  <p class="text-neutral-700 dark:text-neutral-300">
                    {{ formatDate(task.completedAt) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Comments -->
            <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                  Comments ({{ comments.length }})
                </h3>
              </div>

              <!-- Comment List -->
              <div class="space-y-4 mb-4">
                <div v-if="loadingComments" class="flex items-center justify-center py-4">
                  <Icon name="ph:spinner" class="w-5 h-5 text-neutral-500 dark:text-neutral-400 animate-spin" />
                </div>
                <div v-else-if="comments.length === 0" class="text-center py-4">
                  <p class="text-sm text-neutral-500 dark:text-neutral-400">No comments yet</p>
                </div>
                <template v-else>
                  <div
                    v-for="comment in comments"
                    :key="comment.id"
                    class="group"
                  >
                    <!-- Top-level comment -->
                    <div class="flex gap-3">
                      <AgentAvatar v-if="comment.author" :user="comment.author" size="sm" />
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                          <Link
                            v-if="comment.author"
                            :href="comment.author.type === 'agent' ? `/agent/${comment.author.id}` : `/profile/${comment.author.id}`"
                            class="text-sm font-medium text-neutral-900 dark:text-white hover:text-neutral-900 dark:hover:text-white transition-colors"
                          >
                            {{ comment.author.name }}
                          </Link>
                          <span class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ formatRelativeTime(comment.createdAt) }}
                          </span>
                          <button
                            class="ml-auto p-1 text-neutral-500 dark:text-neutral-400 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Delete comment"
                            @click="handleDeleteComment(comment.id)"
                          >
                            <Icon name="ph:trash" class="w-3.5 h-3.5" />
                          </button>
                        </div>
                        <p class="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
                          {{ comment.content }}
                        </p>
                        <button
                          class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white mt-1 transition-colors"
                          @click="replyingTo = replyingTo === comment.id ? null : comment.id"
                        >
                          Reply
                        </button>

                        <!-- Reply input -->
                        <div v-if="replyingTo === comment.id" class="mt-2">
                          <div class="flex gap-2">
                            <input
                              v-model="replyContent"
                              type="text"
                              placeholder="Write a reply..."
                              class="flex-1 px-3 py-1.5 text-sm bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-700 dark:text-neutral-200 placeholder-neutral-500 dark:placeholder-neutral-400 focus:border-neutral-300 dark:focus:border-neutral-600 focus:outline-none"
                              @keyup.enter="submitReply(comment.id)"
                            />
                            <button
                              class="px-3 py-1.5 text-xs bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50"
                              :disabled="!replyContent.trim() || submittingComment"
                              @click="submitReply(comment.id)"
                            >
                              Reply
                            </button>
                          </div>
                        </div>

                        <!-- Replies -->
                        <div v-if="comment.replies && comment.replies.length > 0" class="mt-3 space-y-3 pl-4 border-l-2 border-neutral-200 dark:border-neutral-700">
                          <div
                            v-for="reply in comment.replies"
                            :key="reply.id"
                            class="group/reply flex gap-2"
                          >
                            <AgentAvatar v-if="reply.author" :user="reply.author" size="xs" />
                            <div class="flex-1 min-w-0">
                              <div class="flex items-center gap-2 mb-0.5">
                                <Link
                                  v-if="reply.author"
                                  :href="reply.author.type === 'agent' ? `/agent/${reply.author.id}` : `/profile/${reply.author.id}`"
                                  class="text-xs font-medium text-neutral-900 dark:text-white hover:text-neutral-900 dark:hover:text-white transition-colors"
                                >
                                  {{ reply.author.name }}
                                </Link>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                  {{ formatRelativeTime(reply.createdAt) }}
                                </span>
                                <button
                                  class="ml-auto p-0.5 text-neutral-500 dark:text-neutral-400 hover:text-red-400 opacity-0 group-hover/reply:opacity-100 transition-opacity"
                                  title="Delete reply"
                                  @click="handleDeleteComment(reply.id)"
                                >
                                  <Icon name="ph:trash" class="w-3 h-3" />
                                </button>
                              </div>
                              <p class="text-xs text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
                                {{ reply.content }}
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>
              </div>

              <!-- Add Comment -->
              <div class="flex gap-2">
                <input
                  v-model="newComment"
                  type="text"
                  placeholder="Add a comment..."
                  class="flex-1 px-3 py-2 text-sm bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-700 dark:text-neutral-200 placeholder-neutral-500 dark:placeholder-neutral-400 focus:border-neutral-300 dark:focus:border-neutral-600 focus:outline-none"
                  @keyup.enter="submitComment"
                />
                <button
                  class="px-4 py-2 text-sm bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50"
                  :disabled="!newComment.trim() || submittingComment"
                  @click="submitComment"
                >
                  <Icon v-if="submittingComment" name="ph:spinner" class="w-4 h-4 animate-spin" />
                  <Icon v-else name="ph:paper-plane-tilt" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
    </template>

    <template #footer>
      <!-- Footer -->
      <div v-if="editing" class="flex items-center justify-end gap-3 w-full">
        <Button variant="ghost" @click="cancelEditing">
          Cancel
        </Button>
        <Button :disabled="saving" :loading="saving" @click="saveChanges">
          Save Changes
        </Button>
      </div>

      <!-- Quick Actions -->
      <div v-else class="flex items-center gap-2 w-full">
        <Button
          v-if="task.status !== 'done'"
          class="flex-1"
          @click="markComplete"
        >
          <Icon name="ph:check" class="w-4 h-4 mr-1" />
          Mark Complete
        </Button>
        <Button
          v-else
          variant="outline"
          class="flex-1"
          @click="reopenTask"
        >
          <Icon name="ph:arrow-counter-clockwise" class="w-4 h-4 mr-1" />
          Reopen Task
        </Button>
        <Button
          variant="ghost"
          color="error"
          @click="deleteTaskConfirm"
        >
          <Icon name="ph:trash" class="w-4 h-4" />
        </Button>
      </div>
    </template>
  </Slideover>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import type { Task, TaskStatus, Priority } from '@/types'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Slideover from '@/Components/shared/Slideover.vue'

const props = defineProps<{
  open: boolean
  task: Task
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update': [task: Partial<Task>]
  'delete': [taskId: string]
  'addComment': [content: string, parentId?: string]
  'deleteComment': [commentId: string]
}>()

// Computed v-model for open state
const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value),
})

interface TaskComment {
  id: string
  taskId: string
  authorId: string
  content: string
  parentId: string | null
  createdAt: Date | string
  updatedAt: Date | string
  author?: { id: string; name: string; type: string; avatar?: string }
  replies?: TaskComment[]
}

const editing = ref(false)
const saving = ref(false)
const editedTask = ref({
  title: '',
  description: '',
  status: 'backlog' as TaskStatus,
  priority: 'medium' as Priority,
  estimatedCost: null as number | null,
})

// Comments state
const comments = ref<TaskComment[]>([])
const loadingComments = ref(false)
const newComment = ref('')
const replyContent = ref('')
const replyingTo = ref<string | null>(null)
const submittingComment = ref(false)

const priorityClasses: Record<Priority, string> = {
  low: 'bg-neutral-500/20 text-neutral-400',
  medium: 'bg-blue-500/20 text-blue-400',
  high: 'bg-amber-500/20 text-amber-400',
  urgent: 'bg-red-500/20 text-red-400',
}

const priorityDots: Record<Priority, string> = {
  low: 'bg-neutral-400',
  medium: 'bg-blue-400',
  high: 'bg-amber-400',
  urgent: 'bg-red-400',
}

const statusClasses: Record<TaskStatus, string> = {
  backlog: 'bg-neutral-500/20 text-neutral-400',
  in_progress: 'bg-neutral-100 text-neutral-900',
  done: 'bg-green-500/20 text-green-400',
}

const statusLabels: Record<TaskStatus, string> = {
  backlog: 'Backlog',
  in_progress: 'In Progress',
  done: 'Done',
}

const statusDots: Record<TaskStatus, string> = {
  backlog: 'bg-neutral-400',
  in_progress: 'bg-neutral-900',
  done: 'bg-green-400',
}

const formatDate = (date: Date | string): string => {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const startEditing = () => {
  editedTask.value = {
    title: props.task.title,
    description: props.task.description,
    status: props.task.status,
    priority: props.task.priority,
    estimatedCost: props.task.estimatedCost ?? null,
  }
  editing.value = true
}

const cancelEditing = () => {
  editing.value = false
}

const saveChanges = async () => {
  saving.value = true
  try {
    emit('update', {
      id: props.task.id,
      title: editedTask.value.title,
      description: editedTask.value.description,
      status: editedTask.value.status,
      priority: editedTask.value.priority,
      estimatedCost: editedTask.value.estimatedCost,
    })
    editing.value = false
  } finally {
    saving.value = false
  }
}

const markComplete = async () => {
  saving.value = true
  try {
    emit('update', {
      id: props.task.id,
      status: 'done',
      completedAt: new Date(),
    })
  } finally {
    saving.value = false
  }
}

const reopenTask = async () => {
  saving.value = true
  try {
    emit('update', {
      id: props.task.id,
      status: 'in_progress',
      completedAt: undefined,
    })
  } finally {
    saving.value = false
  }
}

const deleteTaskConfirm = async () => {
  if (confirm('Are you sure you want to delete this task?')) {
    emit('delete', props.task.id)
    emit('update:open', false)
  }
}

// Comment functions
const submitComment = async () => {
  if (!newComment.value.trim() || submittingComment.value) return

  submittingComment.value = true
  try {
    emit('addComment', newComment.value.trim())
    newComment.value = ''
  } finally {
    submittingComment.value = false
  }
}

const submitReply = async (parentId: string) => {
  if (!replyContent.value.trim() || submittingComment.value) return

  submittingComment.value = true
  try {
    emit('addComment', replyContent.value.trim(), parentId)
    replyContent.value = ''
    replyingTo.value = null
  } finally {
    submittingComment.value = false
  }
}

const handleDeleteComment = async (commentId: string) => {
  if (!confirm('Delete this comment?')) return
  emit('deleteComment', commentId)
}

const formatRelativeTime = (date: Date | string): string => {
  const d = new Date(date)
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Reset state when dialog opens/closes
watch(() => props.open, (isOpen) => {
  if (!isOpen) {
    // Reset state when closing
    comments.value = []
    newComment.value = ''
    replyContent.value = ''
    replyingTo.value = null
    editing.value = false
  }
}, { immediate: true })
</script>
