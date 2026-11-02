CREATE TABLE "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);

CREATE TABLE "sessions"(
  "id" varchar not null,
  "user_id" varchar,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);

CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);

CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);

CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);

CREATE TABLE "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE "activity_steps"(
  "id" varchar not null,
  "user_id" varchar not null,
  "description" text not null,
  "status" varchar check("status" in('completed', 'in_progress', 'pending')) not null,
  "started_at" datetime not null,
  "completed_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE TABLE "channel_members"(
  "id" integer primary key autoincrement not null,
  "channel_id" varchar not null,
  "user_id" varchar not null,
  "role" varchar check("role" in('admin', 'member')) not null default 'member',
  "unread_count" integer not null default '0',
  "joined_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "last_read_at" datetime,
  foreign key("channel_id") references "channels"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "channel_members_channel_id_user_id_unique" on "channel_members"(
  "channel_id",
  "user_id"
);
CREATE TABLE "message_reactions"(
  "id" varchar not null,
  "message_id" varchar not null,
  "user_id" varchar not null,
  "emoji" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("message_id") references "messages"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "message_reactions_message_id_user_id_emoji_unique" on "message_reactions"(
  "message_id",
  "user_id",
  "emoji"
);
CREATE TABLE "message_attachments"(
  "id" varchar not null,
  "message_id" varchar not null,
  "filename" varchar not null,
  "original_name" varchar not null,
  "mime_type" varchar not null,
  "size" integer not null,
  "url" varchar not null,
  "uploaded_by_id" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("message_id") references "messages"("id") on delete cascade,
  foreign key("uploaded_by_id") references "users"("id"),
  primary key("id")
);

CREATE TABLE "list_item_collaborators"(
  "id" varchar not null,
  "list_item_id" varchar not null,
  "user_id" varchar not null,
  foreign key("list_item_id") references "list_items"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "task_collaborators_task_id_user_id_unique" on "list_item_collaborators"(
  "list_item_id",
  "user_id"
);
CREATE TABLE "list_item_comments"(
  "id" varchar not null,
  "list_item_id" varchar not null,
  "author_id" varchar not null,
  "content" text not null,
  "parent_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("author_id") references users("id") on delete no action on update no action,
  foreign key("list_item_id") references "list_items"("id") on delete cascade on update no action,
  foreign key("parent_id") references "list_item_comments"("id") on delete cascade,
  primary key("id")
);

CREATE TABLE "document_permissions"(
  "id" varchar not null,
  "document_id" varchar not null,
  "user_id" varchar not null,
  "role" varchar check("role" in('viewer', 'editor')) not null,
  foreign key("document_id") references "documents"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "document_permissions_document_id_user_id_unique" on "document_permissions"(
  "document_id",
  "user_id"
);
CREATE TABLE "document_comments"(
  "id" varchar not null,
  "document_id" varchar not null,
  "author_id" varchar not null,
  "content" text not null,
  "parent_id" varchar,
  "resolved" tinyint(1) not null default('0'),
  "resolved_by_id" varchar,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("resolved_by_id") references users("id") on delete set null on update no action,
  foreign key("author_id") references users("id") on delete no action on update no action,
  foreign key("document_id") references documents("id") on delete cascade on update no action,
  foreign key("parent_id") references "document_comments"("id") on delete cascade,
  primary key("id")
);

CREATE TABLE "document_versions"(
  "id" varchar not null,
  "document_id" varchar not null,
  "title" varchar not null,
  "content" text not null,
  "author_id" varchar not null,
  "version_number" integer not null,
  "change_description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "content_format" varchar not null default 'markdown',
  foreign key("document_id") references "documents"("id") on delete cascade,
  foreign key("author_id") references "users"("id"),
  primary key("id")
);

CREATE TABLE "document_attachments"(
  "id" varchar not null,
  "document_id" varchar not null,
  "filename" varchar not null,
  "original_name" varchar not null,
  "mime_type" varchar not null,
  "size" integer not null,
  "url" varchar not null,
  "uploaded_by_id" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade,
  foreign key("uploaded_by_id") references "users"("id"),
  primary key("id")
);

CREATE TABLE "codex_tokens"(
  "id" integer primary key autoincrement not null,
  "access_token" text not null,
  "refresh_token" text not null,
  "expires_at" datetime not null,
  "account_id" varchar,
  "email" varchar,
  "token_data" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "calendar_event_attendees"(
  "id" varchar not null,
  "event_id" varchar not null,
  "user_id" varchar not null,
  "status" varchar check("status" in('pending', 'accepted', 'declined', 'tentative')) not null default 'pending',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("event_id") references "calendar_events"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE TABLE "data_table_columns"(
  "id" varchar not null,
  "table_id" varchar not null,
  "name" varchar not null,
  "type" varchar not null,
  "options" text,
  "order" integer not null default '0',
  "required" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("table_id") references "data_tables"("id") on delete cascade,
  primary key("id")
);

CREATE TABLE "data_table_views"(
  "id" varchar not null,
  "table_id" varchar not null,
  "name" varchar not null,
  "type" varchar not null default 'grid',
  "filters" text,
  "sorts" text,
  "hidden_columns" text,
  "created_at" datetime,
  "updated_at" datetime,
  "config" text,
  foreign key("table_id") references "data_tables"("id") on delete cascade,
  primary key("id")
);

CREATE TABLE "data_table_rows"(
  "id" varchar not null,
  "table_id" varchar not null,
  "data" text not null,
  "created_by" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("table_id") references data_tables("id") on delete cascade on update no action,
  foreign key("created_by") references "users"("id") on delete set null,
  primary key("id")
);

CREATE TABLE "agent_conversations"(
  "id" varchar not null,
  "user_id" integer not null,
  "title" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);

CREATE INDEX "agent_conversations_user_id_updated_at_index" on "agent_conversations"(
  "user_id",
  "updated_at"
);
CREATE TABLE "agent_conversation_messages"(
  "id" varchar not null,
  "conversation_id" varchar not null,
  "user_id" integer not null,
  "agent" varchar not null,
  "role" varchar not null,
  "content" text not null,
  "attachments" text not null,
  "tool_calls" text not null,
  "tool_results" text not null,
  "usage" text not null,
  "meta" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);

CREATE INDEX "conversation_index" on "agent_conversation_messages"(
  "conversation_id",
  "user_id",
  "updated_at"
);
CREATE INDEX "agent_conversation_messages_user_id_index" on "agent_conversation_messages"(
  "user_id"
);
CREATE INDEX "agent_conversation_messages_conversation_id_index" on "agent_conversation_messages"(
  "conversation_id"
);
CREATE TABLE "agent_permissions"(
  "id" varchar not null,
  "agent_id" varchar not null,
  "scope_type" varchar not null,
  "scope_key" varchar not null,
  "permission" varchar not null default 'allow',
  "requires_approval" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("agent_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "agent_permissions_agent_id_scope_type_scope_key_unique" on "agent_permissions"(
  "agent_id",
  "scope_type",
  "scope_key"
);
CREATE INDEX "agent_permissions_agent_id_scope_type_index" on "agent_permissions"(
  "agent_id",
  "scope_type"
);
CREATE TABLE "user_external_identities"(
  "id" varchar not null,
  "user_id" varchar not null,
  "provider" varchar not null,
  "external_id" varchar not null,
  "display_name" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "user_external_identities_provider_external_id_unique" on "user_external_identities"(
  "provider",
  "external_id"
);
CREATE TABLE "workspaces"(
  "id" varchar not null,
  "name" varchar not null,
  "slug" varchar not null,
  "owner_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "icon" varchar not null default 'ph:buildings',
  "color" varchar not null default 'neutral',
  "governance_preset" varchar,
  foreign key("owner_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE UNIQUE INDEX "workspaces_slug_unique" on "workspaces"("slug");
CREATE TABLE "workspace_members"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "user_id" varchar not null,
  "role" varchar not null default 'member',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "workspace_members_workspace_id_user_id_unique" on "workspace_members"(
  "workspace_id",
  "user_id"
);
CREATE INDEX "workspace_members_user_id_index" on "workspace_members"(
  "user_id"
);
CREATE TABLE "workspace_invitations"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "email" varchar not null,
  "role" varchar not null default 'member',
  "token" varchar not null,
  "inviter_id" varchar not null,
  "accepted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("inviter_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "workspace_invitations_workspace_id_email_index" on "workspace_invitations"(
  "workspace_id",
  "email"
);
CREATE UNIQUE INDEX "workspace_invitations_token_unique" on "workspace_invitations"(
  "token"
);
CREATE TABLE "users"(
  "id" varchar not null,
  "name" varchar not null,
  "avatar" varchar,
  "type" varchar not null,
  "agent_type" varchar,
  "status" varchar not null default('offline'),
  "presence" varchar not null default('offline'),
  "last_seen_at" datetime,
  "current_task" varchar,
  "email" varchar,
  "email_verified_at" datetime,
  "password" varchar,
  "is_ephemeral" tinyint(1) not null default('0'),
  "manager_id" varchar,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "brain" varchar,
  "docs_folder_id" varchar,
  "awaiting_approval_id" varchar,
  "must_wait_for_approval" tinyint(1) not null default('0'),
  "bootstrapped_at" datetime,
  "sleeping_until" datetime,
  "sleeping_reason" varchar,
  "awaiting_delegation_ids" text,
  "workspace_id" varchar,
  foreign key("awaiting_approval_id") references approval_requests("id") on delete set null on update no action,
  foreign key("manager_id") references users("id") on delete set null on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "users_workspace_id_index" on "users"("workspace_id");
CREATE TABLE "channels"(
  "id" varchar not null,
  "name" varchar not null,
  "type" varchar not null default('public'),
  "description" text,
  "creator_id" varchar,
  "is_ephemeral" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "last_message_at" datetime,
  "external_provider" varchar,
  "external_id" varchar,
  "external_config" text,
  "workspace_id" varchar not null,
  foreign key("creator_id") references users("id") on delete set null on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "channels_workspace_id_index" on "channels"("workspace_id");
CREATE TABLE "documents"(
  "id" varchar not null,
  "title" varchar not null,
  "content" text not null,
  "author_id" varchar not null,
  "parent_id" varchar,
  "is_folder" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "is_system" tinyint(1) not null default('0'),
  "color" varchar,
  "icon" varchar,
  "workspace_id" varchar not null,
  "content_format" varchar not null default 'markdown',
  "deleted_at" datetime,
  foreign key("parent_id") references documents("id") on delete cascade on update no action,
  foreign key("author_id") references users("id") on delete no action on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "documents_workspace_id_index" on "documents"("workspace_id");
CREATE TABLE "list_items"(
  "id" varchar not null,
  "title" varchar not null,
  "description" text not null,
  "status" varchar not null default('backlog'),
  "assignee_id" varchar,
  "priority" varchar not null default('medium'),
  "cost" numeric,
  "estimated_cost" numeric,
  "channel_id" varchar,
  "position" integer not null default('0'),
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "creator_id" varchar,
  "parent_id" varchar,
  "is_folder" tinyint(1) not null default('0'),
  "due_date" date,
  "workspace_id" varchar not null,
  "deleted_at" datetime,
  foreign key("parent_id") references list_items("id") on delete set null on update no action,
  foreign key("channel_id") references channels("id") on delete set null on update no action,
  foreign key("assignee_id") references users("id") on delete no action on update no action,
  foreign key("creator_id") references users("id") on delete set null on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "list_items_workspace_id_index" on "list_items"("workspace_id");
CREATE TABLE "integration_settings"(
  "id" varchar not null,
  "integration_id" varchar not null,
  "config" text not null,
  "enabled" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  "account_alias" varchar not null default '',
  "is_default" tinyint(1) not null default '1',
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "integration_settings_workspace_id_index" on "integration_settings"(
  "workspace_id"
);
CREATE TABLE "app_settings"(
  "id" varchar not null,
  "key" varchar not null,
  "category" varchar not null,
  "value" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "app_settings_category_index" on "app_settings"("category");
CREATE INDEX "app_settings_workspace_id_index" on "app_settings"(
  "workspace_id"
);
CREATE TABLE "activities"(
  "id" varchar not null,
  "type" varchar not null,
  "description" text not null,
  "actor_id" varchar not null,
  "metadata" text,
  "timestamp" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("actor_id") references users("id") on delete no action on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "activities_workspace_id_index" on "activities"("workspace_id");
CREATE TABLE "notifications"(
  "id" varchar not null,
  "type" varchar not null,
  "title" varchar not null,
  "message" text not null,
  "user_id" varchar not null,
  "is_read" tinyint(1) not null default('0'),
  "action_url" varchar,
  "actor_id" varchar,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("actor_id") references users("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete no action on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "notifications_workspace_id_index" on "notifications"(
  "workspace_id"
);
CREATE TABLE "calendar_events"(
  "id" varchar not null,
  "title" varchar not null,
  "description" text,
  "start_at" datetime not null,
  "end_at" datetime,
  "all_day" tinyint(1) not null default('0'),
  "location" varchar,
  "color" varchar,
  "recurrence_rule" varchar,
  "created_by" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "recurrence_end" datetime,
  "workspace_id" varchar not null,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "calendar_events_workspace_id_index" on "calendar_events"(
  "workspace_id"
);
CREATE TABLE "data_tables"(
  "id" varchar not null,
  "name" varchar not null,
  "description" text,
  "icon" varchar,
  "created_by" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "data_tables_workspace_id_index" on "data_tables"(
  "workspace_id"
);
CREATE TABLE "mcp_servers"(
  "id" varchar not null,
  "name" varchar not null,
  "slug" varchar not null,
  "url" text not null,
  "auth_type" varchar not null default('none'),
  "auth_config" text,
  "headers" text,
  "enabled" tinyint(1) not null default('0'),
  "discovered_tools" text,
  "server_info" text,
  "tools_discovered_at" datetime,
  "timeout" integer not null default('30'),
  "icon" varchar not null default('ph:plug'),
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  "account_alias" varchar not null default '',
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "mcp_servers_workspace_id_index" on "mcp_servers"(
  "workspace_id"
);
CREATE TABLE "ai_gateway_api_keys"(
  "id" varchar not null,
  "name" varchar not null,
  "key_hash" varchar not null,
  "key_prefix" varchar not null,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "ai_gateway_api_keys_key_hash_unique" on "ai_gateway_api_keys"(
  "key_hash"
);
CREATE INDEX "ai_gateway_api_keys_workspace_id_index" on "ai_gateway_api_keys"(
  "workspace_id"
);
CREATE TABLE "list_statuses"(
  "id" varchar not null,
  "name" varchar not null,
  "slug" varchar not null,
  "color" varchar not null,
  "icon" varchar not null,
  "is_done" tinyint(1) not null default('0'),
  "is_default" tinyint(1) not null default('0'),
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "list_statuses_workspace_id_index" on "list_statuses"(
  "workspace_id"
);
CREATE TABLE "list_templates"(
  "id" varchar not null,
  "name" varchar not null,
  "description" text,
  "default_title" varchar not null,
  "default_description" text,
  "default_priority" varchar not null default('medium'),
  "default_assignee_id" varchar,
  "estimated_cost" numeric,
  "tags" text,
  "created_by_id" varchar not null,
  "is_active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("created_by_id") references users("id") on delete no action on update no action,
  foreign key("default_assignee_id") references users("id") on delete set null on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "list_templates_workspace_id_index" on "list_templates"(
  "workspace_id"
);
CREATE TABLE "document_chunks"(
  "id" varchar not null,
  "document_id" varchar not null,
  "content" text not null,
  "content_hash" varchar not null,
  "embedding" text,
  "metadata" text,
  "collection" varchar not null default('general'),
  "agent_id" varchar,
  "chunk_index" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("agent_id") references users("id") on delete set null on update no action,
  foreign key("document_id") references documents("id") on delete cascade on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "document_chunks_collection_agent_id_index" on "document_chunks"(
  "collection",
  "agent_id"
);
CREATE INDEX "document_chunks_content_hash_index" on "document_chunks"(
  "content_hash"
);
CREATE INDEX "document_chunks_document_id_index" on "document_chunks"(
  "document_id"
);
CREATE INDEX "document_chunks_workspace_id_index" on "document_chunks"(
  "workspace_id"
);
CREATE TABLE "conversation_summaries"(
  "id" varchar not null,
  "channel_id" varchar not null,
  "agent_id" varchar not null,
  "summary" text not null,
  "tokens_before" integer not null default('0'),
  "tokens_after" integer not null default('0'),
  "compaction_count" integer not null default('0'),
  "messages_summarized" integer not null default('0'),
  "last_message_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "flush_count" integer not null default('0'),
  "workspace_id" varchar not null,
  "compaction_failure_count" integer not null default '0',
  "last_compaction_failed_at" datetime,
  "compaction_circuit_open_until" datetime,
  "last_compaction_error" text,
  foreign key("agent_id") references users("id") on delete cascade on update no action,
  foreign key("channel_id") references channels("id") on delete cascade on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "conversation_summaries_channel_id_agent_id_unique" on "conversation_summaries"(
  "channel_id",
  "agent_id"
);
CREATE INDEX "conversation_summaries_workspace_id_index" on "conversation_summaries"(
  "workspace_id"
);
CREATE TABLE "embedding_cache"(
  "id" varchar not null,
  "provider" varchar not null,
  "model" varchar not null,
  "embedding" text,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "embedding_cache_workspace_id_index" on "embedding_cache"(
  "workspace_id"
);
CREATE TABLE "calendar_feeds"(
  "id" varchar not null,
  "user_id" varchar not null,
  "token" varchar not null,
  "name" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar not null,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "calendar_feeds_token_unique" on "calendar_feeds"(
  "token"
);
CREATE INDEX "calendar_feeds_workspace_id_index" on "calendar_feeds"(
  "workspace_id"
);
CREATE UNIQUE INDEX "app_settings_workspace_id_key_unique" on "app_settings"(
  "workspace_id",
  "key"
);
CREATE UNIQUE INDEX "list_statuses_workspace_id_slug_unique" on "list_statuses"(
  "workspace_id",
  "slug"
);
CREATE TABLE "tasks"(
  "id" varchar not null,
  "title" varchar not null,
  "description" text,
  "type" varchar not null default('custom'),
  "status" varchar not null default('pending'),
  "priority" varchar not null default('normal'),
  "agent_id" varchar,
  "requester_id" varchar not null,
  "channel_id" varchar,
  "list_item_id" varchar,
  "parent_task_id" varchar,
  "context" text,
  "result" text,
  "started_at" datetime,
  "completed_at" datetime,
  "due_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "source" varchar,
  "workspace_id" varchar not null,
  "trigger_message_id" varchar,
  "causality_id" varchar,
  "causality_depth" integer not null default '0',
  foreign key("workspace_id") references workspaces("id") on delete cascade on update no action,
  foreign key("list_item_id") references list_items("id") on delete set null on update no action,
  foreign key("channel_id") references channels("id") on delete set null on update no action,
  foreign key("requester_id") references users("id") on delete cascade on update no action,
  foreign key("agent_id") references users("id") on delete set null on update no action,
  foreign key("parent_task_id") references tasks("id") on delete set null on update no action,
  foreign key("trigger_message_id") references "messages"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "tasks_agent_id_status_index" on "tasks"("agent_id", "status");
CREATE INDEX "tasks_priority_index" on "tasks"("priority");
CREATE INDEX "tasks_source_index" on "tasks"("source");
CREATE INDEX "tasks_status_index" on "tasks"("status");
CREATE INDEX "tasks_type_index" on "tasks"("type");
CREATE INDEX "tasks_workspace_id_index" on "tasks"("workspace_id");
CREATE INDEX "tasks_trigger_message_id_agent_id_index" on "tasks"(
  "trigger_message_id",
  "agent_id"
);
CREATE TABLE "task_steps"(
  "id" varchar not null,
  "task_id" varchar not null,
  "description" text not null,
  "status" varchar not null default('pending'),
  "step_type" varchar not null default('action'),
  "metadata" text,
  "started_at" datetime,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "tool_invocation_id" varchar,
  foreign key("task_id") references tasks("id") on delete cascade on update no action,
  primary key("id")
);

CREATE INDEX "task_steps_status_index" on "task_steps"("status");
CREATE INDEX "task_steps_step_type_index" on "task_steps"("step_type");
CREATE TABLE "telescope_entries"(
  "sequence" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "batch_id" varchar not null,
  "family_hash" varchar,
  "should_display_on_index" tinyint(1) not null default '1',
  "type" varchar not null,
  "content" text not null,
  "created_at" datetime
);
CREATE UNIQUE INDEX "telescope_entries_uuid_unique" on "telescope_entries"(
  "uuid"
);
CREATE INDEX "telescope_entries_batch_id_index" on "telescope_entries"(
  "batch_id"
);
CREATE INDEX "telescope_entries_family_hash_index" on "telescope_entries"(
  "family_hash"
);
CREATE INDEX "telescope_entries_created_at_index" on "telescope_entries"(
  "created_at"
);
CREATE INDEX "telescope_entries_type_should_display_on_index_index" on "telescope_entries"(
  "type",
  "should_display_on_index"
);
CREATE TABLE "telescope_entries_tags"(
  "entry_uuid" varchar not null,
  "tag" varchar not null,
  foreign key("entry_uuid") references "telescope_entries"("uuid") on delete cascade,
  primary key("entry_uuid", "tag")
);

CREATE INDEX "telescope_entries_tags_tag_index" on "telescope_entries_tags"(
  "tag"
);
CREATE TABLE "telescope_monitoring"(
  "tag" varchar not null,
  primary key("tag")
);

CREATE TABLE "workspace_disks"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "name" varchar not null,
  "driver" varchar not null,
  "config" text,
  "is_default" tinyint(1) not null default '0',
  "enabled" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "workspace_disks_workspace_id_name_unique" on "workspace_disks"(
  "workspace_id",
  "name"
);
CREATE INDEX "workspace_disks_workspace_id_is_default_index" on "workspace_disks"(
  "workspace_id",
  "is_default"
);
CREATE TABLE "workspace_files"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "parent_id" varchar,
  "name" varchar not null,
  "is_folder" tinyint(1) not null default('0'),
  "storage_disk" varchar,
  "storage_path" varchar,
  "mime_type" varchar,
  "size" integer,
  "owner_id" varchar not null,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "description" text,
  "workspace_disk_id" varchar,
  foreign key("parent_id") references workspace_files("id") on delete cascade on update no action,
  foreign key("workspace_id") references workspaces("id") on delete cascade on update no action,
  foreign key("owner_id") references users("id") on delete no action on update no action,
  foreign key("workspace_disk_id") references "workspace_disks"("id") on delete set null,
  primary key("id")
);

CREATE UNIQUE INDEX "wf_workspace_parent_name_unique" on "workspace_files"(
  "workspace_id",
  "parent_id",
  "name"
);
CREATE INDEX "workspace_files_workspace_id_owner_id_index" on "workspace_files"(
  "workspace_id",
  "owner_id"
);
CREATE INDEX "workspace_files_workspace_id_parent_id_index" on "workspace_files"(
  "workspace_id",
  "parent_id"
);
CREATE INDEX "workspace_files_workspace_disk_id_index" on "workspace_files"(
  "workspace_disk_id"
);
CREATE UNIQUE INDEX "integration_settings_workspace_id_integration_id_account_alias_unique" on "integration_settings"(
  "workspace_id",
  "integration_id",
  "account_alias"
);
CREATE UNIQUE INDEX "mcp_servers_workspace_id_slug_account_alias_unique" on "mcp_servers"(
  "workspace_id",
  "slug",
  "account_alias"
);
CREATE TABLE "llm_usage_events"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "agent_id" varchar,
  "task_id" varchar,
  "purpose" varchar not null,
  "status" varchar not null default 'completed',
  "requested_provider" varchar,
  "requested_model" varchar,
  "resolved_provider" varchar,
  "resolved_model" varchar,
  "provider_generation_id" varchar,
  "prompt_tokens" integer not null default '0',
  "completion_tokens" integer not null default '0',
  "cache_read_tokens" integer not null default '0',
  "cache_write_tokens" integer not null default '0',
  "reasoning_tokens" integer not null default '0',
  "tool_calls_count" integer not null default '0',
  "generation_time_ms" integer,
  "estimated_cost_usd" numeric,
  "actual_cost_usd" numeric,
  "cost_source" varchar not null default 'catalog_estimate',
  "raw_usage" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("agent_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "llm_usage_events_workspace_id_index" on "llm_usage_events"(
  "workspace_id"
);
CREATE INDEX "llm_usage_events_agent_id_index" on "llm_usage_events"(
  "agent_id"
);
CREATE INDEX "llm_usage_events_task_id_index" on "llm_usage_events"(
  "task_id"
);
CREATE INDEX "llm_usage_events_purpose_index" on "llm_usage_events"(
  "purpose"
);
CREATE INDEX "llm_usage_events_status_index" on "llm_usage_events"("status");
CREATE INDEX "llm_usage_events_requested_provider_index" on "llm_usage_events"(
  "requested_provider"
);
CREATE INDEX "llm_usage_events_requested_model_index" on "llm_usage_events"(
  "requested_model"
);
CREATE INDEX "llm_usage_events_resolved_provider_index" on "llm_usage_events"(
  "resolved_provider"
);
CREATE INDEX "llm_usage_events_resolved_model_index" on "llm_usage_events"(
  "resolved_model"
);
CREATE INDEX "llm_usage_events_provider_generation_id_index" on "llm_usage_events"(
  "provider_generation_id"
);
CREATE INDEX "llm_usage_events_occurred_at_index" on "llm_usage_events"(
  "occurred_at"
);
CREATE TABLE "web_usage_events"(
  "id" varchar not null,
  "workspace_id" varchar,
  "agent_id" varchar,
  "user_id" varchar,
  "capability" varchar not null,
  "provider" varchar not null,
  "request_hash" varchar not null,
  "query" text,
  "url" text,
  "final_url" text,
  "status_code" integer,
  "content_type" varchar,
  "result_count" integer,
  "bytes" integer,
  "cache_hit" tinyint(1) not null default '0',
  "success" tinyint(1) not null default '1',
  "error" text,
  "metadata" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete set null,
  foreign key("agent_id") references "users"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "web_usage_events_workspace_id_index" on "web_usage_events"(
  "workspace_id"
);
CREATE INDEX "web_usage_events_agent_id_index" on "web_usage_events"(
  "agent_id"
);
CREATE INDEX "web_usage_events_user_id_index" on "web_usage_events"(
  "user_id"
);
CREATE INDEX "web_usage_events_capability_index" on "web_usage_events"(
  "capability"
);
CREATE INDEX "web_usage_events_provider_index" on "web_usage_events"(
  "provider"
);
CREATE INDEX "web_usage_events_request_hash_index" on "web_usage_events"(
  "request_hash"
);
CREATE INDEX "web_usage_events_cache_hit_index" on "web_usage_events"(
  "cache_hit"
);
CREATE INDEX "web_usage_events_success_index" on "web_usage_events"(
  "success"
);
CREATE INDEX "web_usage_events_occurred_at_index" on "web_usage_events"(
  "occurred_at"
);
CREATE TABLE "telegram_integration_profiles"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "bot_id" varchar,
  "bot_username" varchar,
  "capabilities" text,
  "webhook_url" varchar,
  "webhook_secret_fingerprint" varchar,
  "allowed_updates" text,
  "command_sync_status" varchar not null default 'unknown',
  "profile_sync_status" varchar not null default 'unknown',
  "pending_update_count" integer,
  "default_mode" varchar not null default 'command_center',
  "default_agent_id" varchar,
  "notification_policy" text,
  "health_status" varchar not null default 'unknown',
  "last_health_error" text,
  "last_health_checked_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("integration_setting_id") references "integration_settings"("id") on delete cascade,
  foreign key("default_agent_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE UNIQUE INDEX "telegram_profiles_workspace_setting_unique" on "telegram_integration_profiles"(
  "workspace_id",
  "integration_setting_id"
);
CREATE INDEX "telegram_integration_profiles_workspace_id_index" on "telegram_integration_profiles"(
  "workspace_id"
);
CREATE TABLE "telegram_conversations"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "chat_id" varchar not null,
  "chat_type" varchar not null default 'private',
  "title" varchar,
  "username" varchar,
  "topic_id" varchar,
  "direct_messages_topic_id" varchar,
  "channel_id" varchar,
  "default_agent_id" varchar,
  "mode" varchar not null default 'command_center',
  "observed_context_enabled" tinyint(1) not null default '0',
  "notification_policy" text,
  "last_seen_at" datetime,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("integration_setting_id") references "integration_settings"("id") on delete cascade,
  foreign key("channel_id") references "channels"("id") on delete set null,
  foreign key("default_agent_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE UNIQUE INDEX "telegram_conversations_external_unique" on "telegram_conversations"(
  "integration_setting_id",
  "chat_id",
  "topic_id",
  "direct_messages_topic_id"
);
CREATE INDEX "telegram_conversations_workspace_id_index" on "telegram_conversations"(
  "workspace_id"
);
CREATE TABLE "telegram_update_receipts"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "update_id" varchar not null,
  "update_type" varchar not null default 'unknown',
  "payload_hash" varchar not null,
  "payload" text not null,
  "status" varchar not null default 'received',
  "telegram_conversation_id" varchar,
  "error_class" varchar,
  "error_message" text,
  "retry_count" integer not null default '0',
  "received_at" datetime not null,
  "processed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "duplicate_count" integer not null default '0',
  "last_duplicate_at" datetime,
  "diagnostics" text,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("integration_setting_id") references "integration_settings"("id") on delete cascade,
  foreign key("telegram_conversation_id") references "telegram_conversations"("id") on delete set null,
  primary key("id")
);

CREATE UNIQUE INDEX "telegram_update_receipts_setting_update_unique" on "telegram_update_receipts"(
  "integration_setting_id",
  "update_id"
);
CREATE INDEX "telegram_update_receipts_status_received_at_index" on "telegram_update_receipts"(
  "status",
  "received_at"
);
CREATE INDEX "telegram_update_receipts_workspace_id_index" on "telegram_update_receipts"(
  "workspace_id"
);
CREATE TABLE "telegram_deliveries"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "source_type" varchar,
  "source_id" varchar,
  "chat_id" varchar not null,
  "topic_id" varchar,
  "direct_messages_topic_id" varchar,
  "telegram_message_id" varchar,
  "media_group_id" varchar,
  "parse_mode" varchar,
  "renderer_version" varchar,
  "status" varchar not null default('pending'),
  "previous_delivery_id" varchar,
  "request_payload" text,
  "response_payload" text,
  "provider_error_code" varchar,
  "provider_error_message" text,
  "attempts" integer not null default('0'),
  "sent_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("integration_setting_id") references integration_settings("id") on delete cascade on update no action,
  foreign key("workspace_id") references workspaces("id") on delete cascade on update no action,
  foreign key("previous_delivery_id") references "telegram_deliveries"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "telegram_deliveries_chat_id_topic_id_index" on "telegram_deliveries"(
  "chat_id",
  "topic_id"
);
CREATE INDEX "telegram_deliveries_source_type_source_id_index" on "telegram_deliveries"(
  "source_type",
  "source_id"
);
CREATE INDEX "telegram_deliveries_status_created_at_index" on "telegram_deliveries"(
  "status",
  "created_at"
);
CREATE INDEX "telegram_deliveries_workspace_id_index" on "telegram_deliveries"(
  "workspace_id"
);
CREATE TABLE "telegram_interactions"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "token" varchar not null,
  "interaction_type" varchar not null,
  "target_type" varchar,
  "target_id" varchar,
  "payload" text,
  "allowed_actor_rule" text,
  "expires_at" datetime,
  "resolved_at" datetime,
  "resolved_by_id" varchar,
  "final_status" varchar,
  "payload_checksum" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("integration_setting_id") references "integration_settings"("id") on delete cascade,
  foreign key("resolved_by_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "telegram_interactions_target_type_target_id_index" on "telegram_interactions"(
  "target_type",
  "target_id"
);
CREATE INDEX "telegram_interactions_interaction_type_expires_at_index" on "telegram_interactions"(
  "interaction_type",
  "expires_at"
);
CREATE INDEX "telegram_interactions_workspace_id_index" on "telegram_interactions"(
  "workspace_id"
);
CREATE UNIQUE INDEX "telegram_interactions_token_unique" on "telegram_interactions"(
  "token"
);
CREATE TABLE "telegram_subscriptions"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "scope_type" varchar not null,
  "scope_id" varchar not null,
  "event_type" varchar not null,
  "severity" varchar not null default 'normal',
  "filters" text,
  "schedule" varchar,
  "timezone" varchar not null default 'UTC',
  "enabled" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("integration_setting_id") references "integration_settings"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "telegram_subscriptions_scope_event_unique" on "telegram_subscriptions"(
  "integration_setting_id",
  "scope_type",
  "scope_id",
  "event_type",
  "severity"
);
CREATE INDEX "telegram_subscriptions_workspace_id_index" on "telegram_subscriptions"(
  "workspace_id"
);
CREATE TABLE "telegram_message_mappings"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "integration_setting_id" varchar not null,
  "telegram_conversation_id" varchar not null,
  "channel_id" varchar not null,
  "message_id" varchar not null,
  "chat_id" varchar not null,
  "topic_id" varchar,
  "direct_messages_topic_id" varchar,
  "telegram_message_id" varchar not null,
  "mapping_type" varchar not null default 'primary',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("integration_setting_id") references "integration_settings"("id") on delete cascade,
  foreign key("telegram_conversation_id") references "telegram_conversations"("id") on delete cascade,
  foreign key("channel_id") references "channels"("id") on delete cascade,
  foreign key("message_id") references "messages"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "telegram_message_mappings_external_unique" on "telegram_message_mappings"(
  "integration_setting_id",
  "chat_id",
  "topic_id",
  "direct_messages_topic_id",
  "telegram_message_id"
);
CREATE INDEX "telegram_message_mappings_message_id_mapping_type_index" on "telegram_message_mappings"(
  "message_id",
  "mapping_type"
);
CREATE INDEX "telegram_message_mappings_workspace_id_index" on "telegram_message_mappings"(
  "workspace_id"
);
CREATE TABLE "vfs_operation_events"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "agent_id" varchar not null,
  "surface" varchar not null,
  "operation" varchar not null,
  "path" text,
  "cwd" text,
  "success" tinyint(1) not null default '1',
  "error_code" varchar,
  "duration_ms" integer not null default '0',
  "metadata" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("agent_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "vfs_operation_events_workspace_id_index" on "vfs_operation_events"(
  "workspace_id"
);
CREATE INDEX "vfs_operation_events_agent_id_index" on "vfs_operation_events"(
  "agent_id"
);
CREATE INDEX "vfs_operation_events_surface_index" on "vfs_operation_events"(
  "surface"
);
CREATE INDEX "vfs_operation_events_operation_index" on "vfs_operation_events"(
  "operation"
);
CREATE INDEX "vfs_operation_events_success_index" on "vfs_operation_events"(
  "success"
);
CREATE INDEX "vfs_operation_events_error_code_index" on "vfs_operation_events"(
  "error_code"
);
CREATE INDEX "vfs_operation_events_occurred_at_index" on "vfs_operation_events"(
  "occurred_at"
);
CREATE TABLE "agent_runs"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "task_id" varchar not null,
  "agent_id" varchar,
  "channel_id" varchar,
  "trigger_message_id" varchar,
  "kind" varchar not null,
  "dispatch_key" varchar,
  "status" varchar not null default 'queued',
  "attempt_count" integer not null default '0',
  "max_attempts" integer not null default '3',
  "queue_job_uuid" varchar,
  "cancel_requested_at" datetime,
  "heartbeat_at" datetime,
  "started_at" datetime,
  "finished_at" datetime,
  "last_error" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "causality_id" varchar,
  "causality_depth" integer not null default '0',
  "on_behalf_of_user_id" varchar,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("agent_id") references "users"("id") on delete set null,
  foreign key("channel_id") references "channels"("id") on delete set null,
  foreign key("trigger_message_id") references "messages"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "agent_runs_workspace_id_status_index" on "agent_runs"(
  "workspace_id",
  "status"
);
CREATE INDEX "agent_runs_agent_id_status_index" on "agent_runs"(
  "agent_id",
  "status"
);
CREATE INDEX "agent_runs_queue_job_uuid_index" on "agent_runs"(
  "queue_job_uuid"
);
CREATE INDEX "agent_runs_heartbeat_at_index" on "agent_runs"("heartbeat_at");
CREATE UNIQUE INDEX "agent_runs_task_id_unique" on "agent_runs"("task_id");
CREATE UNIQUE INDEX "agent_runs_dispatch_key_unique" on "agent_runs"(
  "dispatch_key"
);
CREATE TABLE "messages"(
  "id" varchar not null,
  "content" text not null,
  "author_id" varchar not null,
  "channel_id" varchar not null,
  "reply_to_id" varchar,
  "is_approval_request" tinyint(1) not null default('0'),
  "approval_request_id" varchar,
  "is_pinned" tinyint(1) not null default('0'),
  "pinned_by_id" varchar,
  "pinned_at" datetime,
  "timestamp" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  "source" varchar,
  "external_message_id" varchar,
  "agent_run_id" varchar,
  foreign key("reply_to_id") references messages("id") on delete set null on update no action,
  foreign key("author_id") references users("id") on delete no action on update no action,
  foreign key("channel_id") references channels("id") on delete cascade on update no action,
  foreign key("approval_request_id") references approval_requests("id") on delete set null on update no action,
  foreign key("pinned_by_id") references users("id") on delete set null on update no action,
  foreign key("agent_run_id") references "agent_runs"("id") on delete set null,
  primary key("id")
);

CREATE UNIQUE INDEX "messages_agent_run_id_unique" on "messages"(
  "agent_run_id"
);
CREATE UNIQUE INDEX "task_steps_tool_invocation_unique" on "task_steps"(
  "task_id",
  "tool_invocation_id"
);
CREATE TABLE "direct_messages"(
  "id" varchar not null,
  "user1_id" varchar not null,
  "user2_id" varchar not null,
  "channel_id" varchar not null,
  "last_message_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "workspace_id" varchar,
  "participant_key" varchar,
  foreign key("channel_id") references channels("id") on delete no action on update no action,
  foreign key("user2_id") references users("id") on delete no action on update no action,
  foreign key("user1_id") references users("id") on delete no action on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "direct_messages_workspace_pair_unique" on "direct_messages"(
  "workspace_id",
  "participant_key"
);
CREATE INDEX "direct_messages_workspace_id_updated_at_index" on "direct_messages"(
  "workspace_id",
  "updated_at"
);
CREATE TABLE "agent_tool_executions"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "agent_run_id" varchar not null,
  "surface" varchar not null,
  "sequence" integer not null,
  "tool_name" varchar not null,
  "signature" varchar not null,
  "tool_invocation_id" varchar,
  "status" varchar not null default 'running',
  "result_payload" text,
  "error" text,
  "started_at" datetime,
  "finished_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("agent_run_id") references "agent_runs"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "agent_tool_executions_run_surface_sequence_unique" on "agent_tool_executions"(
  "agent_run_id",
  "surface",
  "sequence"
);
CREATE INDEX "agent_tool_executions_workspace_id_status_index" on "agent_tool_executions"(
  "workspace_id",
  "status"
);
CREATE INDEX "agent_tool_executions_agent_run_id_signature_index" on "agent_tool_executions"(
  "agent_run_id",
  "signature"
);
CREATE INDEX "agent_tool_executions_tool_invocation_id_index" on "agent_tool_executions"(
  "tool_invocation_id"
);
CREATE INDEX "vfs_docs_all_page_idx" on "documents"(
  "workspace_id",
  "is_system",
  "is_folder",
  "title",
  "id"
);
CREATE INDEX "vfs_docs_child_page_idx" on "documents"(
  "workspace_id",
  "parent_id",
  "is_system",
  "is_folder",
  "title",
  "id"
);
CREATE INDEX "vfs_files_all_page_idx" on "workspace_files"(
  "workspace_id",
  "is_folder",
  "name",
  "id"
);
CREATE INDEX "vfs_files_child_page_idx" on "workspace_files"(
  "workspace_id",
  "parent_id",
  "is_folder",
  "name",
  "id"
);
CREATE INDEX "vfs_tasks_recent_page_idx" on "tasks"(
  "workspace_id",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_tasks_status_page_idx" on "tasks"(
  "workspace_id",
  "status",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_tasks_agent_page_idx" on "tasks"(
  "workspace_id",
  "agent_id",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_tasks_agent_status_page_idx" on "tasks"(
  "workspace_id",
  "agent_id",
  "status",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_lists_parent_page_idx" on "list_items"(
  "workspace_id",
  "parent_id",
  "is_folder",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_lists_status_page_idx" on "list_items"(
  "workspace_id",
  "status",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_lists_assignee_page_idx" on "list_items"(
  "workspace_id",
  "assignee_id",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_agents_name_page_idx" on "users"(
  "workspace_id",
  "type",
  "name",
  "id"
);
CREATE INDEX "vfs_channels_name_page_idx" on "channels"(
  "workspace_id",
  "name",
  "id"
);
CREATE INDEX "vfs_messages_time_page_idx" on "messages"(
  "channel_id",
  "timestamp",
  "id"
);
CREATE INDEX "vfs_messages_fallback_page_idx" on "messages"(
  "channel_id",
  "created_at",
  "id"
);
CREATE INDEX "vfs_tables_name_page_idx" on "data_tables"(
  "workspace_id",
  "name",
  "id"
);
CREATE INDEX "vfs_table_rows_page_idx" on "data_table_rows"(
  "table_id",
  "updated_at",
  "id"
);
CREATE INDEX "vfs_table_views_page_idx" on "data_table_views"(
  "table_id",
  "created_at",
  "id"
);
CREATE TABLE "approval_requests"(
  "id" varchar not null,
  "type" varchar not null,
  "title" varchar not null,
  "description" text not null,
  "requester_id" varchar not null,
  "amount" numeric,
  "status" varchar not null default('pending'),
  "responded_by_id" varchar,
  "responded_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "tool_execution_context" text,
  "channel_id" varchar,
  "workspace_id" varchar,
  foreign key("channel_id") references channels("id") on delete set null on update no action,
  foreign key("requester_id") references users("id") on delete no action on update no action,
  foreign key("responded_by_id") references users("id") on delete no action on update no action,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "vfs_approvals_page_idx" on "approval_requests"(
  "requester_id",
  "created_at",
  "id"
);
CREATE INDEX "approval_requests_workspace_id_status_created_at_index" on "approval_requests"(
  "workspace_id",
  "status",
  "created_at"
);
CREATE TABLE "policy_grants"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "agent_id" varchar not null,
  "tool_slug" varchar not null,
  "scope" varchar not null,
  "run_id" varchar,
  "task_id" varchar,
  "expires_at" datetime,
  "consumed_at" datetime,
  "revoked_at" datetime,
  "granted_by_id" varchar,
  "reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("agent_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE INDEX "policy_grants_agent_id_tool_slug_index" on "policy_grants"(
  "agent_id",
  "tool_slug"
);
CREATE INDEX "policy_grants_workspace_id_index" on "policy_grants"(
  "workspace_id"
);
CREATE INDEX "policy_grants_agent_id_index" on "policy_grants"("agent_id");
CREATE TABLE "workspace_events"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "type" varchar not null,
  "source" varchar not null,
  "source_ref" varchar,
  "subject" varchar,
  "actor_type" varchar not null,
  "actor_id" varchar,
  "occurred_at" datetime not null,
  "recorded_at" datetime not null,
  "idempotency_key" varchar,
  "payload" text,
  "causality_id" varchar not null,
  "parent_event_id" varchar,
  "depth" integer not null default '0',
  "run_id" varchar,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "workspace_events_workspace_id_idempotency_key_unique" on "workspace_events"(
  "workspace_id",
  "idempotency_key"
);
CREATE INDEX "workspace_events_workspace_id_type_recorded_at_index" on "workspace_events"(
  "workspace_id",
  "type",
  "recorded_at"
);
CREATE INDEX "workspace_events_causality_id_index" on "workspace_events"(
  "causality_id"
);
CREATE TABLE "connections"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "kind" varchar not null,
  "name" varchar not null,
  "ownership" varchar not null,
  "owner_user_id" varchar,
  "status" varchar not null default 'active',
  "settings" text,
  "created_by_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("owner_user_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "connections_workspace_id_kind_index" on "connections"(
  "workspace_id",
  "kind"
);
CREATE TABLE "vault_secrets"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "connection_id" varchar not null,
  "name" varchar not null,
  "ciphertext" text not null,
  "version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("connection_id") references "connections"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "vault_secrets_connection_id_name_unique" on "vault_secrets"(
  "connection_id",
  "name"
);
CREATE TABLE "connection_grants"(
  "id" varchar not null,
  "connection_id" varchar not null,
  "agent_id" varchar not null,
  "mode" varchar not null,
  "granted_by_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("connection_id") references "connections"("id") on delete cascade,
  foreign key("agent_id") references "users"("id") on delete cascade,
  primary key("id")
);

CREATE UNIQUE INDEX "connection_grants_connection_id_agent_id_unique" on "connection_grants"(
  "connection_id",
  "agent_id"
);
CREATE INDEX "tasks_causality_id_index" on "tasks"("causality_id");
CREATE INDEX "agent_runs_causality_id_index" on "agent_runs"("causality_id");
CREATE TABLE "routines"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "name" varchar not null,
  "description" text,
  "kind" varchar not null default 'agent',
  "built_in_key" varchar,
  "agent_id" varchar,
  "instructions" text,
  "script" text,
  "script_runtime" varchar,
  "script_digest" varchar,
  "script_engine_digest" varchar,
  "script_validated_at" datetime,
  "version" integer not null default '1',
  "mode" varchar not null default 'act',
  "notify" varchar not null default 'always',
  "last_output_digest" varchar,
  "cron_expression" varchar not null,
  "timezone" varchar not null default 'UTC',
  "is_active" tinyint(1) not null default '1',
  "last_run_at" datetime,
  "next_run_at" datetime,
  "run_count" integer not null default '0',
  "consecutive_failures" integer not null default '0',
  "channel_id" varchar,
  "keep_history" tinyint(1) not null default '1',
  "created_by_id" varchar,
  "last_result" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("workspace_id") references "workspaces"("id") on delete cascade,
  foreign key("agent_id") references "users"("id") on delete cascade,
  foreign key("channel_id") references "channels"("id") on delete set null,
  foreign key("created_by_id") references "users"("id") on delete set null,
  primary key("id")
);

CREATE INDEX "routines_is_active_next_run_at_index" on "routines"(
  "is_active",
  "next_run_at"
);
CREATE INDEX "vfs_routines_page_idx" on "routines"(
  "workspace_id",
  "created_at",
  "id"
);
CREATE UNIQUE INDEX "routines_workspace_id_built_in_key_unique" on "routines"(
  "workspace_id",
  "built_in_key"
);
CREATE TABLE "routine_script_revisions"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "routine_id" varchar not null,
  "runtime" varchar,
  "source" text not null,
  "source_digest" varchar not null,
  "engine_digest" varchar,
  "status" varchar not null,
  "created_at" datetime not null,
  primary key("id")
);

CREATE UNIQUE INDEX "routine_script_revision_identity" on "routine_script_revisions"(
  "routine_id",
  "source_digest",
  "engine_digest",
  "status"
);
CREATE INDEX "routine_script_revisions_workspace_id_index" on "routine_script_revisions"(
  "workspace_id"
);
CREATE INDEX "routine_script_revisions_routine_id_index" on "routine_script_revisions"(
  "routine_id"
);
CREATE INDEX "agent_runs_on_behalf_of_user_id_index" on "agent_runs"(
  "on_behalf_of_user_id"
);
CREATE TABLE "change_records"(
  "id" varchar not null,
  "workspace_id" varchar not null,
  "run_id" varchar,
  "agent_id" varchar,
  "subject_type" varchar not null,
  "subject_id" varchar not null,
  "operation" varchar not null,
  "before" text,
  "after" text,
  "reverted_at" datetime,
  "reverted_by_id" varchar,
  "created_at" datetime,
  primary key("id")
);

CREATE INDEX "change_records_subject_type_subject_id_index" on "change_records"(
  "subject_type",
  "subject_id"
);
CREATE INDEX "change_records_workspace_id_index" on "change_records"(
  "workspace_id"
);
CREATE INDEX "change_records_run_id_index" on "change_records"("run_id");

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'0001_01_01_000003_create_channels_table',1);
INSERT INTO migrations VALUES(5,'0001_01_01_000004_create_stats_table',1);
INSERT INTO migrations VALUES(6,'0001_01_01_000005_create_activity_steps_table',1);
INSERT INTO migrations VALUES(7,'0001_01_01_000006_create_channel_members_table',1);
INSERT INTO migrations VALUES(8,'0001_01_01_000007_create_approval_requests_table',1);
INSERT INTO migrations VALUES(9,'0001_01_01_000008_create_messages_table',1);
INSERT INTO migrations VALUES(10,'0001_01_01_000009_create_message_reactions_table',1);
INSERT INTO migrations VALUES(11,'0001_01_01_000010_create_message_attachments_table',1);
INSERT INTO migrations VALUES(12,'0001_01_01_000011_create_tasks_table',1);
INSERT INTO migrations VALUES(13,'0001_01_01_000012_create_task_collaborators_table',1);
INSERT INTO migrations VALUES(14,'0001_01_01_000013_create_task_comments_table',1);
INSERT INTO migrations VALUES(15,'0001_01_01_000014_create_task_templates_table',1);
INSERT INTO migrations VALUES(16,'0001_01_01_000015_create_task_automation_rules_table',1);
INSERT INTO migrations VALUES(17,'0001_01_01_000016_create_documents_table',1);
INSERT INTO migrations VALUES(18,'0001_01_01_000017_create_document_permissions_table',1);
INSERT INTO migrations VALUES(19,'0001_01_01_000018_create_document_comments_table',1);
INSERT INTO migrations VALUES(20,'0001_01_01_000019_create_document_versions_table',1);
INSERT INTO migrations VALUES(21,'0001_01_01_000020_create_document_attachments_table',1);
INSERT INTO migrations VALUES(22,'0001_01_01_000021_create_activities_table',1);
INSERT INTO migrations VALUES(23,'0001_01_01_000022_create_notifications_table',1);
INSERT INTO migrations VALUES(24,'0001_01_01_000023_create_credit_transactions_table',1);
INSERT INTO migrations VALUES(25,'0001_01_01_000024_create_direct_messages_table',1);
INSERT INTO migrations VALUES(26,'2022_08_03_000000_create_vector_extension',1);
INSERT INTO migrations VALUES(27,'2025_01_01_000000_create_codex_tokens_table',1);
INSERT INTO migrations VALUES(28,'2026_01_21_000001_fix_messages_and_channel_members_columns',1);
INSERT INTO migrations VALUES(29,'2026_01_21_000002_add_timestamps_to_activities_table',1);
INSERT INTO migrations VALUES(30,'2026_01_30_231819_add_last_message_at_to_channels_table',1);
INSERT INTO migrations VALUES(31,'2026_01_30_234257_add_creator_id_to_tasks_table',1);
INSERT INTO migrations VALUES(32,'2026_01_31_002654_create_calendar_events_table',1);
INSERT INTO migrations VALUES(33,'2026_01_31_002655_create_calendar_event_attendees_table',1);
INSERT INTO migrations VALUES(34,'2026_01_31_002656_create_data_tables_table',1);
INSERT INTO migrations VALUES(35,'2026_01_31_002658_create_data_table_columns_table',1);
INSERT INTO migrations VALUES(36,'2026_01_31_002659_create_data_table_rows_table',1);
INSERT INTO migrations VALUES(37,'2026_01_31_002660_create_data_table_views_table',1);
INSERT INTO migrations VALUES(38,'2026_01_31_003834_make_created_by_nullable_in_data_tables',1);
INSERT INTO migrations VALUES(39,'2026_01_31_132834_add_external_fields_to_channels_table',1);
INSERT INTO migrations VALUES(40,'2026_01_31_132938_update_channels_type_enum',1);
INSERT INTO migrations VALUES(41,'2026_01_31_161928_add_project_support_to_tasks_table',1);
INSERT INTO migrations VALUES(42,'2026_01_31_162944_make_task_assignee_nullable',1);
INSERT INTO migrations VALUES(43,'2026_01_31_172521_remove_credit_system',1);
INSERT INTO migrations VALUES(44,'2026_01_31_172915_rename_is_temporary_to_is_ephemeral',1);
INSERT INTO migrations VALUES(45,'2026_01_31_200000_create_integration_settings_table',1);
INSERT INTO migrations VALUES(46,'2026_01_31_200001_add_brain_and_docs_folder_to_users_table',1);
INSERT INTO migrations VALUES(47,'2026_02_05_195736_create_agent_conversations_table',1);
INSERT INTO migrations VALUES(48,'2026_02_05_220000_rename_tasks_to_list_items',1);
INSERT INTO migrations VALUES(49,'2026_02_05_220001_create_tasks_table',1);
INSERT INTO migrations VALUES(50,'2026_02_05_220002_create_task_steps_table',1);
INSERT INTO migrations VALUES(51,'2026_02_06_000001_create_agent_permissions_table',1);
INSERT INTO migrations VALUES(52,'2026_02_06_000002_add_behavior_mode_to_users_table',1);
INSERT INTO migrations VALUES(53,'2026_02_06_000003_add_tool_context_to_approval_requests',1);
INSERT INTO migrations VALUES(54,'2026_02_06_100000_add_approval_waiting_to_users_table',1);
INSERT INTO migrations VALUES(55,'2026_02_06_100000_remove_agent_channel_type',1);
INSERT INTO migrations VALUES(56,'2026_02_06_130634_add_source_to_tasks_table',1);
INSERT INTO migrations VALUES(57,'2026_02_06_223132_add_is_system_to_documents_table',1);
INSERT INTO migrations VALUES(58,'2026_02_06_230537_add_bootstrapped_at_to_users_table',1);
INSERT INTO migrations VALUES(59,'2026_02_06_232224_drop_unused_tables',1);
INSERT INTO migrations VALUES(60,'2026_02_07_000001_add_sleeping_fields_to_users_table',1);
INSERT INTO migrations VALUES(61,'2026_02_08_100001_add_source_to_messages_table',1);
INSERT INTO migrations VALUES(62,'2026_02_08_150000_update_users_status_check_constraint',1);
INSERT INTO migrations VALUES(63,'2026_02_08_200001_add_calendar_feeds_and_recurrence_end',1);
INSERT INTO migrations VALUES(64,'2026_02_09_100001_add_due_date_to_list_items',1);
INSERT INTO migrations VALUES(65,'2026_02_10_100000_create_ai_gateway_api_keys_table',1);
INSERT INTO migrations VALUES(66,'2026_02_10_100001_create_list_statuses_table',1);
INSERT INTO migrations VALUES(67,'2026_02_10_100002_change_list_items_status_to_string',1);
INSERT INTO migrations VALUES(68,'2026_02_11_100001_add_color_and_icon_to_documents_table',1);
INSERT INTO migrations VALUES(69,'2026_02_11_200001_create_user_external_identities_table',1);
INSERT INTO migrations VALUES(70,'2026_02_12_100001_create_app_settings_table',1);
INSERT INTO migrations VALUES(71,'2026_02_13_100001_add_awaiting_delegation_ids_to_users_table',1);
INSERT INTO migrations VALUES(72,'2026_02_14_100000_create_scheduled_automations_table',1);
INSERT INTO migrations VALUES(73,'2026_02_14_100001_create_mcp_servers_table',1);
INSERT INTO migrations VALUES(74,'2026_02_14_200000_add_keep_history_to_scheduled_automations',1);
INSERT INTO migrations VALUES(75,'2026_02_14_200001_add_external_message_id_to_messages_table',1);
INSERT INTO migrations VALUES(76,'2026_02_15_100001_create_conversation_summaries_table',1);
INSERT INTO migrations VALUES(77,'2026_02_15_100002_add_flush_count_to_conversation_summaries_table',1);
INSERT INTO migrations VALUES(78,'2026_02_16_000001_create_document_chunks_table',1);
INSERT INTO migrations VALUES(79,'2026_02_16_000002_create_embedding_cache_table',1);
INSERT INTO migrations VALUES(80,'2026_02_16_000003_remove_vector_dimension_constraints',1);
INSERT INTO migrations VALUES(81,'2026_02_17_000001_add_search_vector_to_document_chunks',1);
INSERT INTO migrations VALUES(82,'2026_02_18_000001_add_multi_workspace_support',1);
INSERT INTO migrations VALUES(83,'2026_02_18_000002_add_icon_color_to_workspaces',1);
INSERT INTO migrations VALUES(84,'2026_02_20_214031_add_trigger_message_id_to_tasks_table',1);
INSERT INTO migrations VALUES(85,'2026_02_20_220023_add_config_to_data_table_views_table',1);
INSERT INTO migrations VALUES(86,'2026_02_21_144134_rename_scheduled_automations_to_automations_and_add_trigger_type',1);
INSERT INTO migrations VALUES(87,'2026_02_22_211552_migrate_tool_permissions_to_atomic',1);
INSERT INTO migrations VALUES(88,'2026_02_28_153352_add_content_format_to_documents_table',1);
INSERT INTO migrations VALUES(89,'2026_02_28_170000_scope_mcp_servers_slug_to_workspace',1);
INSERT INTO migrations VALUES(90,'2026_02_28_180000_change_task_steps_description_to_text',1);
INSERT INTO migrations VALUES(91,'2026_02_28_190701_create_telescope_entries_table',1);
INSERT INTO migrations VALUES(92,'2026_02_28_220506_add_script_support_to_automations',1);
INSERT INTO migrations VALUES(93,'2026_02_28_225205_make_prompt_nullable_on_automations',1);
INSERT INTO migrations VALUES(94,'2026_03_01_000001_create_workspace_files_table',1);
INSERT INTO migrations VALUES(95,'2026_03_01_000002_add_description_to_workspace_files_table',1);
INSERT INTO migrations VALUES(96,'2026_03_01_000003_create_workspace_disks_table',1);
INSERT INTO migrations VALUES(97,'2026_03_01_000004_add_workspace_disk_id_to_workspace_files',1);
INSERT INTO migrations VALUES(98,'2026_04_05_000001_add_multi_account_to_integration_settings',1);
INSERT INTO migrations VALUES(99,'2026_04_07_000001_refactor_identity_files',1);
INSERT INTO migrations VALUES(100,'2026_04_09_120000_add_compaction_failure_tracking_to_conversation_summaries_table',1);
INSERT INTO migrations VALUES(101,'2026_05_24_000001_create_llm_usage_events_table',1);
INSERT INTO migrations VALUES(102,'2026_05_24_000002_create_integration_webhooks_table',1);
INSERT INTO migrations VALUES(103,'2026_05_24_000003_create_web_usage_events_table',1);
INSERT INTO migrations VALUES(104,'2026_05_24_000004_create_telegram_runtime_tables',1);
INSERT INTO migrations VALUES(105,'2026_05_25_000001_create_telegram_message_mappings_table',1);
INSERT INTO migrations VALUES(106,'2026_05_25_000002_add_telegram_receipt_observability_columns',1);
INSERT INTO migrations VALUES(107,'2026_05_25_120000_add_vfs_trigram_indexes',1);
INSERT INTO migrations VALUES(108,'2026_05_25_130000_create_vfs_operation_events_table',1);
INSERT INTO migrations VALUES(109,'2026_08_09_000001_create_agent_runs_and_scope_direct_messages',1);
INSERT INTO migrations VALUES(110,'2026_08_09_000002_create_agent_tool_executions_table',1);
INSERT INTO migrations VALUES(111,'2026_08_09_000003_add_vfs_pagination_indexes',1);
INSERT INTO migrations VALUES(112,'2026_09_08_000001_pin_mruby_runtime_on_automations',1);
INSERT INTO migrations VALUES(113,'2026_11_02_000001_add_workspace_id_to_automation_rules_and_approvals',1);
INSERT INTO migrations VALUES(114,'2026_11_02_000002_replace_behavior_modes_with_policy_grants',1);
INSERT INTO migrations VALUES(115,'2026_11_02_000003_create_event_log_and_connections',1);
INSERT INTO migrations VALUES(116,'2026_11_02_000004_add_causality_to_tasks_and_runs',1);
INSERT INTO migrations VALUES(117,'2026_11_02_000005_replace_automations_with_routines',1);
INSERT INTO migrations VALUES(118,'2026_11_02_000006_add_governance_presets_principals_and_change_records',1);
