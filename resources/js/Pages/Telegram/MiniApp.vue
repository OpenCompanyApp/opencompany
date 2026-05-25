<template>
  <main class="min-h-screen bg-[var(--tg-bg)] text-[var(--tg-text)]">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
      <header class="sticky top-0 z-10 border-b border-[var(--tg-border)] bg-[var(--tg-bg)]/95 px-4 py-3 backdrop-blur">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <h1 class="truncate text-base font-semibold">OpenCompany</h1>
            <p class="truncate text-xs text-[var(--tg-muted)]">{{ workspaceLabel }}</p>
          </div>
          <span
            class="shrink-0 rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px] font-medium"
            :class="session ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--tg-muted)]'"
          >
            {{ session ? 'Linked' : 'Telegram' }}
          </span>
        </div>
      </header>

      <section v-if="!telegramInitData" class="flex flex-1 flex-col justify-center gap-2 px-4 py-8">
        <p class="text-sm font-medium">Open from Telegram</p>
        <p class="text-sm text-[var(--tg-muted)]">This panel needs signed Telegram launch data.</p>
      </section>

      <section v-else-if="missingWorkspace" class="flex flex-1 flex-col justify-center gap-3 px-4 py-8">
        <p class="text-sm font-medium">Choose workspace</p>
        <div v-if="workspaceLoading" class="space-y-2">
          <div class="h-12 animate-pulse rounded-md bg-[var(--tg-section)]" />
          <div class="h-12 animate-pulse rounded-md bg-[var(--tg-section)]" />
        </div>
        <div v-else-if="workspaceOptions.length" class="space-y-2">
          <button
            v-for="workspace in workspaceOptions"
            :key="workspace.id"
            type="button"
            class="w-full rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3 text-left"
            @click="chooseWorkspace(workspace.id)"
          >
            <div class="truncate text-sm font-medium">{{ workspace.name }}</div>
            <div class="mt-1 truncate text-xs text-[var(--tg-muted)]">{{ workspace.role || 'member' }}{{ workspace.bot_username ? ` · @${workspace.bot_username}` : '' }}</div>
          </button>
        </div>
        <p v-if="error" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>
        <input
          v-model="workspaceInput"
          type="text"
          placeholder="Workspace ID"
          class="h-10 rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] px-3 text-sm outline-none focus:border-[var(--tg-accent)]"
        />
        <button class="h-10 rounded-md bg-[var(--tg-accent)] px-3 text-sm font-medium text-white" type="button" @click="useWorkspaceInput">
          Continue
        </button>
      </section>

      <section v-else class="flex min-h-0 flex-1 flex-col">
        <nav class="scrollbar-none flex gap-1 overflow-x-auto border-b border-[var(--tg-border)] px-3 py-2">
          <button
            v-for="item in visiblePanels"
            :key="item"
            type="button"
            class="h-8 shrink-0 rounded-md px-3 text-xs font-medium capitalize"
            :class="activePanel === item ? 'bg-[var(--tg-accent)] text-white' : 'bg-[var(--tg-section)] text-[var(--tg-muted)]'"
            @click="openPanel(item)"
          >
            {{ panelLabel(item) }}
          </button>
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
          <div v-if="loading" class="space-y-2">
            <div class="h-10 animate-pulse rounded-md bg-[var(--tg-section)]" />
            <div class="h-24 animate-pulse rounded-md bg-[var(--tg-section)]" />
            <div class="h-24 animate-pulse rounded-md bg-[var(--tg-section)]" />
          </div>

          <div v-else-if="error" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            {{ error }}
          </div>

          <div v-else-if="activePanel === 'settings' && panelData" class="space-y-4">
            <section class="space-y-2">
              <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">Settings</h2>
                <span class="rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px]">{{ panelData.health_status || 'unknown' }}</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-xs">
                <Metric label="Receipts 24h" :value="panelData.operations?.receipts_24h ?? 0" />
                <Metric label="Failed sends" :value="panelData.operations?.failed_deliveries_24h ?? 0" />
                <Metric label="Lanes" :value="panelData.conversations ?? 0" />
                <Metric label="Linked" :value="panelData.linked_identities_count ?? 0" />
              </div>
            </section>

            <section v-if="panelData.diagnostics?.length" class="space-y-2">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Diagnostics</h3>
              <div v-for="diagnostic in panelData.diagnostics" :key="diagnostic.code" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3 text-xs">
                <div class="font-medium">{{ diagnostic.code }}</div>
                <div class="mt-1 text-[var(--tg-muted)]">{{ diagnostic.message }}</div>
              </div>
            </section>

            <section v-if="linkedTelegramIdentities.length" class="space-y-3">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Linked identities</h3>
              <div v-for="identity in linkedTelegramIdentities" :key="identity.id" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium">{{ identity.display_name || identity.user?.name || identity.telegram_user_id }}</div>
                    <div class="mt-1 truncate text-xs text-[var(--tg-muted)]">
                      {{ identity.user?.name || 'Unknown user' }} · {{ identity.user?.role || 'member' }} · {{ identity.telegram_user_id }}
                    </div>
                  </div>
                  <button
                    v-if="identity.can_revoke"
                    type="button"
                    class="h-8 shrink-0 rounded-md border border-red-300 bg-red-50 px-3 text-xs font-medium text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                    :disabled="actionLoading"
                    @click="revokeTelegramIdentity(identity.id)"
                  >
                    Revoke
                  </button>
                  <span v-else class="shrink-0 rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px] text-[var(--tg-muted)]">Current</span>
                </div>
              </div>
            </section>

            <section class="space-y-3">
              <div class="flex items-center justify-between gap-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Repair</h3>
                <button
                  type="button"
                  class="h-8 rounded-md px-3 text-xs font-medium"
                  :class="repairCandidateCounts.local > 0 ? 'bg-[var(--tg-accent)] text-white' : 'bg-[var(--tg-section)] text-[var(--tg-muted)]'"
                  :disabled="actionLoading || repairCandidateCounts.local === 0"
                  @click="repairTelegramState"
                >
                  Repair local state
                </button>
              </div>
              <div class="grid grid-cols-2 gap-2 text-xs">
                <Metric label="Expired buttons" :value="repairCandidateCounts.expired" />
                <Metric label="Missing lanes" :value="repairCandidateCounts.orphaned" />
                <Metric label="Lane drift" :value="repairCandidateCounts.drifted" />
                <Metric label="Webhook drift" :value="repairCandidateCounts.webhook" />
              </div>
            </section>

            <section v-if="operationReceipts.length || operationDeliveries.length" class="space-y-3">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Operations</h3>

              <div v-if="operationDeliveries.length" class="space-y-2">
                <div v-for="delivery in operationDeliveries" :key="delivery.id" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="truncate text-sm font-medium">{{ delivery.method || delivery.renderer_version || 'Telegram delivery' }}</div>
                      <div class="mt-1 truncate text-xs text-[var(--tg-muted)]">{{ delivery.status }} · {{ delivery.chat_id }}{{ delivery.topic_id ? ` / ${delivery.topic_id}` : '' }}</div>
                    </div>
                    <button
                      v-if="['failed', 'pending'].includes(delivery.status)"
                      type="button"
                      class="h-8 shrink-0 rounded-md bg-[var(--tg-accent)] px-3 text-xs font-medium text-white"
                      :disabled="actionLoading"
                      @click="retryTelegramDelivery(delivery.id)"
                    >
                      Retry
                    </button>
                  </div>
                  <p v-if="delivery.provider_error_message" class="mt-2 line-clamp-2 text-xs text-red-600 dark:text-red-300">{{ delivery.provider_error_message }}</p>
                </div>
              </div>

              <div v-if="operationReceipts.length" class="space-y-2">
                <div v-for="receipt in operationReceipts" :key="receipt.id" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="truncate text-sm font-medium">{{ receipt.update_type || 'Telegram update' }} #{{ receipt.update_id }}</div>
                      <div class="mt-1 truncate text-xs text-[var(--tg-muted)]">{{ receipt.status }} · retries {{ receipt.retry_count ?? 0 }}</div>
                    </div>
                    <button
                      v-if="['failed', 'received', 'processing'].includes(receipt.status)"
                      type="button"
                      class="h-8 shrink-0 rounded-md bg-[var(--tg-accent)] px-3 text-xs font-medium text-white"
                      :disabled="actionLoading"
                      @click="replayTelegramReceipt(receipt.id)"
                    >
                      Replay
                    </button>
                  </div>
                  <p v-if="receipt.error_message" class="mt-2 line-clamp-2 text-xs text-red-600 dark:text-red-300">{{ receipt.error_message }}</p>
                </div>
              </div>
            </section>

            <section v-if="panelData.recent_lanes?.length" class="space-y-3">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Lane</h3>
                <select v-model="selectedConversationId" class="h-8 rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] px-2 text-xs">
                  <option v-for="lane in panelData.recent_lanes" :key="lane.id" :value="lane.id">
                    {{ lane.title || lane.channel || lane.chat_id }}
                  </option>
                </select>
              </div>

              <div class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <div class="text-sm font-medium">{{ selectedLane?.title || selectedLane?.channel || selectedLane?.chat_id }}</div>
                <div class="mt-1 text-xs text-[var(--tg-muted)]">
                  {{ selectedLane?.chat_type }} · {{ selectedLane?.topic_id ? `topic ${selectedLane.topic_id}` : 'main lane' }}
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                  <button
                    v-for="mode in laneModes"
                    :key="mode.value"
                    type="button"
                    class="h-8 rounded-md text-xs font-medium"
                    :class="selectedMode === mode.value ? 'bg-[var(--tg-accent)] text-white' : 'bg-[var(--tg-bg)] text-[var(--tg-muted)]'"
                    @click="saveMode(mode.value)"
                  >
                    {{ mode.label }}
                  </button>
                </div>
              </div>

              <div class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Agent</label>
                <div class="mt-2 flex gap-2">
                  <select v-model="selectedAgentId" class="h-9 min-w-0 flex-1 rounded-md border border-[var(--tg-border)] bg-[var(--tg-bg)] px-2 text-sm">
                    <option value="">Workspace default</option>
                    <option v-for="agent in panelData.available_agents || []" :key="agent.id" :value="agent.id">
                      {{ agent.name }}
                    </option>
                  </select>
                  <button type="button" class="h-9 rounded-md bg-[var(--tg-accent)] px-3 text-xs font-medium text-white" :disabled="actionLoading" @click="saveAgent">
                    Save
                  </button>
                </div>
              </div>
            </section>

            <section class="space-y-3">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Alerts</h3>
              <div class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <div class="grid grid-cols-2 gap-2">
                  <input v-model="notificationEvent" class="h-9 rounded-md border border-[var(--tg-border)] bg-[var(--tg-bg)] px-2 text-sm" />
                  <select v-model="notificationSeverity" class="h-9 rounded-md border border-[var(--tg-border)] bg-[var(--tg-bg)] px-2 text-sm">
                    <option value="normal">normal</option>
                    <option value="high">high</option>
                  </select>
                  <select v-model="notificationMode" class="h-9 rounded-md border border-[var(--tg-border)] bg-[var(--tg-bg)] px-2 text-sm">
                    <option value="immediate">immediate</option>
                    <option value="silent">silent</option>
                    <option value="digest">digest</option>
                    <option value="batched">batched</option>
                    <option value="off">off</option>
                  </select>
                  <button type="button" class="h-9 rounded-md bg-[var(--tg-accent)] px-3 text-xs font-medium text-white" :disabled="actionLoading" @click="saveNotification">
                    Apply
                  </button>
                </div>
              </div>

              <div class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <div class="grid grid-cols-3 gap-2">
                  <select v-model="digestSchedule" class="h-9 rounded-md border border-[var(--tg-border)] bg-[var(--tg-bg)] px-2 text-sm">
                    <option value="daily">daily</option>
                    <option value="weekly">weekly</option>
                    <option value="off">off</option>
                  </select>
                  <input v-model="digestTime" class="h-9 rounded-md border border-[var(--tg-border)] bg-[var(--tg-bg)] px-2 text-sm" placeholder="09:00" />
                  <button type="button" class="h-9 rounded-md bg-[var(--tg-accent)] px-3 text-xs font-medium text-white" :disabled="actionLoading" @click="saveDigest">
                    Set
                  </button>
                </div>
              </div>
            </section>
          </div>

          <div v-else-if="activePanel === 'tasks' && panelData?.type === 'task_detail'" class="space-y-4">
            <section class="space-y-2">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h2 class="break-words text-sm font-semibold">{{ panelData.title }}</h2>
                  <p class="mt-1 text-xs text-[var(--tg-muted)]">{{ panelData.agent || 'Unassigned' }} · {{ panelData.status }}</p>
                </div>
                <span class="shrink-0 rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px]">{{ panelData.priority }}</span>
              </div>
              <p v-if="panelData.description" class="text-sm text-[var(--tg-muted)]">{{ panelData.description }}</p>
            </section>

            <section class="grid grid-cols-2 gap-2 text-xs">
              <Metric label="Steps" :value="panelData.step_counts?.total ?? 0" />
              <Metric label="Elapsed" :value="formatDuration(panelData.elapsed_seconds)" />
              <Metric label="Pending" :value="panelData.step_counts?.pending ?? 0" />
              <Metric label="Done" :value="panelData.step_counts?.completed ?? 0" />
            </section>

            <section v-if="panelData.current_step" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Current step</div>
              <div class="mt-2 text-sm font-medium">{{ panelData.current_step.description }}</div>
              <div class="mt-1 text-xs text-[var(--tg-muted)]">{{ panelData.current_step.status }} · {{ panelData.current_step.type }}</div>
            </section>

            <section v-if="panelData.actions?.length" class="grid grid-cols-2 gap-2">
              <button
                v-for="action in panelData.actions"
                :key="action"
                type="button"
                class="h-9 rounded-md text-xs font-medium"
                :class="action === 'cancel' ? 'border border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' : 'bg-[var(--tg-accent)] text-white'"
                :disabled="actionLoading"
                @click="runTaskAction(action)"
              >
                {{ taskActionLabel(action) }}
              </button>
            </section>

            <section v-if="panelData.steps?.length" class="space-y-2">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Execution</h3>
              <div v-for="step in panelData.steps" :key="step.id" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <div class="text-sm font-medium">{{ step.description }}</div>
                <div class="mt-1 text-xs text-[var(--tg-muted)]">{{ step.status }} · {{ step.type }}</div>
              </div>
            </section>
          </div>

          <div v-else-if="activePanel === 'approvals' && panelData?.type === 'approval_detail'" class="space-y-4">
            <section class="space-y-2">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h2 class="break-words text-sm font-semibold">{{ panelData.title }}</h2>
                  <p class="mt-1 text-xs text-[var(--tg-muted)]">{{ panelData.requester || 'unknown' }} · {{ panelData.status }}</p>
                </div>
                <span class="shrink-0 rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px]">{{ panelData.type }}</span>
              </div>
              <p v-if="panelData.description" class="text-sm text-[var(--tg-muted)]">{{ panelData.description }}</p>
            </section>

            <section class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Inspection</div>
              <dl class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Tool</dt>
                  <dd class="min-w-0 break-words text-right">{{ panelData.tool }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Amount</dt>
                  <dd class="min-w-0 break-words text-right">{{ panelData.amount || 'n/a' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Audit</dt>
                  <dd class="min-w-0 break-words text-right">{{ panelData.audit_id }}</dd>
                </div>
              </dl>
            </section>

            <section v-if="Object.keys(panelData.arguments || {}).length" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Arguments</div>
              <dl class="mt-2 space-y-2 text-xs">
                <div v-for="(value, key) in panelData.arguments" :key="key" class="grid grid-cols-[minmax(0,0.45fr)_minmax(0,0.55fr)] gap-2">
                  <dt class="break-words text-[var(--tg-muted)]">{{ key }}</dt>
                  <dd class="break-words text-right">{{ value }}</dd>
                </div>
              </dl>
            </section>

            <section v-if="panelData.responded_by" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3 text-sm">
              {{ panelData.status }} by {{ panelData.responded_by }}
            </section>

            <section v-if="panelData.actions?.length" class="grid grid-cols-2 gap-2">
              <button
                v-for="action in panelData.actions"
                :key="action"
                type="button"
                class="h-9 rounded-md text-xs font-medium"
                :class="action === 'reject' ? 'border border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' : 'bg-[var(--tg-accent)] text-white'"
                :disabled="actionLoading"
                @click="runApprovalAction(action)"
              >
                {{ approvalActionLabel(action) }}
              </button>
            </section>
          </div>

          <div v-else-if="activePanel === 'docs' && panelData?.type === 'document_detail'" class="space-y-4">
            <section class="space-y-2">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h2 class="break-words text-sm font-semibold">{{ panelData.title }}</h2>
                  <p class="mt-1 text-xs text-[var(--tg-muted)]">{{ panelData.author || 'OpenCompany' }} · {{ panelData.is_folder ? 'folder' : panelData.content_format }}</p>
                </div>
                <span v-if="panelData.is_system" class="shrink-0 rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px]">system</span>
              </div>
            </section>

            <section class="grid grid-cols-3 gap-2 text-xs">
              <Metric label="Children" :value="panelData.children_count ?? 0" />
              <Metric label="Files" :value="panelData.attachments_count ?? 0" />
              <Metric label="Comments" :value="panelData.comments_count ?? 0" />
            </section>

            <section v-if="panelData.parent || panelData.updated_at" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
              <dl class="space-y-2 text-sm">
                <div v-if="panelData.parent" class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Parent</dt>
                  <dd class="min-w-0 break-words text-right">{{ panelData.parent }}</dd>
                </div>
                <div v-if="panelData.updated_at" class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Updated</dt>
                  <dd class="min-w-0 break-words text-right">{{ formatDate(panelData.updated_at) }}</dd>
                </div>
              </dl>
            </section>

            <section v-if="panelData.content_excerpt" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Preview</div>
              <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ panelData.content_excerpt }}</p>
            </section>

            <section v-if="panelData.actions?.length" class="grid grid-cols-1 gap-2">
              <button
                type="button"
                class="h-9 rounded-md bg-[var(--tg-accent)] text-xs font-medium text-white"
                @click="openWebUrl(panelData.web_url)"
              >
                Open in web
              </button>
            </section>
          </div>

          <div v-else-if="activePanel === 'automation' && panelData?.type === 'automation_detail'" class="space-y-4">
            <section class="space-y-2">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h2 class="break-words text-sm font-semibold">{{ panelData.name }}</h2>
                  <p class="mt-1 text-xs text-[var(--tg-muted)]">{{ panelData.agent || 'Unassigned' }} · {{ panelData.is_active ? 'active' : 'paused' }}</p>
                </div>
                <span class="shrink-0 rounded-md border border-[var(--tg-border)] px-2 py-1 text-[11px]">{{ panelData.execution_type }}</span>
              </div>
              <p v-if="panelData.description" class="text-sm text-[var(--tg-muted)]">{{ panelData.description }}</p>
            </section>

            <section class="grid grid-cols-2 gap-2 text-xs">
              <Metric label="Runs" :value="panelData.run_count ?? 0" />
              <Metric label="Failures" :value="panelData.consecutive_failures ?? 0" />
              <Metric label="Trigger" :value="panelData.trigger_type || 'schedule'" />
              <Metric label="Timezone" :value="panelData.timezone || 'UTC'" />
            </section>

            <section class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">Schedule</div>
              <dl class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Cron</dt>
                  <dd class="min-w-0 break-words text-right">{{ panelData.cron_expression }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Next</dt>
                  <dd class="min-w-0 break-words text-right">{{ formatDate(panelData.next_run_at) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-[var(--tg-muted)]">Last</dt>
                  <dd class="min-w-0 break-words text-right">{{ formatDate(panelData.last_run_at) }}</dd>
                </div>
              </dl>
            </section>

            <section v-if="panelData.last_error" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
              {{ panelData.last_error }}
            </section>

            <section v-if="panelData.actions?.length" class="grid grid-cols-2 gap-2">
              <button
                v-for="action in panelData.actions"
                :key="action"
                type="button"
                class="h-9 rounded-md text-xs font-medium"
                :class="action === 'pause' ? 'border border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' : 'bg-[var(--tg-accent)] text-white'"
                :disabled="actionLoading"
                @click="runAutomationAction(action)"
              >
                {{ automationActionLabel(action) }}
              </button>
            </section>

            <section v-if="panelData.recent_runs?.length" class="space-y-2">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--tg-muted)]">History</h3>
              <div v-for="run in panelData.recent_runs" :key="run.id" class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
                <div class="text-sm font-medium">{{ run.title }}</div>
                <div class="mt-1 text-xs text-[var(--tg-muted)]">{{ run.status }} · {{ formatDate(run.created_at) }}</div>
              </div>
            </section>
          </div>

          <div v-else-if="panelData" class="space-y-3">
            <h2 class="text-sm font-semibold capitalize">{{ panelTitle }}</h2>
            <div v-if="Array.isArray(panelItems) && panelItems.length" class="space-y-2">
              <button
                v-for="item in panelItems"
                :key="item.id || item.name || item.title"
                type="button"
                class="w-full rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3 text-left"
                @click="item.id && loadTarget(item.id)"
              >
                <div class="truncate text-sm font-medium">{{ item.title || item.name || item.event_type || item.id }}</div>
                <div class="mt-1 truncate text-xs text-[var(--tg-muted)]">{{ item.status || item.type || item.agent || item.severity || item.source || '' }}</div>
              </button>
            </div>
            <div v-else class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3 text-sm">
              <pre class="whitespace-pre-wrap break-words text-xs">{{ JSON.stringify(panelData, null, 2) }}</pre>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import Metric from './Partials/Metric.vue'

type TelegramWebApp = {
  initData?: string
  initDataUnsafe?: { start_param?: string }
  themeParams?: Record<string, string>
  ready?: () => void
  expand?: () => void
  BackButton?: { show: () => void; hide: () => void; onClick: (callback: () => void) => void }
}

declare global {
  interface Window {
    Telegram?: { WebApp?: TelegramWebApp }
  }
}

const props = defineProps<{
  workspaceId?: string | null
  initialPanel?: string | null
  targetId?: string | null
}>()

const workspaceId = ref(props.workspaceId || '')
const workspaceInput = ref(workspaceId.value)
const telegramInitData = ref('')
const activePanel = ref(props.initialPanel || 'settings')
const targetId = ref(props.targetId || '')
const session = ref<any>(null)
const panelData = ref<any>(null)
const loading = ref(false)
const workspaceLoading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const workspaceOptions = ref<any[]>([])
const selectedConversationId = ref('')
const selectedAgentId = ref('')
const selectedMode = ref('command_center')
const notificationEvent = ref('task_failed')
const notificationSeverity = ref('high')
const notificationMode = ref('immediate')
const digestSchedule = ref('daily')
const digestTime = ref('09:00')

const laneModes = [
  { value: 'command_center', label: 'Mention' },
  { value: 'observed', label: 'Observe' },
  { value: 'free_response', label: 'Free' },
  { value: 'ignored', label: 'Ignore' },
]

const visiblePanels = computed(() => session.value?.panels?.length ? session.value.panels : ['settings'])
const missingWorkspace = computed(() => !workspaceId.value)
const workspaceLabel = computed(() => session.value?.workspace?.name || workspaceId.value || 'Mini App')
const panelTitle = computed(() => String(panelData.value?.type || activePanel.value).replace(/_/g, ' '))
const panelItems = computed(() => panelData.value?.items || panelData.value?.steps || panelData.value?.recent_lanes || [])
const selectedLane = computed(() => (panelData.value?.recent_lanes || []).find((lane: any) => lane.id === selectedConversationId.value))
const linkedTelegramIdentities = computed(() => panelData.value?.linked_identities || [])
const operationReceipts = computed(() => panelData.value?.operations_logs?.receipts || [])
const operationDeliveries = computed(() => panelData.value?.operations_logs?.deliveries || [])
const repairCandidateCounts = computed(() => {
  const candidates = panelData.value?.repair_candidates || {}
  const expired = candidates.expired_open_interactions?.length || 0
  const orphaned = candidates.orphaned_conversations?.length || 0
  const drifted = candidates.broken_conversation_mappings?.length || 0
  const webhook = candidates.stale_health_profile ? 1 : 0

  return {
    expired,
    orphaned,
    drifted,
    webhook,
    local: expired + orphaned + drifted,
  }
})

watch(selectedLane, (lane) => {
  if (!lane) return
  selectedAgentId.value = lane.default_agent?.id || ''
  selectedMode.value = lane.mode || 'command_center'
}, { immediate: true })

onMounted(async () => {
  bootTelegramTheme()
  telegramInitData.value = window.Telegram?.WebApp?.initData || new URLSearchParams(window.location.search).get('init_data') || ''
  workspaceId.value ||= workspaceFromStartParam() || ''
  workspaceInput.value = workspaceId.value
  window.Telegram?.WebApp?.ready?.()
  window.Telegram?.WebApp?.expand?.()

  if (workspaceId.value && telegramInitData.value) {
    await loadSession()
  } else if (telegramInitData.value) {
    await loadWorkspaceOptions()
  }
})

function bootTelegramTheme() {
  const theme = window.Telegram?.WebApp?.themeParams || {}
  const root = document.documentElement
  root.style.setProperty('--tg-bg', theme.bg_color || '#f7f7f7')
  root.style.setProperty('--tg-section', theme.secondary_bg_color || '#ffffff')
  root.style.setProperty('--tg-text', theme.text_color || '#111827')
  root.style.setProperty('--tg-muted', theme.hint_color || '#6b7280')
  root.style.setProperty('--tg-accent', theme.button_color || '#2481cc')
  root.style.setProperty('--tg-border', 'color-mix(in srgb, var(--tg-muted) 24%, transparent)')
}

function workspaceFromStartParam(): string {
  const value = window.Telegram?.WebApp?.initDataUnsafe?.start_param || ''
  const match = value.match(/(?:workspace|w)[_:]([0-9a-fA-F-]{32,36})/)
  return match?.[1] || ''
}

function useWorkspaceInput() {
  workspaceId.value = workspaceInput.value.trim()
  if (workspaceId.value && telegramInitData.value) {
    loadSession()
  }
}

async function loadWorkspaceOptions() {
  workspaceLoading.value = true
  error.value = ''
  try {
    const data = await apiPost('/api/telegram/mini-app/workspaces', {
      init_data: telegramInitData.value,
    })
    workspaceOptions.value = data.workspaces || []
    if (workspaceOptions.value.length === 1) {
      chooseWorkspace(workspaceOptions.value[0].id)
    }
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    workspaceLoading.value = false
  }
}

function chooseWorkspace(id: string) {
  workspaceId.value = id
  workspaceInput.value = id
  loadSession()
}

async function apiPost(path: string, body: Record<string, unknown>) {
  const xsrfToken = csrfToken()
  const response = await fetch(path, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
    },
    body: JSON.stringify(body),
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok || data.ok === false) {
    throw new Error(data.message || data.error || `Request failed (${response.status})`)
  }
  return data
}

async function loadSession() {
  loading.value = true
  error.value = ''
  try {
    const data = await apiPost('/api/telegram/mini-app/session', basePayload())
    session.value = data
    await openPanel(activePanel.value)
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}

async function openPanel(panel: string) {
  activePanel.value = panel
  loading.value = true
  error.value = ''
  try {
    const data = await apiPost('/api/telegram/mini-app/panel', {
      ...basePayload(),
      panel,
      target_id: targetId.value || undefined,
    })
    panelData.value = data.data
    await nextTick()
    if (panel === 'settings' && panelData.value?.recent_lanes?.length && !selectedConversationId.value) {
      selectedConversationId.value = panelData.value.recent_lanes[0].id
    }
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}

function loadTarget(id: string) {
  targetId.value = id
  openPanel(activePanel.value)
}

function openWebUrl(url: string | null | undefined) {
  if (url) {
    window.location.href = url
  }
}

async function saveAgent() {
  await runAction({
    action: 'set_default_agent',
    conversation_id: selectedConversationId.value,
    agent_id: selectedAgentId.value || null,
  })
}

async function saveMode(mode: string) {
  selectedMode.value = mode
  await runAction({
    action: 'set_conversation_mode',
    conversation_id: selectedConversationId.value,
    mode,
  })
}

async function saveNotification() {
  await runAction({
    action: 'set_notification',
    conversation_id: selectedConversationId.value,
    event_type: notificationEvent.value,
    severity: notificationSeverity.value,
    notification_mode: notificationMode.value,
  })
}

async function saveDigest() {
  await runAction({
    action: 'set_digest',
    conversation_id: selectedConversationId.value,
    schedule: digestSchedule.value,
    time: digestTime.value,
  })
}

async function repairTelegramState() {
  await runAction({
    action: 'repair_telegram_state',
    repair_actions: ['expire_interactions', 'repair_conversations'],
  })
}

async function revokeTelegramIdentity(id: string) {
  await runAction({
    action: 'revoke_telegram_identity',
    identity_id: id,
  })
}

async function retryTelegramDelivery(id: string) {
  await runAction({
    action: 'retry_telegram_delivery',
    delivery_id: id,
  })
}

async function replayTelegramReceipt(id: string) {
  await runAction({
    action: 'replay_telegram_receipt',
    receipt_id: id,
  })
}

async function runTaskAction(action: string) {
  if (action === 'open' && panelData.value?.web_url) {
    window.location.href = panelData.value.web_url
    return
  }

  await runAction({
    action: `${action}_task`,
    task_id: panelData.value?.id,
  })
}

async function runApprovalAction(action: string) {
  if (action === 'open' && panelData.value?.web_url) {
    window.location.href = panelData.value.web_url
    return
  }

  await runAction({
    action: `${action}_approval`,
    approval_id: panelData.value?.id,
  })
}

async function runAutomationAction(action: string) {
  if (action === 'open' && panelData.value?.web_url) {
    window.location.href = panelData.value.web_url
    return
  }

  await runAction({
    action: `${action}_automation`,
    automation_id: panelData.value?.id,
  })
}

async function runAction(payload: Record<string, unknown>) {
  actionLoading.value = true
  error.value = ''
  try {
    await apiPost('/api/telegram/mini-app/action', {
      ...basePayload(),
      ...payload,
    })
    await openPanel(activePanel.value)
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    actionLoading.value = false
  }
}

function basePayload() {
  return {
    workspace_id: workspaceId.value,
    init_data: telegramInitData.value,
  }
}

function csrfToken() {
  const token = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1]

  return token ? decodeURIComponent(token) : ''
}

function panelLabel(panel: string) {
  return panel === 'identity' ? 'Me' : panel
}

function taskActionLabel(action: string) {
  return action === 'open' ? 'Open in web' : action.charAt(0).toUpperCase() + action.slice(1)
}

function approvalActionLabel(action: string) {
  return action === 'open' ? 'Open in web' : action.charAt(0).toUpperCase() + action.slice(1)
}

function automationActionLabel(action: string) {
  return action === 'open' ? 'Open in web' : action.charAt(0).toUpperCase() + action.slice(1)
}

function formatDate(value: string | null | undefined) {
  if (!value) return 'n/a'
  return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatDuration(seconds: number | null | undefined) {
  if (!seconds) return '0m'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m`
  return `${Math.floor(minutes / 60)}h ${minutes % 60}m`
}
</script>
