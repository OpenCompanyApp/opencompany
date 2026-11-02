--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: vector; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;


--
-- Name: EXTENSION vector; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION vector IS 'vector data type and ivfflat and hnsw access methods';


--
-- Name: document_chunks_search_vector_update(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.document_chunks_search_vector_update() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                NEW.search_vector := to_tsvector('english', COALESCE(NEW.content, ''));
                RETURN NEW;
            END
            $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activities (
    id character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    description text NOT NULL,
    actor_id character varying(255) NOT NULL,
    metadata json,
    "timestamp" timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL,
    CONSTRAINT activities_type_check CHECK (((type)::text = ANY ((ARRAY['message'::character varying, 'task_completed'::character varying, 'task_started'::character varying, 'agent_spawned'::character varying, 'approval_needed'::character varying, 'approval_granted'::character varying, 'error'::character varying])::text[])))
);


--
-- Name: activity_steps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_steps (
    id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    description text NOT NULL,
    status character varying(255) NOT NULL,
    started_at timestamp(0) without time zone NOT NULL,
    completed_at timestamp(0) without time zone,
    CONSTRAINT activity_steps_status_check CHECK (((status)::text = ANY ((ARRAY['completed'::character varying, 'in_progress'::character varying, 'pending'::character varying])::text[])))
);


--
-- Name: agent_conversation_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_conversation_messages (
    id character varying(36) NOT NULL,
    conversation_id character varying(36) NOT NULL,
    user_id bigint NOT NULL,
    agent character varying(255) NOT NULL,
    role character varying(25) NOT NULL,
    content text NOT NULL,
    attachments text NOT NULL,
    tool_calls text NOT NULL,
    tool_results text NOT NULL,
    usage text NOT NULL,
    meta text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: agent_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_conversations (
    id character varying(36) NOT NULL,
    user_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: agent_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_permissions (
    id character varying(255) NOT NULL,
    agent_id character varying(255) NOT NULL,
    scope_type character varying(255) NOT NULL,
    scope_key character varying(255) NOT NULL,
    permission character varying(255) DEFAULT 'allow'::character varying NOT NULL,
    requires_approval boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: agent_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_runs (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    task_id uuid NOT NULL,
    agent_id character varying(255),
    channel_id character varying(255),
    trigger_message_id character varying(255),
    kind character varying(255) NOT NULL,
    dispatch_key uuid,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    attempt_count integer DEFAULT 0 NOT NULL,
    max_attempts integer DEFAULT 3 NOT NULL,
    queue_job_uuid uuid,
    cancel_requested_at timestamp(0) without time zone,
    heartbeat_at timestamp(0) without time zone,
    started_at timestamp(0) without time zone,
    finished_at timestamp(0) without time zone,
    last_error text,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    causality_id uuid,
    causality_depth smallint DEFAULT '0'::smallint NOT NULL,
    on_behalf_of_user_id character varying(255)
);


--
-- Name: agent_tool_executions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_tool_executions (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    agent_run_id uuid NOT NULL,
    surface character varying(32) NOT NULL,
    sequence integer NOT NULL,
    tool_name character varying(255) NOT NULL,
    signature character(64) NOT NULL,
    tool_invocation_id character varying(255),
    status character varying(255) DEFAULT 'running'::character varying NOT NULL,
    result_payload text,
    error text,
    started_at timestamp(0) without time zone,
    finished_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ai_gateway_api_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_gateway_api_keys (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    key_hash character varying(255) NOT NULL,
    key_prefix character varying(255) NOT NULL,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: app_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_settings (
    id character varying(255) NOT NULL,
    key character varying(255) NOT NULL,
    category character varying(255) NOT NULL,
    value json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: approval_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_requests (
    id character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    description text NOT NULL,
    requester_id character varying(255) NOT NULL,
    amount numeric(12,2),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    responded_by_id character varying(255),
    responded_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tool_execution_context jsonb,
    channel_id character varying(255),
    workspace_id uuid,
    CONSTRAINT approval_requests_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[]))),
    CONSTRAINT approval_requests_type_check CHECK (((type)::text = ANY ((ARRAY['budget'::character varying, 'action'::character varying, 'spawn'::character varying, 'access'::character varying])::text[])))
);


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: calendar_event_attendees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendar_event_attendees (
    id uuid NOT NULL,
    event_id uuid NOT NULL,
    user_id character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT calendar_event_attendees_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'accepted'::character varying, 'declined'::character varying, 'tentative'::character varying])::text[])))
);


--
-- Name: calendar_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendar_events (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    start_at timestamp(0) without time zone NOT NULL,
    end_at timestamp(0) without time zone,
    all_day boolean DEFAULT false NOT NULL,
    location character varying(255),
    color character varying(255),
    recurrence_rule character varying(255),
    created_by character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    recurrence_end timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: calendar_feeds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendar_feeds (
    id uuid NOT NULL,
    user_id character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    name character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: change_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_records (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    run_id uuid,
    agent_id character varying(255),
    subject_type character varying(64) NOT NULL,
    subject_id character varying(255) NOT NULL,
    operation character varying(16) NOT NULL,
    before json,
    after json,
    reverted_at timestamp(0) without time zone,
    reverted_by_id character varying(255),
    created_at timestamp(0) without time zone
);


--
-- Name: channel_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.channel_members (
    id bigint NOT NULL,
    channel_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    role character varying(255) DEFAULT 'member'::character varying NOT NULL,
    unread_count integer DEFAULT 0 NOT NULL,
    joined_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_read_at timestamp(0) without time zone,
    CONSTRAINT channel_members_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'member'::character varying])::text[])))
);


--
-- Name: channel_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.channel_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: channel_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.channel_members_id_seq OWNED BY public.channel_members.id;


--
-- Name: channels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.channels (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'public'::character varying NOT NULL,
    description text,
    creator_id character varying(255),
    is_ephemeral boolean DEFAULT false CONSTRAINT channels_is_temporary_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_message_at timestamp(0) without time zone,
    external_provider character varying(255),
    external_id character varying(255),
    external_config json,
    workspace_id uuid NOT NULL,
    CONSTRAINT channels_type_check CHECK (((type)::text = ANY ((ARRAY['public'::character varying, 'private'::character varying, 'dm'::character varying, 'external'::character varying])::text[])))
);


--
-- Name: codex_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.codex_tokens (
    id bigint NOT NULL,
    access_token text NOT NULL,
    refresh_token text NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    account_id character varying(255),
    email character varying(255),
    token_data text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: codex_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.codex_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: codex_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.codex_tokens_id_seq OWNED BY public.codex_tokens.id;


--
-- Name: connection_grants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.connection_grants (
    id uuid NOT NULL,
    connection_id uuid NOT NULL,
    agent_id character varying(255) NOT NULL,
    mode character varying(8) NOT NULL,
    granted_by_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: connections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.connections (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    kind character varying(64) NOT NULL,
    name character varying(255) NOT NULL,
    ownership character varying(16) NOT NULL,
    owner_user_id character varying(255),
    status character varying(24) DEFAULT 'active'::character varying NOT NULL,
    settings json,
    created_by_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: conversation_summaries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conversation_summaries (
    id uuid NOT NULL,
    channel_id character varying(255) NOT NULL,
    agent_id character varying(255) NOT NULL,
    summary text NOT NULL,
    tokens_before integer DEFAULT 0 NOT NULL,
    tokens_after integer DEFAULT 0 NOT NULL,
    compaction_count integer DEFAULT 0 NOT NULL,
    messages_summarized integer DEFAULT 0 NOT NULL,
    last_message_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    flush_count integer DEFAULT 0 NOT NULL,
    workspace_id uuid NOT NULL,
    compaction_failure_count integer DEFAULT 0 NOT NULL,
    last_compaction_failed_at timestamp(0) without time zone,
    compaction_circuit_open_until timestamp(0) without time zone,
    last_compaction_error text
);


--
-- Name: data_table_columns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_table_columns (
    id uuid NOT NULL,
    table_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    options json,
    "order" integer DEFAULT 0 NOT NULL,
    required boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: data_table_rows; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_table_rows (
    id uuid NOT NULL,
    table_id uuid NOT NULL,
    data json NOT NULL,
    created_by character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: data_table_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_table_views (
    id uuid NOT NULL,
    table_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'grid'::character varying NOT NULL,
    filters json,
    sorts json,
    hidden_columns json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    config json
);


--
-- Name: data_tables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_tables (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    icon character varying(255),
    created_by character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: direct_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.direct_messages (
    id character varying(255) NOT NULL,
    user1_id character varying(255) NOT NULL,
    user2_id character varying(255) NOT NULL,
    channel_id character varying(255) NOT NULL,
    last_message_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid,
    participant_key character varying(255)
);


--
-- Name: document_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_attachments (
    id character varying(255) NOT NULL,
    document_id character varying(255) NOT NULL,
    filename character varying(255) NOT NULL,
    original_name character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    size bigint NOT NULL,
    url character varying(255) NOT NULL,
    uploaded_by_id character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: document_chunks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_chunks (
    id uuid NOT NULL,
    document_id character varying(255) NOT NULL,
    content text NOT NULL,
    content_hash character varying(64) NOT NULL,
    embedding public.vector NOT NULL,
    metadata jsonb,
    collection character varying(255) DEFAULT 'general'::character varying NOT NULL,
    agent_id character varying(255),
    chunk_index integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    search_vector tsvector,
    workspace_id uuid NOT NULL
);


--
-- Name: document_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_comments (
    id character varying(255) NOT NULL,
    document_id character varying(255) NOT NULL,
    author_id character varying(255) NOT NULL,
    content text NOT NULL,
    parent_id character varying(255),
    resolved boolean DEFAULT false NOT NULL,
    resolved_by_id character varying(255),
    resolved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: document_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_permissions (
    id character varying(255) NOT NULL,
    document_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    role character varying(255) NOT NULL,
    CONSTRAINT document_permissions_role_check CHECK (((role)::text = ANY ((ARRAY['viewer'::character varying, 'editor'::character varying])::text[])))
);


--
-- Name: document_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_versions (
    id character varying(255) NOT NULL,
    document_id character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    content text NOT NULL,
    author_id character varying(255) NOT NULL,
    version_number integer NOT NULL,
    change_description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    content_format character varying(10) DEFAULT 'markdown'::character varying NOT NULL
);


--
-- Name: documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.documents (
    id character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    content text NOT NULL,
    author_id character varying(255) NOT NULL,
    parent_id character varying(255),
    is_folder boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_system boolean DEFAULT false NOT NULL,
    color character varying(255),
    icon character varying(255),
    workspace_id uuid NOT NULL,
    content_format character varying(10) DEFAULT 'markdown'::character varying NOT NULL,
    deleted_at timestamp(0) without time zone
);


--
-- Name: embedding_cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.embedding_cache (
    id character varying(64) NOT NULL,
    provider character varying(50) NOT NULL,
    model character varying(100) NOT NULL,
    embedding public.vector NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: integration_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.integration_settings (
    id character varying(255) NOT NULL,
    integration_id character varying(255) NOT NULL,
    config text NOT NULL,
    enabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL,
    account_alias character varying(32) DEFAULT ''::character varying NOT NULL,
    is_default boolean DEFAULT true NOT NULL
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: list_item_collaborators; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_item_collaborators (
    id character varying(255) CONSTRAINT task_collaborators_id_not_null NOT NULL,
    list_item_id character varying(255) CONSTRAINT task_collaborators_task_id_not_null NOT NULL,
    user_id character varying(255) CONSTRAINT task_collaborators_user_id_not_null NOT NULL
);


--
-- Name: list_item_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_item_comments (
    id character varying(255) CONSTRAINT task_comments_id_not_null NOT NULL,
    list_item_id character varying(255) CONSTRAINT task_comments_task_id_not_null NOT NULL,
    author_id character varying(255) CONSTRAINT task_comments_author_id_not_null NOT NULL,
    content text CONSTRAINT task_comments_content_not_null NOT NULL,
    parent_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: list_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_items (
    id character varying(255) CONSTRAINT tasks_id_not_null NOT NULL,
    title character varying(255) CONSTRAINT tasks_title_not_null NOT NULL,
    description text CONSTRAINT tasks_description_not_null NOT NULL,
    status character varying(50) DEFAULT 'backlog'::character varying CONSTRAINT tasks_status_not_null NOT NULL,
    assignee_id character varying(255),
    priority character varying(255) DEFAULT 'medium'::character varying CONSTRAINT tasks_priority_not_null NOT NULL,
    cost numeric(12,2),
    estimated_cost numeric(12,2),
    channel_id character varying(255),
    "position" integer DEFAULT 0 CONSTRAINT tasks_position_not_null NOT NULL,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    creator_id character varying(255),
    parent_id character varying(255),
    is_folder boolean DEFAULT false CONSTRAINT tasks_is_folder_not_null NOT NULL,
    due_date date,
    workspace_id uuid NOT NULL,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT tasks_priority_check CHECK (((priority)::text = ANY ((ARRAY['low'::character varying, 'medium'::character varying, 'high'::character varying, 'urgent'::character varying])::text[]))),
    CONSTRAINT tasks_status_check CHECK (((status)::text = ANY (ARRAY[('backlog'::character varying)::text, ('in_progress'::character varying)::text, ('done'::character varying)::text])))
);


--
-- Name: list_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_statuses (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    color character varying(255) NOT NULL,
    icon character varying(255) NOT NULL,
    is_done boolean DEFAULT false NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL
);


--
-- Name: list_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_templates (
    id character varying(255) CONSTRAINT task_templates_id_not_null NOT NULL,
    name character varying(255) CONSTRAINT task_templates_name_not_null NOT NULL,
    description text,
    default_title character varying(255) CONSTRAINT task_templates_default_title_not_null NOT NULL,
    default_description text,
    default_priority character varying(255) DEFAULT 'medium'::character varying CONSTRAINT task_templates_default_priority_not_null NOT NULL,
    default_assignee_id character varying(255),
    estimated_cost numeric(12,2),
    tags json,
    created_by_id character varying(255) CONSTRAINT task_templates_created_by_id_not_null NOT NULL,
    is_active boolean DEFAULT true CONSTRAINT task_templates_is_active_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL,
    CONSTRAINT task_templates_default_priority_check CHECK (((default_priority)::text = ANY ((ARRAY['low'::character varying, 'medium'::character varying, 'high'::character varying, 'urgent'::character varying])::text[])))
);


--
-- Name: llm_usage_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.llm_usage_events (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    agent_id character varying(255),
    task_id character varying(255),
    purpose character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'completed'::character varying NOT NULL,
    requested_provider character varying(255),
    requested_model character varying(255),
    resolved_provider character varying(255),
    resolved_model character varying(255),
    provider_generation_id character varying(255),
    prompt_tokens bigint DEFAULT '0'::bigint NOT NULL,
    completion_tokens bigint DEFAULT '0'::bigint NOT NULL,
    cache_read_tokens bigint DEFAULT '0'::bigint NOT NULL,
    cache_write_tokens bigint DEFAULT '0'::bigint NOT NULL,
    reasoning_tokens bigint DEFAULT '0'::bigint NOT NULL,
    tool_calls_count bigint DEFAULT '0'::bigint NOT NULL,
    generation_time_ms bigint,
    estimated_cost_usd numeric(14,8),
    actual_cost_usd numeric(14,8),
    cost_source character varying(255) DEFAULT 'catalog_estimate'::character varying NOT NULL,
    raw_usage json,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: mcp_servers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mcp_servers (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    url text NOT NULL,
    auth_type character varying(255) DEFAULT 'none'::character varying NOT NULL,
    auth_config text,
    headers json,
    enabled boolean DEFAULT false NOT NULL,
    discovered_tools json,
    server_info json,
    tools_discovered_at timestamp(0) without time zone,
    timeout integer DEFAULT 30 NOT NULL,
    icon character varying(255) DEFAULT 'ph:plug'::character varying NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL,
    account_alias character varying(32) DEFAULT ''::character varying NOT NULL
);


--
-- Name: message_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_attachments (
    id character varying(255) NOT NULL,
    message_id character varying(255) NOT NULL,
    filename character varying(255) NOT NULL,
    original_name character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    size bigint NOT NULL,
    url character varying(255) NOT NULL,
    uploaded_by_id character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_reactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_reactions (
    id character varying(255) NOT NULL,
    message_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    emoji character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messages (
    id character varying(255) NOT NULL,
    content text NOT NULL,
    author_id character varying(255) NOT NULL,
    channel_id character varying(255) NOT NULL,
    reply_to_id character varying(255),
    is_approval_request boolean DEFAULT false NOT NULL,
    approval_request_id character varying(255),
    is_pinned boolean DEFAULT false NOT NULL,
    pinned_by_id character varying(255),
    pinned_at timestamp(0) without time zone,
    "timestamp" timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source character varying(255),
    external_message_id character varying(255),
    agent_run_id uuid
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    message text NOT NULL,
    user_id character varying(255) NOT NULL,
    is_read boolean DEFAULT false NOT NULL,
    action_url character varying(255),
    actor_id character varying(255),
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    workspace_id uuid NOT NULL,
    CONSTRAINT notifications_type_check CHECK (((type)::text = ANY ((ARRAY['approval'::character varying, 'task'::character varying, 'message'::character varying, 'agent'::character varying, 'system'::character varying, 'mention'::character varying])::text[])))
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: policy_grants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.policy_grants (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    agent_id character varying(255) NOT NULL,
    tool_slug character varying(255) NOT NULL,
    scope character varying(16) NOT NULL,
    run_id uuid,
    task_id uuid,
    expires_at timestamp(0) without time zone,
    consumed_at timestamp(0) without time zone,
    revoked_at timestamp(0) without time zone,
    granted_by_id character varying(255),
    reason character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: routine_script_revisions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.routine_script_revisions (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    routine_id character varying(255) NOT NULL,
    runtime character varying(255),
    source text NOT NULL,
    source_digest character varying(64) NOT NULL,
    engine_digest character varying(64),
    status character varying(255) NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: routines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.routines (
    id character varying(255) NOT NULL,
    workspace_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    kind character varying(16) DEFAULT 'agent'::character varying NOT NULL,
    built_in_key character varying(64),
    agent_id character varying(255),
    instructions text,
    script text,
    script_runtime character varying(255),
    script_digest character varying(64),
    script_engine_digest character varying(64),
    script_validated_at timestamp(0) without time zone,
    version integer DEFAULT 1 NOT NULL,
    mode character varying(16) DEFAULT 'act'::character varying NOT NULL,
    notify character varying(16) DEFAULT 'always'::character varying NOT NULL,
    last_output_digest character varying(64),
    cron_expression character varying(255) NOT NULL,
    timezone character varying(255) DEFAULT 'UTC'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    last_run_at timestamp(0) without time zone,
    next_run_at timestamp(0) without time zone,
    run_count integer DEFAULT 0 NOT NULL,
    consecutive_failures integer DEFAULT 0 NOT NULL,
    channel_id character varying(255),
    keep_history boolean DEFAULT true NOT NULL,
    created_by_id character varying(255),
    last_result json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id character varying(255),
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: task_steps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_steps (
    id uuid NOT NULL,
    task_id uuid NOT NULL,
    description text NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    step_type character varying(255) DEFAULT 'action'::character varying NOT NULL,
    metadata json,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tool_invocation_id character varying(255)
);


--
-- Name: tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tasks (
    id uuid CONSTRAINT tasks_id_not_null1 NOT NULL,
    title character varying(255) CONSTRAINT tasks_title_not_null1 NOT NULL,
    description text,
    type character varying(255) DEFAULT 'custom'::character varying NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying CONSTRAINT tasks_status_not_null1 NOT NULL,
    priority character varying(255) DEFAULT 'normal'::character varying CONSTRAINT tasks_priority_not_null1 NOT NULL,
    agent_id character varying(255),
    requester_id character varying(255) NOT NULL,
    channel_id character varying(255),
    list_item_id character varying(255),
    parent_task_id uuid,
    context json,
    result json,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    due_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source character varying(255),
    workspace_id uuid NOT NULL,
    trigger_message_id character varying(255),
    causality_id uuid,
    causality_depth smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: telegram_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_conversations (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    chat_id character varying(255) NOT NULL,
    chat_type character varying(255) DEFAULT 'private'::character varying NOT NULL,
    title character varying(255),
    username character varying(255),
    topic_id character varying(255),
    direct_messages_topic_id character varying(255),
    channel_id character varying(255),
    default_agent_id character varying(255),
    mode character varying(255) DEFAULT 'command_center'::character varying NOT NULL,
    observed_context_enabled boolean DEFAULT false NOT NULL,
    notification_policy json,
    last_seen_at timestamp(0) without time zone,
    archived_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_deliveries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_deliveries (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    source_type character varying(255),
    source_id uuid,
    chat_id character varying(255) NOT NULL,
    topic_id character varying(255),
    direct_messages_topic_id character varying(255),
    telegram_message_id character varying(255),
    media_group_id character varying(255),
    parse_mode character varying(255),
    renderer_version character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    previous_delivery_id uuid,
    request_payload json,
    response_payload json,
    provider_error_code character varying(255),
    provider_error_message text,
    attempts integer DEFAULT 0 NOT NULL,
    sent_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_integration_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_integration_profiles (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    bot_id character varying(255),
    bot_username character varying(255),
    capabilities json,
    webhook_url character varying(255),
    webhook_secret_fingerprint character varying(255),
    allowed_updates json,
    command_sync_status character varying(255) DEFAULT 'unknown'::character varying NOT NULL,
    profile_sync_status character varying(255) DEFAULT 'unknown'::character varying NOT NULL,
    pending_update_count integer,
    default_mode character varying(255) DEFAULT 'command_center'::character varying NOT NULL,
    default_agent_id character varying(255),
    notification_policy json,
    health_status character varying(255) DEFAULT 'unknown'::character varying NOT NULL,
    last_health_error text,
    last_health_checked_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_interactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_interactions (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    interaction_type character varying(255) NOT NULL,
    target_type character varying(255),
    target_id uuid,
    payload json,
    allowed_actor_rule json,
    expires_at timestamp(0) without time zone,
    resolved_at timestamp(0) without time zone,
    resolved_by_id character varying(255),
    final_status character varying(255),
    payload_checksum character varying(64),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_message_mappings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_message_mappings (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    telegram_conversation_id uuid NOT NULL,
    channel_id character varying(255) NOT NULL,
    message_id character varying(255) NOT NULL,
    chat_id character varying(255) NOT NULL,
    topic_id character varying(255),
    direct_messages_topic_id character varying(255),
    telegram_message_id character varying(255) NOT NULL,
    mapping_type character varying(255) DEFAULT 'primary'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_subscriptions (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    scope_type character varying(255) NOT NULL,
    scope_id character varying(255) NOT NULL,
    event_type character varying(255) NOT NULL,
    severity character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    filters json,
    schedule character varying(255),
    timezone character varying(255) DEFAULT 'UTC'::character varying NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_update_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_update_receipts (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    integration_setting_id character varying(255) NOT NULL,
    update_id character varying(255) NOT NULL,
    update_type character varying(255) DEFAULT 'unknown'::character varying NOT NULL,
    payload_hash character varying(64) NOT NULL,
    payload json NOT NULL,
    status character varying(255) DEFAULT 'received'::character varying NOT NULL,
    telegram_conversation_id uuid,
    error_class character varying(255),
    error_message text,
    retry_count integer DEFAULT 0 NOT NULL,
    received_at timestamp(0) without time zone NOT NULL,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    duplicate_count integer DEFAULT 0 NOT NULL,
    last_duplicate_at timestamp(0) without time zone,
    diagnostics json
);


--
-- Name: telescope_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_entries (
    sequence bigint NOT NULL,
    uuid uuid NOT NULL,
    batch_id uuid NOT NULL,
    family_hash character varying(255),
    should_display_on_index boolean DEFAULT true NOT NULL,
    type character varying(20) NOT NULL,
    content text NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.telescope_entries_sequence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.telescope_entries_sequence_seq OWNED BY public.telescope_entries.sequence;


--
-- Name: telescope_entries_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_entries_tags (
    entry_uuid uuid NOT NULL,
    tag character varying(255) NOT NULL
);


--
-- Name: telescope_monitoring; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telescope_monitoring (
    tag character varying(255) NOT NULL
);


--
-- Name: user_external_identities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_external_identities (
    id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    provider character varying(255) NOT NULL,
    external_id character varying(255) NOT NULL,
    display_name character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    avatar character varying(255),
    type character varying(255) NOT NULL,
    agent_type character varying(255),
    status character varying(255) DEFAULT 'offline'::character varying NOT NULL,
    presence character varying(255) DEFAULT 'offline'::character varying NOT NULL,
    last_seen_at timestamp(0) without time zone,
    current_task character varying(255),
    email character varying(255),
    email_verified_at timestamp(0) without time zone,
    password character varying(255),
    is_ephemeral boolean DEFAULT false CONSTRAINT users_is_temporary_not_null NOT NULL,
    manager_id character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    brain character varying(255),
    docs_folder_id character varying(255),
    awaiting_approval_id character varying(255),
    must_wait_for_approval boolean DEFAULT false NOT NULL,
    bootstrapped_at timestamp(0) without time zone,
    sleeping_until timestamp(0) without time zone,
    sleeping_reason character varying(255),
    awaiting_delegation_ids json,
    workspace_id uuid,
    CONSTRAINT users_presence_check CHECK (((presence)::text = ANY ((ARRAY['online'::character varying, 'away'::character varying, 'busy'::character varying, 'offline'::character varying])::text[]))),
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['idle'::character varying, 'working'::character varying, 'offline'::character varying, 'sleeping'::character varying, 'awaiting_approval'::character varying, 'awaiting_delegation'::character varying])::text[]))),
    CONSTRAINT users_type_check CHECK (((type)::text = ANY ((ARRAY['human'::character varying, 'agent'::character varying])::text[])))
);


--
-- Name: vault_secrets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vault_secrets (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    connection_id uuid NOT NULL,
    name character varying(120) NOT NULL,
    ciphertext text NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vfs_operation_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vfs_operation_events (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    agent_id character varying(255) NOT NULL,
    surface character varying(32) NOT NULL,
    operation character varying(64) NOT NULL,
    path text,
    cwd text,
    success boolean DEFAULT true NOT NULL,
    error_code character varying(255),
    duration_ms integer DEFAULT 0 NOT NULL,
    metadata json,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: web_usage_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.web_usage_events (
    id uuid NOT NULL,
    workspace_id uuid,
    agent_id character varying(255),
    user_id character varying(255),
    capability character varying(32) NOT NULL,
    provider character varying(255) NOT NULL,
    request_hash character varying(255) NOT NULL,
    query text,
    url text,
    final_url text,
    status_code smallint,
    content_type character varying(255),
    result_count integer,
    bytes bigint,
    cache_hit boolean DEFAULT false NOT NULL,
    success boolean DEFAULT true NOT NULL,
    error text,
    metadata json,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: workspace_disks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workspace_disks (
    id character varying(255) NOT NULL,
    workspace_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    driver character varying(255) NOT NULL,
    config text,
    is_default boolean DEFAULT false NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: workspace_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workspace_events (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    type character varying(120) NOT NULL,
    source character varying(120) NOT NULL,
    source_ref character varying(255),
    subject character varying(500),
    actor_type character varying(20) NOT NULL,
    actor_id character varying(255),
    occurred_at timestamp(0) without time zone NOT NULL,
    recorded_at timestamp(0) without time zone NOT NULL,
    idempotency_key character varying(255),
    payload json,
    causality_id uuid NOT NULL,
    parent_event_id uuid,
    depth smallint DEFAULT '0'::smallint NOT NULL,
    run_id uuid
);


--
-- Name: workspace_files; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workspace_files (
    id character varying(255) NOT NULL,
    workspace_id uuid NOT NULL,
    parent_id character varying(255),
    name character varying(255) NOT NULL,
    is_folder boolean DEFAULT false NOT NULL,
    storage_disk character varying(255),
    storage_path character varying(255),
    mime_type character varying(255),
    size bigint,
    owner_id character varying(255) NOT NULL,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    description text,
    workspace_disk_id character varying(255)
);


--
-- Name: workspace_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workspace_invitations (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    email character varying(255) NOT NULL,
    role character varying(255) DEFAULT 'member'::character varying NOT NULL,
    token character varying(64) NOT NULL,
    inviter_id character varying(255) NOT NULL,
    accepted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: workspace_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workspace_members (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    user_id character varying(255) NOT NULL,
    role character varying(255) DEFAULT 'member'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: workspaces; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workspaces (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    owner_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    icon character varying(255) DEFAULT 'ph:buildings'::character varying NOT NULL,
    color character varying(255) DEFAULT 'neutral'::character varying NOT NULL,
    governance_preset character varying(16)
);


--
-- Name: channel_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_members ALTER COLUMN id SET DEFAULT nextval('public.channel_members_id_seq'::regclass);


--
-- Name: codex_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.codex_tokens ALTER COLUMN id SET DEFAULT nextval('public.codex_tokens_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: telescope_entries sequence; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries ALTER COLUMN sequence SET DEFAULT nextval('public.telescope_entries_sequence_seq'::regclass);


--
-- Name: activities activities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activities
    ADD CONSTRAINT activities_pkey PRIMARY KEY (id);


--
-- Name: activity_steps activity_steps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_steps
    ADD CONSTRAINT activity_steps_pkey PRIMARY KEY (id);


--
-- Name: agent_conversation_messages agent_conversation_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_conversation_messages
    ADD CONSTRAINT agent_conversation_messages_pkey PRIMARY KEY (id);


--
-- Name: agent_conversations agent_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_conversations
    ADD CONSTRAINT agent_conversations_pkey PRIMARY KEY (id);


--
-- Name: agent_permissions agent_permissions_agent_id_scope_type_scope_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_permissions
    ADD CONSTRAINT agent_permissions_agent_id_scope_type_scope_key_unique UNIQUE (agent_id, scope_type, scope_key);


--
-- Name: agent_permissions agent_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_permissions
    ADD CONSTRAINT agent_permissions_pkey PRIMARY KEY (id);


--
-- Name: agent_runs agent_runs_dispatch_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_dispatch_key_unique UNIQUE (dispatch_key);


--
-- Name: agent_runs agent_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_pkey PRIMARY KEY (id);


--
-- Name: agent_runs agent_runs_task_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_task_id_unique UNIQUE (task_id);


--
-- Name: agent_tool_executions agent_tool_executions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_tool_executions
    ADD CONSTRAINT agent_tool_executions_pkey PRIMARY KEY (id);


--
-- Name: agent_tool_executions agent_tool_executions_run_surface_sequence_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_tool_executions
    ADD CONSTRAINT agent_tool_executions_run_surface_sequence_unique UNIQUE (agent_run_id, surface, sequence);


--
-- Name: ai_gateway_api_keys ai_gateway_api_keys_key_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_gateway_api_keys
    ADD CONSTRAINT ai_gateway_api_keys_key_hash_unique UNIQUE (key_hash);


--
-- Name: ai_gateway_api_keys ai_gateway_api_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_gateway_api_keys
    ADD CONSTRAINT ai_gateway_api_keys_pkey PRIMARY KEY (id);


--
-- Name: app_settings app_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings
    ADD CONSTRAINT app_settings_pkey PRIMARY KEY (id);


--
-- Name: app_settings app_settings_workspace_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings
    ADD CONSTRAINT app_settings_workspace_id_key_unique UNIQUE (workspace_id, key);


--
-- Name: approval_requests approval_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_requests
    ADD CONSTRAINT approval_requests_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: calendar_event_attendees calendar_event_attendees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_event_attendees
    ADD CONSTRAINT calendar_event_attendees_pkey PRIMARY KEY (id);


--
-- Name: calendar_events calendar_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_events
    ADD CONSTRAINT calendar_events_pkey PRIMARY KEY (id);


--
-- Name: calendar_feeds calendar_feeds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_feeds
    ADD CONSTRAINT calendar_feeds_pkey PRIMARY KEY (id);


--
-- Name: calendar_feeds calendar_feeds_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_feeds
    ADD CONSTRAINT calendar_feeds_token_unique UNIQUE (token);


--
-- Name: change_records change_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_records
    ADD CONSTRAINT change_records_pkey PRIMARY KEY (id);


--
-- Name: channel_members channel_members_channel_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_members
    ADD CONSTRAINT channel_members_channel_id_user_id_unique UNIQUE (channel_id, user_id);


--
-- Name: channel_members channel_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_members
    ADD CONSTRAINT channel_members_pkey PRIMARY KEY (id);


--
-- Name: channels channels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channels
    ADD CONSTRAINT channels_pkey PRIMARY KEY (id);


--
-- Name: codex_tokens codex_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.codex_tokens
    ADD CONSTRAINT codex_tokens_pkey PRIMARY KEY (id);


--
-- Name: connection_grants connection_grants_connection_id_agent_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connection_grants
    ADD CONSTRAINT connection_grants_connection_id_agent_id_unique UNIQUE (connection_id, agent_id);


--
-- Name: connection_grants connection_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connection_grants
    ADD CONSTRAINT connection_grants_pkey PRIMARY KEY (id);


--
-- Name: connections connections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_pkey PRIMARY KEY (id);


--
-- Name: conversation_summaries conversation_summaries_channel_id_agent_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_summaries
    ADD CONSTRAINT conversation_summaries_channel_id_agent_id_unique UNIQUE (channel_id, agent_id);


--
-- Name: conversation_summaries conversation_summaries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_summaries
    ADD CONSTRAINT conversation_summaries_pkey PRIMARY KEY (id);


--
-- Name: data_table_columns data_table_columns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_columns
    ADD CONSTRAINT data_table_columns_pkey PRIMARY KEY (id);


--
-- Name: data_table_rows data_table_rows_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_rows
    ADD CONSTRAINT data_table_rows_pkey PRIMARY KEY (id);


--
-- Name: data_table_views data_table_views_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_views
    ADD CONSTRAINT data_table_views_pkey PRIMARY KEY (id);


--
-- Name: data_tables data_tables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_tables
    ADD CONSTRAINT data_tables_pkey PRIMARY KEY (id);


--
-- Name: direct_messages direct_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_pkey PRIMARY KEY (id);


--
-- Name: direct_messages direct_messages_workspace_pair_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_workspace_pair_unique UNIQUE (workspace_id, participant_key);


--
-- Name: document_attachments document_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_pkey PRIMARY KEY (id);


--
-- Name: document_chunks document_chunks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_chunks
    ADD CONSTRAINT document_chunks_pkey PRIMARY KEY (id);


--
-- Name: document_comments document_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_comments
    ADD CONSTRAINT document_comments_pkey PRIMARY KEY (id);


--
-- Name: document_permissions document_permissions_document_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_permissions
    ADD CONSTRAINT document_permissions_document_id_user_id_unique UNIQUE (document_id, user_id);


--
-- Name: document_permissions document_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_permissions
    ADD CONSTRAINT document_permissions_pkey PRIMARY KEY (id);


--
-- Name: document_versions document_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_pkey PRIMARY KEY (id);


--
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- Name: embedding_cache embedding_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.embedding_cache
    ADD CONSTRAINT embedding_cache_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: integration_settings integration_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_settings
    ADD CONSTRAINT integration_settings_pkey PRIMARY KEY (id);


--
-- Name: integration_settings integration_settings_workspace_id_integration_id_account_alias_; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_settings
    ADD CONSTRAINT integration_settings_workspace_id_integration_id_account_alias_ UNIQUE (workspace_id, integration_id, account_alias);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: list_statuses list_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_statuses
    ADD CONSTRAINT list_statuses_pkey PRIMARY KEY (id);


--
-- Name: list_statuses list_statuses_workspace_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_statuses
    ADD CONSTRAINT list_statuses_workspace_id_slug_unique UNIQUE (workspace_id, slug);


--
-- Name: llm_usage_events llm_usage_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.llm_usage_events
    ADD CONSTRAINT llm_usage_events_pkey PRIMARY KEY (id);


--
-- Name: mcp_servers mcp_servers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_servers
    ADD CONSTRAINT mcp_servers_pkey PRIMARY KEY (id);


--
-- Name: mcp_servers mcp_servers_workspace_id_slug_account_alias_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_servers
    ADD CONSTRAINT mcp_servers_workspace_id_slug_account_alias_unique UNIQUE (workspace_id, slug, account_alias);


--
-- Name: message_attachments message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_pkey PRIMARY KEY (id);


--
-- Name: message_reactions message_reactions_message_id_user_id_emoji_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_message_id_user_id_emoji_unique UNIQUE (message_id, user_id, emoji);


--
-- Name: message_reactions message_reactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_pkey PRIMARY KEY (id);


--
-- Name: messages messages_agent_run_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_agent_run_id_unique UNIQUE (agent_run_id);


--
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: policy_grants policy_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.policy_grants
    ADD CONSTRAINT policy_grants_pkey PRIMARY KEY (id);


--
-- Name: routine_script_revisions routine_script_revision_identity; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routine_script_revisions
    ADD CONSTRAINT routine_script_revision_identity UNIQUE (routine_id, source_digest, engine_digest, status);


--
-- Name: routine_script_revisions routine_script_revisions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routine_script_revisions
    ADD CONSTRAINT routine_script_revisions_pkey PRIMARY KEY (id);


--
-- Name: routines routines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routines
    ADD CONSTRAINT routines_pkey PRIMARY KEY (id);


--
-- Name: routines routines_workspace_id_built_in_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routines
    ADD CONSTRAINT routines_workspace_id_built_in_key_unique UNIQUE (workspace_id, built_in_key);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: list_item_collaborators task_collaborators_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_collaborators
    ADD CONSTRAINT task_collaborators_pkey PRIMARY KEY (id);


--
-- Name: list_item_collaborators task_collaborators_task_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_collaborators
    ADD CONSTRAINT task_collaborators_task_id_user_id_unique UNIQUE (list_item_id, user_id);


--
-- Name: list_item_comments task_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_comments
    ADD CONSTRAINT task_comments_pkey PRIMARY KEY (id);


--
-- Name: task_steps task_steps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_steps
    ADD CONSTRAINT task_steps_pkey PRIMARY KEY (id);


--
-- Name: task_steps task_steps_tool_invocation_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_steps
    ADD CONSTRAINT task_steps_tool_invocation_unique UNIQUE (task_id, tool_invocation_id);


--
-- Name: list_templates task_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_templates
    ADD CONSTRAINT task_templates_pkey PRIMARY KEY (id);


--
-- Name: list_items tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT tasks_pkey PRIMARY KEY (id);


--
-- Name: tasks tasks_pkey1; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_pkey1 PRIMARY KEY (id);


--
-- Name: telegram_conversations telegram_conversations_external_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_conversations
    ADD CONSTRAINT telegram_conversations_external_unique UNIQUE (integration_setting_id, chat_id, topic_id, direct_messages_topic_id);


--
-- Name: telegram_conversations telegram_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_conversations
    ADD CONSTRAINT telegram_conversations_pkey PRIMARY KEY (id);


--
-- Name: telegram_deliveries telegram_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_deliveries
    ADD CONSTRAINT telegram_deliveries_pkey PRIMARY KEY (id);


--
-- Name: telegram_integration_profiles telegram_integration_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_integration_profiles
    ADD CONSTRAINT telegram_integration_profiles_pkey PRIMARY KEY (id);


--
-- Name: telegram_interactions telegram_interactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_interactions
    ADD CONSTRAINT telegram_interactions_pkey PRIMARY KEY (id);


--
-- Name: telegram_interactions telegram_interactions_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_interactions
    ADD CONSTRAINT telegram_interactions_token_unique UNIQUE (token);


--
-- Name: telegram_message_mappings telegram_message_mappings_external_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_external_unique UNIQUE (integration_setting_id, chat_id, topic_id, direct_messages_topic_id, telegram_message_id);


--
-- Name: telegram_message_mappings telegram_message_mappings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_pkey PRIMARY KEY (id);


--
-- Name: telegram_integration_profiles telegram_profiles_workspace_setting_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_integration_profiles
    ADD CONSTRAINT telegram_profiles_workspace_setting_unique UNIQUE (workspace_id, integration_setting_id);


--
-- Name: telegram_subscriptions telegram_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_subscriptions
    ADD CONSTRAINT telegram_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: telegram_subscriptions telegram_subscriptions_scope_event_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_subscriptions
    ADD CONSTRAINT telegram_subscriptions_scope_event_unique UNIQUE (integration_setting_id, scope_type, scope_id, event_type, severity);


--
-- Name: telegram_update_receipts telegram_update_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_update_receipts
    ADD CONSTRAINT telegram_update_receipts_pkey PRIMARY KEY (id);


--
-- Name: telegram_update_receipts telegram_update_receipts_setting_update_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_update_receipts
    ADD CONSTRAINT telegram_update_receipts_setting_update_unique UNIQUE (integration_setting_id, update_id);


--
-- Name: telescope_entries telescope_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_pkey PRIMARY KEY (sequence);


--
-- Name: telescope_entries_tags telescope_entries_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_pkey PRIMARY KEY (entry_uuid, tag);


--
-- Name: telescope_entries telescope_entries_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_uuid_unique UNIQUE (uuid);


--
-- Name: telescope_monitoring telescope_monitoring_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_monitoring
    ADD CONSTRAINT telescope_monitoring_pkey PRIMARY KEY (tag);


--
-- Name: user_external_identities user_external_identities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_external_identities
    ADD CONSTRAINT user_external_identities_pkey PRIMARY KEY (id);


--
-- Name: user_external_identities user_external_identities_provider_external_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_external_identities
    ADD CONSTRAINT user_external_identities_provider_external_id_unique UNIQUE (provider, external_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vault_secrets vault_secrets_connection_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vault_secrets
    ADD CONSTRAINT vault_secrets_connection_id_name_unique UNIQUE (connection_id, name);


--
-- Name: vault_secrets vault_secrets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vault_secrets
    ADD CONSTRAINT vault_secrets_pkey PRIMARY KEY (id);


--
-- Name: vfs_operation_events vfs_operation_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vfs_operation_events
    ADD CONSTRAINT vfs_operation_events_pkey PRIMARY KEY (id);


--
-- Name: web_usage_events web_usage_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.web_usage_events
    ADD CONSTRAINT web_usage_events_pkey PRIMARY KEY (id);


--
-- Name: workspace_files wf_workspace_parent_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_files
    ADD CONSTRAINT wf_workspace_parent_name_unique UNIQUE (workspace_id, parent_id, name);


--
-- Name: workspace_disks workspace_disks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_disks
    ADD CONSTRAINT workspace_disks_pkey PRIMARY KEY (id);


--
-- Name: workspace_disks workspace_disks_workspace_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_disks
    ADD CONSTRAINT workspace_disks_workspace_id_name_unique UNIQUE (workspace_id, name);


--
-- Name: workspace_events workspace_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_events
    ADD CONSTRAINT workspace_events_pkey PRIMARY KEY (id);


--
-- Name: workspace_events workspace_events_workspace_id_idempotency_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_events
    ADD CONSTRAINT workspace_events_workspace_id_idempotency_key_unique UNIQUE (workspace_id, idempotency_key);


--
-- Name: workspace_files workspace_files_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_files
    ADD CONSTRAINT workspace_files_pkey PRIMARY KEY (id);


--
-- Name: workspace_invitations workspace_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_invitations
    ADD CONSTRAINT workspace_invitations_pkey PRIMARY KEY (id);


--
-- Name: workspace_invitations workspace_invitations_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_invitations
    ADD CONSTRAINT workspace_invitations_token_unique UNIQUE (token);


--
-- Name: workspace_members workspace_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_members
    ADD CONSTRAINT workspace_members_pkey PRIMARY KEY (id);


--
-- Name: workspace_members workspace_members_workspace_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_members
    ADD CONSTRAINT workspace_members_workspace_id_user_id_unique UNIQUE (workspace_id, user_id);


--
-- Name: workspaces workspaces_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspaces
    ADD CONSTRAINT workspaces_pkey PRIMARY KEY (id);


--
-- Name: workspaces workspaces_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspaces
    ADD CONSTRAINT workspaces_slug_unique UNIQUE (slug);


--
-- Name: activities_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_workspace_id_index ON public.activities USING btree (workspace_id);


--
-- Name: agent_conversation_messages_conversation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_conversation_messages_conversation_id_index ON public.agent_conversation_messages USING btree (conversation_id);


--
-- Name: agent_conversation_messages_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_conversation_messages_user_id_index ON public.agent_conversation_messages USING btree (user_id);


--
-- Name: agent_conversations_user_id_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_conversations_user_id_updated_at_index ON public.agent_conversations USING btree (user_id, updated_at);


--
-- Name: agent_permissions_agent_id_scope_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_permissions_agent_id_scope_type_index ON public.agent_permissions USING btree (agent_id, scope_type);


--
-- Name: agent_runs_agent_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_runs_agent_id_status_index ON public.agent_runs USING btree (agent_id, status);


--
-- Name: agent_runs_causality_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_runs_causality_id_index ON public.agent_runs USING btree (causality_id);


--
-- Name: agent_runs_heartbeat_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_runs_heartbeat_at_index ON public.agent_runs USING btree (heartbeat_at);


--
-- Name: agent_runs_on_behalf_of_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_runs_on_behalf_of_user_id_index ON public.agent_runs USING btree (on_behalf_of_user_id);


--
-- Name: agent_runs_queue_job_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_runs_queue_job_uuid_index ON public.agent_runs USING btree (queue_job_uuid);


--
-- Name: agent_runs_workspace_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_runs_workspace_id_status_index ON public.agent_runs USING btree (workspace_id, status);


--
-- Name: agent_tool_executions_agent_run_id_signature_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_tool_executions_agent_run_id_signature_index ON public.agent_tool_executions USING btree (agent_run_id, signature);


--
-- Name: agent_tool_executions_tool_invocation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_tool_executions_tool_invocation_id_index ON public.agent_tool_executions USING btree (tool_invocation_id);


--
-- Name: agent_tool_executions_workspace_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_tool_executions_workspace_id_status_index ON public.agent_tool_executions USING btree (workspace_id, status);


--
-- Name: ai_gateway_api_keys_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_gateway_api_keys_workspace_id_index ON public.ai_gateway_api_keys USING btree (workspace_id);


--
-- Name: app_settings_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX app_settings_category_index ON public.app_settings USING btree (category);


--
-- Name: app_settings_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX app_settings_workspace_id_index ON public.app_settings USING btree (workspace_id);


--
-- Name: approval_requests_workspace_id_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_requests_workspace_id_status_created_at_index ON public.approval_requests USING btree (workspace_id, status, created_at);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: calendar_events_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX calendar_events_workspace_id_index ON public.calendar_events USING btree (workspace_id);


--
-- Name: calendar_feeds_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX calendar_feeds_workspace_id_index ON public.calendar_feeds USING btree (workspace_id);


--
-- Name: change_records_run_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_records_run_id_index ON public.change_records USING btree (run_id);


--
-- Name: change_records_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_records_subject_type_subject_id_index ON public.change_records USING btree (subject_type, subject_id);


--
-- Name: change_records_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_records_workspace_id_index ON public.change_records USING btree (workspace_id);


--
-- Name: channels_name_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX channels_name_trgm_idx ON public.channels USING gin (name public.gin_trgm_ops);


--
-- Name: channels_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX channels_workspace_id_index ON public.channels USING btree (workspace_id);


--
-- Name: connections_workspace_id_kind_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX connections_workspace_id_kind_index ON public.connections USING btree (workspace_id, kind);


--
-- Name: conversation_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_index ON public.agent_conversation_messages USING btree (conversation_id, user_id, updated_at);


--
-- Name: conversation_summaries_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_summaries_workspace_id_index ON public.conversation_summaries USING btree (workspace_id);


--
-- Name: data_tables_name_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_tables_name_trgm_idx ON public.data_tables USING gin (name public.gin_trgm_ops);


--
-- Name: data_tables_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_tables_workspace_id_index ON public.data_tables USING btree (workspace_id);


--
-- Name: direct_messages_workspace_id_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX direct_messages_workspace_id_updated_at_index ON public.direct_messages USING btree (workspace_id, updated_at);


--
-- Name: document_chunks_collection_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_chunks_collection_agent_id_index ON public.document_chunks USING btree (collection, agent_id);


--
-- Name: document_chunks_content_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_chunks_content_hash_index ON public.document_chunks USING btree (content_hash);


--
-- Name: document_chunks_document_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_chunks_document_id_index ON public.document_chunks USING btree (document_id);


--
-- Name: document_chunks_search_vector_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_chunks_search_vector_idx ON public.document_chunks USING gin (search_vector);


--
-- Name: document_chunks_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_chunks_workspace_id_index ON public.document_chunks USING btree (workspace_id);


--
-- Name: documents_content_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_content_trgm_idx ON public.documents USING gin (content public.gin_trgm_ops);


--
-- Name: documents_title_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_title_trgm_idx ON public.documents USING gin (title public.gin_trgm_ops);


--
-- Name: documents_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_workspace_id_index ON public.documents USING btree (workspace_id);


--
-- Name: embedding_cache_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX embedding_cache_workspace_id_index ON public.embedding_cache USING btree (workspace_id);


--
-- Name: integration_settings_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX integration_settings_workspace_id_index ON public.integration_settings USING btree (workspace_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: list_items_description_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_items_description_trgm_idx ON public.list_items USING gin (description public.gin_trgm_ops);


--
-- Name: list_items_title_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_items_title_trgm_idx ON public.list_items USING gin (title public.gin_trgm_ops);


--
-- Name: list_items_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_items_workspace_id_index ON public.list_items USING btree (workspace_id);


--
-- Name: list_statuses_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_statuses_workspace_id_index ON public.list_statuses USING btree (workspace_id);


--
-- Name: list_templates_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_templates_workspace_id_index ON public.list_templates USING btree (workspace_id);


--
-- Name: llm_usage_events_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_agent_id_index ON public.llm_usage_events USING btree (agent_id);


--
-- Name: llm_usage_events_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_occurred_at_index ON public.llm_usage_events USING btree (occurred_at);


--
-- Name: llm_usage_events_provider_generation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_provider_generation_id_index ON public.llm_usage_events USING btree (provider_generation_id);


--
-- Name: llm_usage_events_purpose_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_purpose_index ON public.llm_usage_events USING btree (purpose);


--
-- Name: llm_usage_events_requested_model_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_requested_model_index ON public.llm_usage_events USING btree (requested_model);


--
-- Name: llm_usage_events_requested_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_requested_provider_index ON public.llm_usage_events USING btree (requested_provider);


--
-- Name: llm_usage_events_resolved_model_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_resolved_model_index ON public.llm_usage_events USING btree (resolved_model);


--
-- Name: llm_usage_events_resolved_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_resolved_provider_index ON public.llm_usage_events USING btree (resolved_provider);


--
-- Name: llm_usage_events_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_status_index ON public.llm_usage_events USING btree (status);


--
-- Name: llm_usage_events_task_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_task_id_index ON public.llm_usage_events USING btree (task_id);


--
-- Name: llm_usage_events_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX llm_usage_events_workspace_id_index ON public.llm_usage_events USING btree (workspace_id);


--
-- Name: mcp_servers_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mcp_servers_workspace_id_index ON public.mcp_servers USING btree (workspace_id);


--
-- Name: messages_content_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messages_content_trgm_idx ON public.messages USING gin (content public.gin_trgm_ops);


--
-- Name: notifications_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_workspace_id_index ON public.notifications USING btree (workspace_id);


--
-- Name: policy_grants_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX policy_grants_agent_id_index ON public.policy_grants USING btree (agent_id);


--
-- Name: policy_grants_agent_id_tool_slug_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX policy_grants_agent_id_tool_slug_index ON public.policy_grants USING btree (agent_id, tool_slug);


--
-- Name: policy_grants_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX policy_grants_workspace_id_index ON public.policy_grants USING btree (workspace_id);


--
-- Name: routine_script_revisions_routine_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX routine_script_revisions_routine_id_index ON public.routine_script_revisions USING btree (routine_id);


--
-- Name: routine_script_revisions_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX routine_script_revisions_workspace_id_index ON public.routine_script_revisions USING btree (workspace_id);


--
-- Name: routines_is_active_next_run_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX routines_is_active_next_run_at_index ON public.routines USING btree (is_active, next_run_at);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: task_steps_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX task_steps_status_index ON public.task_steps USING btree (status);


--
-- Name: task_steps_step_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX task_steps_step_type_index ON public.task_steps USING btree (step_type);


--
-- Name: tasks_agent_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_agent_id_status_index ON public.tasks USING btree (agent_id, status);


--
-- Name: tasks_causality_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_causality_id_index ON public.tasks USING btree (causality_id);


--
-- Name: tasks_description_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_description_trgm_idx ON public.tasks USING gin (description public.gin_trgm_ops);


--
-- Name: tasks_priority_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_priority_index ON public.tasks USING btree (priority);


--
-- Name: tasks_source_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_source_index ON public.tasks USING btree (source);


--
-- Name: tasks_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_status_index ON public.tasks USING btree (status);


--
-- Name: tasks_title_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_title_trgm_idx ON public.tasks USING gin (title public.gin_trgm_ops);


--
-- Name: tasks_trigger_message_id_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_trigger_message_id_agent_id_index ON public.tasks USING btree (trigger_message_id, agent_id);


--
-- Name: tasks_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_type_index ON public.tasks USING btree (type);


--
-- Name: tasks_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_workspace_id_index ON public.tasks USING btree (workspace_id);


--
-- Name: telegram_conversations_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_conversations_workspace_id_index ON public.telegram_conversations USING btree (workspace_id);


--
-- Name: telegram_deliveries_chat_id_topic_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_deliveries_chat_id_topic_id_index ON public.telegram_deliveries USING btree (chat_id, topic_id);


--
-- Name: telegram_deliveries_source_type_source_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_deliveries_source_type_source_id_index ON public.telegram_deliveries USING btree (source_type, source_id);


--
-- Name: telegram_deliveries_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_deliveries_status_created_at_index ON public.telegram_deliveries USING btree (status, created_at);


--
-- Name: telegram_deliveries_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_deliveries_workspace_id_index ON public.telegram_deliveries USING btree (workspace_id);


--
-- Name: telegram_integration_profiles_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_integration_profiles_workspace_id_index ON public.telegram_integration_profiles USING btree (workspace_id);


--
-- Name: telegram_interactions_interaction_type_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_interactions_interaction_type_expires_at_index ON public.telegram_interactions USING btree (interaction_type, expires_at);


--
-- Name: telegram_interactions_target_type_target_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_interactions_target_type_target_id_index ON public.telegram_interactions USING btree (target_type, target_id);


--
-- Name: telegram_interactions_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_interactions_workspace_id_index ON public.telegram_interactions USING btree (workspace_id);


--
-- Name: telegram_message_mappings_message_id_mapping_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_message_mappings_message_id_mapping_type_index ON public.telegram_message_mappings USING btree (message_id, mapping_type);


--
-- Name: telegram_message_mappings_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_message_mappings_workspace_id_index ON public.telegram_message_mappings USING btree (workspace_id);


--
-- Name: telegram_subscriptions_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_subscriptions_workspace_id_index ON public.telegram_subscriptions USING btree (workspace_id);


--
-- Name: telegram_update_receipts_status_received_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_update_receipts_status_received_at_index ON public.telegram_update_receipts USING btree (status, received_at);


--
-- Name: telegram_update_receipts_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telegram_update_receipts_workspace_id_index ON public.telegram_update_receipts USING btree (workspace_id);


--
-- Name: telescope_entries_batch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_batch_id_index ON public.telescope_entries USING btree (batch_id);


--
-- Name: telescope_entries_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_created_at_index ON public.telescope_entries USING btree (created_at);


--
-- Name: telescope_entries_family_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_family_hash_index ON public.telescope_entries USING btree (family_hash);


--
-- Name: telescope_entries_tags_tag_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_tags_tag_index ON public.telescope_entries_tags USING btree (tag);


--
-- Name: telescope_entries_type_should_display_on_index_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX telescope_entries_type_should_display_on_index_index ON public.telescope_entries USING btree (type, should_display_on_index);


--
-- Name: users_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_workspace_id_index ON public.users USING btree (workspace_id);


--
-- Name: vfs_agents_name_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_agents_name_page_idx ON public.users USING btree (workspace_id, type, name, id);


--
-- Name: vfs_approvals_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_approvals_page_idx ON public.approval_requests USING btree (requester_id, created_at, id);


--
-- Name: vfs_channels_name_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_channels_name_page_idx ON public.channels USING btree (workspace_id, name, id);


--
-- Name: vfs_docs_all_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_docs_all_page_idx ON public.documents USING btree (workspace_id, is_system, is_folder, title, id);


--
-- Name: vfs_docs_child_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_docs_child_page_idx ON public.documents USING btree (workspace_id, parent_id, is_system, is_folder, title, id);


--
-- Name: vfs_files_all_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_files_all_page_idx ON public.workspace_files USING btree (workspace_id, is_folder, name, id);


--
-- Name: vfs_files_child_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_files_child_page_idx ON public.workspace_files USING btree (workspace_id, parent_id, is_folder, name, id);


--
-- Name: vfs_lists_assignee_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_lists_assignee_page_idx ON public.list_items USING btree (workspace_id, assignee_id, updated_at, id);


--
-- Name: vfs_lists_parent_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_lists_parent_page_idx ON public.list_items USING btree (workspace_id, parent_id, is_folder, updated_at, id);


--
-- Name: vfs_lists_status_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_lists_status_page_idx ON public.list_items USING btree (workspace_id, status, updated_at, id);


--
-- Name: vfs_messages_fallback_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_messages_fallback_page_idx ON public.messages USING btree (channel_id, created_at, id);


--
-- Name: vfs_messages_time_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_messages_time_page_idx ON public.messages USING btree (channel_id, "timestamp", id);


--
-- Name: vfs_operation_events_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_agent_id_index ON public.vfs_operation_events USING btree (agent_id);


--
-- Name: vfs_operation_events_error_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_error_code_index ON public.vfs_operation_events USING btree (error_code);


--
-- Name: vfs_operation_events_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_occurred_at_index ON public.vfs_operation_events USING btree (occurred_at);


--
-- Name: vfs_operation_events_operation_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_operation_index ON public.vfs_operation_events USING btree (operation);


--
-- Name: vfs_operation_events_success_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_success_index ON public.vfs_operation_events USING btree (success);


--
-- Name: vfs_operation_events_surface_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_surface_index ON public.vfs_operation_events USING btree (surface);


--
-- Name: vfs_operation_events_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_operation_events_workspace_id_index ON public.vfs_operation_events USING btree (workspace_id);


--
-- Name: vfs_routines_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_routines_page_idx ON public.routines USING btree (workspace_id, created_at, id);


--
-- Name: vfs_table_rows_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_table_rows_page_idx ON public.data_table_rows USING btree (table_id, updated_at, id);


--
-- Name: vfs_table_views_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_table_views_page_idx ON public.data_table_views USING btree (table_id, created_at, id);


--
-- Name: vfs_tables_name_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_tables_name_page_idx ON public.data_tables USING btree (workspace_id, name, id);


--
-- Name: vfs_tasks_agent_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_tasks_agent_page_idx ON public.tasks USING btree (workspace_id, agent_id, updated_at, id);


--
-- Name: vfs_tasks_agent_status_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_tasks_agent_status_page_idx ON public.tasks USING btree (workspace_id, agent_id, status, updated_at, id);


--
-- Name: vfs_tasks_recent_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_tasks_recent_page_idx ON public.tasks USING btree (workspace_id, updated_at, id);


--
-- Name: vfs_tasks_status_page_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vfs_tasks_status_page_idx ON public.tasks USING btree (workspace_id, status, updated_at, id);


--
-- Name: web_usage_events_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_agent_id_index ON public.web_usage_events USING btree (agent_id);


--
-- Name: web_usage_events_cache_hit_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_cache_hit_index ON public.web_usage_events USING btree (cache_hit);


--
-- Name: web_usage_events_capability_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_capability_index ON public.web_usage_events USING btree (capability);


--
-- Name: web_usage_events_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_occurred_at_index ON public.web_usage_events USING btree (occurred_at);


--
-- Name: web_usage_events_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_provider_index ON public.web_usage_events USING btree (provider);


--
-- Name: web_usage_events_request_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_request_hash_index ON public.web_usage_events USING btree (request_hash);


--
-- Name: web_usage_events_success_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_success_index ON public.web_usage_events USING btree (success);


--
-- Name: web_usage_events_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_user_id_index ON public.web_usage_events USING btree (user_id);


--
-- Name: web_usage_events_workspace_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX web_usage_events_workspace_id_index ON public.web_usage_events USING btree (workspace_id);


--
-- Name: workspace_disks_workspace_id_is_default_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_disks_workspace_id_is_default_index ON public.workspace_disks USING btree (workspace_id, is_default);


--
-- Name: workspace_events_causality_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_events_causality_id_index ON public.workspace_events USING btree (causality_id);


--
-- Name: workspace_events_workspace_id_type_recorded_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_events_workspace_id_type_recorded_at_index ON public.workspace_events USING btree (workspace_id, type, recorded_at);


--
-- Name: workspace_files_description_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_files_description_trgm_idx ON public.workspace_files USING gin (description public.gin_trgm_ops);


--
-- Name: workspace_files_name_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_files_name_trgm_idx ON public.workspace_files USING gin (name public.gin_trgm_ops);


--
-- Name: workspace_files_workspace_disk_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_files_workspace_disk_id_index ON public.workspace_files USING btree (workspace_disk_id);


--
-- Name: workspace_files_workspace_id_owner_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_files_workspace_id_owner_id_index ON public.workspace_files USING btree (workspace_id, owner_id);


--
-- Name: workspace_files_workspace_id_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_files_workspace_id_parent_id_index ON public.workspace_files USING btree (workspace_id, parent_id);


--
-- Name: workspace_invitations_workspace_id_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_invitations_workspace_id_email_index ON public.workspace_invitations USING btree (workspace_id, email);


--
-- Name: workspace_members_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX workspace_members_user_id_index ON public.workspace_members USING btree (user_id);


--
-- Name: document_chunks document_chunks_search_vector_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER document_chunks_search_vector_trigger BEFORE INSERT OR UPDATE OF content ON public.document_chunks FOR EACH ROW EXECUTE FUNCTION public.document_chunks_search_vector_update();


--
-- Name: activities activities_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activities
    ADD CONSTRAINT activities_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id);


--
-- Name: activities activities_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activities
    ADD CONSTRAINT activities_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: activity_steps activity_steps_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_steps
    ADD CONSTRAINT activity_steps_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: agent_permissions agent_permissions_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_permissions
    ADD CONSTRAINT agent_permissions_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: agent_runs agent_runs_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: agent_runs agent_runs_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE SET NULL;


--
-- Name: agent_runs agent_runs_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- Name: agent_runs agent_runs_trigger_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_trigger_message_id_foreign FOREIGN KEY (trigger_message_id) REFERENCES public.messages(id) ON DELETE SET NULL;


--
-- Name: agent_runs agent_runs_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_runs
    ADD CONSTRAINT agent_runs_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: agent_tool_executions agent_tool_executions_agent_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_tool_executions
    ADD CONSTRAINT agent_tool_executions_agent_run_id_foreign FOREIGN KEY (agent_run_id) REFERENCES public.agent_runs(id) ON DELETE CASCADE;


--
-- Name: agent_tool_executions agent_tool_executions_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_tool_executions
    ADD CONSTRAINT agent_tool_executions_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: ai_gateway_api_keys ai_gateway_api_keys_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_gateway_api_keys
    ADD CONSTRAINT ai_gateway_api_keys_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: app_settings app_settings_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings
    ADD CONSTRAINT app_settings_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: approval_requests approval_requests_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_requests
    ADD CONSTRAINT approval_requests_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE SET NULL;


--
-- Name: approval_requests approval_requests_requester_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_requests
    ADD CONSTRAINT approval_requests_requester_id_foreign FOREIGN KEY (requester_id) REFERENCES public.users(id);


--
-- Name: approval_requests approval_requests_responded_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_requests
    ADD CONSTRAINT approval_requests_responded_by_id_foreign FOREIGN KEY (responded_by_id) REFERENCES public.users(id);


--
-- Name: approval_requests approval_requests_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_requests
    ADD CONSTRAINT approval_requests_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: calendar_event_attendees calendar_event_attendees_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_event_attendees
    ADD CONSTRAINT calendar_event_attendees_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.calendar_events(id) ON DELETE CASCADE;


--
-- Name: calendar_event_attendees calendar_event_attendees_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_event_attendees
    ADD CONSTRAINT calendar_event_attendees_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: calendar_events calendar_events_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_events
    ADD CONSTRAINT calendar_events_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: calendar_events calendar_events_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_events
    ADD CONSTRAINT calendar_events_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: calendar_feeds calendar_feeds_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_feeds
    ADD CONSTRAINT calendar_feeds_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: calendar_feeds calendar_feeds_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_feeds
    ADD CONSTRAINT calendar_feeds_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: channel_members channel_members_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_members
    ADD CONSTRAINT channel_members_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: channel_members channel_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_members
    ADD CONSTRAINT channel_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: channels channels_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channels
    ADD CONSTRAINT channels_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: channels channels_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channels
    ADD CONSTRAINT channels_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: connection_grants connection_grants_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connection_grants
    ADD CONSTRAINT connection_grants_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: connection_grants connection_grants_connection_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connection_grants
    ADD CONSTRAINT connection_grants_connection_id_foreign FOREIGN KEY (connection_id) REFERENCES public.connections(id) ON DELETE CASCADE;


--
-- Name: connections connections_owner_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_owner_user_id_foreign FOREIGN KEY (owner_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: connections connections_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: conversation_summaries conversation_summaries_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_summaries
    ADD CONSTRAINT conversation_summaries_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: conversation_summaries conversation_summaries_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_summaries
    ADD CONSTRAINT conversation_summaries_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: conversation_summaries conversation_summaries_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_summaries
    ADD CONSTRAINT conversation_summaries_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: data_table_columns data_table_columns_table_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_columns
    ADD CONSTRAINT data_table_columns_table_id_foreign FOREIGN KEY (table_id) REFERENCES public.data_tables(id) ON DELETE CASCADE;


--
-- Name: data_table_rows data_table_rows_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_rows
    ADD CONSTRAINT data_table_rows_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_table_rows data_table_rows_table_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_rows
    ADD CONSTRAINT data_table_rows_table_id_foreign FOREIGN KEY (table_id) REFERENCES public.data_tables(id) ON DELETE CASCADE;


--
-- Name: data_table_views data_table_views_table_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_table_views
    ADD CONSTRAINT data_table_views_table_id_foreign FOREIGN KEY (table_id) REFERENCES public.data_tables(id) ON DELETE CASCADE;


--
-- Name: data_tables data_tables_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_tables
    ADD CONSTRAINT data_tables_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_tables data_tables_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_tables
    ADD CONSTRAINT data_tables_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: direct_messages direct_messages_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id);


--
-- Name: direct_messages direct_messages_user1_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_user1_id_foreign FOREIGN KEY (user1_id) REFERENCES public.users(id);


--
-- Name: direct_messages direct_messages_user2_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_user2_id_foreign FOREIGN KEY (user2_id) REFERENCES public.users(id);


--
-- Name: direct_messages direct_messages_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: document_attachments document_attachments_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_attachments document_attachments_uploaded_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_uploaded_by_id_foreign FOREIGN KEY (uploaded_by_id) REFERENCES public.users(id);


--
-- Name: document_chunks document_chunks_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_chunks
    ADD CONSTRAINT document_chunks_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_chunks document_chunks_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_chunks
    ADD CONSTRAINT document_chunks_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_chunks document_chunks_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_chunks
    ADD CONSTRAINT document_chunks_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: document_comments document_comments_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_comments
    ADD CONSTRAINT document_comments_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: document_comments document_comments_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_comments
    ADD CONSTRAINT document_comments_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_comments document_comments_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_comments
    ADD CONSTRAINT document_comments_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.document_comments(id) ON DELETE CASCADE;


--
-- Name: document_comments document_comments_resolved_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_comments
    ADD CONSTRAINT document_comments_resolved_by_id_foreign FOREIGN KEY (resolved_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: document_permissions document_permissions_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_permissions
    ADD CONSTRAINT document_permissions_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_permissions document_permissions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_permissions
    ADD CONSTRAINT document_permissions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: document_versions document_versions_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: document_versions document_versions_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_versions
    ADD CONSTRAINT document_versions_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: documents documents_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: documents documents_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: documents documents_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: embedding_cache embedding_cache_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.embedding_cache
    ADD CONSTRAINT embedding_cache_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: integration_settings integration_settings_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_settings
    ADD CONSTRAINT integration_settings_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: list_items list_items_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT list_items_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: list_statuses list_statuses_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_statuses
    ADD CONSTRAINT list_statuses_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: list_templates list_templates_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_templates
    ADD CONSTRAINT list_templates_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: llm_usage_events llm_usage_events_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.llm_usage_events
    ADD CONSTRAINT llm_usage_events_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: llm_usage_events llm_usage_events_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.llm_usage_events
    ADD CONSTRAINT llm_usage_events_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: mcp_servers mcp_servers_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_servers
    ADD CONSTRAINT mcp_servers_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: message_attachments message_attachments_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: message_attachments message_attachments_uploaded_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_uploaded_by_id_foreign FOREIGN KEY (uploaded_by_id) REFERENCES public.users(id);


--
-- Name: message_reactions message_reactions_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: message_reactions message_reactions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: messages messages_agent_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_agent_run_id_foreign FOREIGN KEY (agent_run_id) REFERENCES public.agent_runs(id) ON DELETE SET NULL;


--
-- Name: messages messages_approval_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_approval_request_id_foreign FOREIGN KEY (approval_request_id) REFERENCES public.approval_requests(id) ON DELETE SET NULL;


--
-- Name: messages messages_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: messages messages_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: messages messages_pinned_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pinned_by_id_foreign FOREIGN KEY (pinned_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: messages messages_reply_to_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_reply_to_id_foreign FOREIGN KEY (reply_to_id) REFERENCES public.messages(id) ON DELETE SET NULL;


--
-- Name: notifications notifications_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: notifications notifications_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: notifications notifications_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: policy_grants policy_grants_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.policy_grants
    ADD CONSTRAINT policy_grants_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: policy_grants policy_grants_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.policy_grants
    ADD CONSTRAINT policy_grants_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: routines routines_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routines
    ADD CONSTRAINT routines_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: routines routines_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routines
    ADD CONSTRAINT routines_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE SET NULL;


--
-- Name: routines routines_created_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routines
    ADD CONSTRAINT routines_created_by_id_foreign FOREIGN KEY (created_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: routines routines_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routines
    ADD CONSTRAINT routines_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: list_item_collaborators task_collaborators_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_collaborators
    ADD CONSTRAINT task_collaborators_task_id_foreign FOREIGN KEY (list_item_id) REFERENCES public.list_items(id) ON DELETE CASCADE;


--
-- Name: list_item_collaborators task_collaborators_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_collaborators
    ADD CONSTRAINT task_collaborators_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: list_item_comments task_comments_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_comments
    ADD CONSTRAINT task_comments_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id);


--
-- Name: list_item_comments task_comments_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_comments
    ADD CONSTRAINT task_comments_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.list_item_comments(id) ON DELETE CASCADE;


--
-- Name: list_item_comments task_comments_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_item_comments
    ADD CONSTRAINT task_comments_task_id_foreign FOREIGN KEY (list_item_id) REFERENCES public.list_items(id) ON DELETE CASCADE;


--
-- Name: task_steps task_steps_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_steps
    ADD CONSTRAINT task_steps_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- Name: list_templates task_templates_created_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_templates
    ADD CONSTRAINT task_templates_created_by_id_foreign FOREIGN KEY (created_by_id) REFERENCES public.users(id);


--
-- Name: list_templates task_templates_default_assignee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_templates
    ADD CONSTRAINT task_templates_default_assignee_id_foreign FOREIGN KEY (default_assignee_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: list_items tasks_assignee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT tasks_assignee_id_foreign FOREIGN KEY (assignee_id) REFERENCES public.users(id);


--
-- Name: list_items tasks_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT tasks_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE SET NULL;


--
-- Name: list_items tasks_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT tasks_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_list_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_list_item_id_foreign FOREIGN KEY (list_item_id) REFERENCES public.list_items(id) ON DELETE SET NULL;


--
-- Name: list_items tasks_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT tasks_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.list_items(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_parent_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_parent_task_id_foreign FOREIGN KEY (parent_task_id) REFERENCES public.tasks(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_requester_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_requester_id_foreign FOREIGN KEY (requester_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: tasks tasks_trigger_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_trigger_message_id_foreign FOREIGN KEY (trigger_message_id) REFERENCES public.messages(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_conversations telegram_conversations_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_conversations
    ADD CONSTRAINT telegram_conversations_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE SET NULL;


--
-- Name: telegram_conversations telegram_conversations_default_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_conversations
    ADD CONSTRAINT telegram_conversations_default_agent_id_foreign FOREIGN KEY (default_agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: telegram_conversations telegram_conversations_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_conversations
    ADD CONSTRAINT telegram_conversations_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_conversations telegram_conversations_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_conversations
    ADD CONSTRAINT telegram_conversations_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_deliveries telegram_deliveries_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_deliveries
    ADD CONSTRAINT telegram_deliveries_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_deliveries telegram_deliveries_previous_delivery_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_deliveries
    ADD CONSTRAINT telegram_deliveries_previous_delivery_id_foreign FOREIGN KEY (previous_delivery_id) REFERENCES public.telegram_deliveries(id) ON DELETE SET NULL;


--
-- Name: telegram_deliveries telegram_deliveries_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_deliveries
    ADD CONSTRAINT telegram_deliveries_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_integration_profiles telegram_integration_profiles_default_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_integration_profiles
    ADD CONSTRAINT telegram_integration_profiles_default_agent_id_foreign FOREIGN KEY (default_agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: telegram_integration_profiles telegram_integration_profiles_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_integration_profiles
    ADD CONSTRAINT telegram_integration_profiles_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_integration_profiles telegram_integration_profiles_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_integration_profiles
    ADD CONSTRAINT telegram_integration_profiles_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_interactions telegram_interactions_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_interactions
    ADD CONSTRAINT telegram_interactions_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_interactions telegram_interactions_resolved_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_interactions
    ADD CONSTRAINT telegram_interactions_resolved_by_id_foreign FOREIGN KEY (resolved_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: telegram_interactions telegram_interactions_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_interactions
    ADD CONSTRAINT telegram_interactions_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_message_mappings telegram_message_mappings_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: telegram_message_mappings telegram_message_mappings_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_message_mappings telegram_message_mappings_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: telegram_message_mappings telegram_message_mappings_telegram_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_telegram_conversation_id_foreign FOREIGN KEY (telegram_conversation_id) REFERENCES public.telegram_conversations(id) ON DELETE CASCADE;


--
-- Name: telegram_message_mappings telegram_message_mappings_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_message_mappings
    ADD CONSTRAINT telegram_message_mappings_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_subscriptions telegram_subscriptions_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_subscriptions
    ADD CONSTRAINT telegram_subscriptions_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_subscriptions telegram_subscriptions_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_subscriptions
    ADD CONSTRAINT telegram_subscriptions_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telegram_update_receipts telegram_update_receipts_integration_setting_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_update_receipts
    ADD CONSTRAINT telegram_update_receipts_integration_setting_id_foreign FOREIGN KEY (integration_setting_id) REFERENCES public.integration_settings(id) ON DELETE CASCADE;


--
-- Name: telegram_update_receipts telegram_update_receipts_telegram_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_update_receipts
    ADD CONSTRAINT telegram_update_receipts_telegram_conversation_id_foreign FOREIGN KEY (telegram_conversation_id) REFERENCES public.telegram_conversations(id) ON DELETE SET NULL;


--
-- Name: telegram_update_receipts telegram_update_receipts_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_update_receipts
    ADD CONSTRAINT telegram_update_receipts_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: telescope_entries_tags telescope_entries_tags_entry_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_entry_uuid_foreign FOREIGN KEY (entry_uuid) REFERENCES public.telescope_entries(uuid) ON DELETE CASCADE;


--
-- Name: user_external_identities user_external_identities_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_external_identities
    ADD CONSTRAINT user_external_identities_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_awaiting_approval_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_awaiting_approval_id_foreign FOREIGN KEY (awaiting_approval_id) REFERENCES public.approval_requests(id) ON DELETE SET NULL;


--
-- Name: users users_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: users users_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE SET NULL;


--
-- Name: vault_secrets vault_secrets_connection_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vault_secrets
    ADD CONSTRAINT vault_secrets_connection_id_foreign FOREIGN KEY (connection_id) REFERENCES public.connections(id) ON DELETE CASCADE;


--
-- Name: vault_secrets vault_secrets_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vault_secrets
    ADD CONSTRAINT vault_secrets_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: vfs_operation_events vfs_operation_events_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vfs_operation_events
    ADD CONSTRAINT vfs_operation_events_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: vfs_operation_events vfs_operation_events_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vfs_operation_events
    ADD CONSTRAINT vfs_operation_events_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: web_usage_events web_usage_events_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.web_usage_events
    ADD CONSTRAINT web_usage_events_agent_id_foreign FOREIGN KEY (agent_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: web_usage_events web_usage_events_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.web_usage_events
    ADD CONSTRAINT web_usage_events_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: web_usage_events web_usage_events_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.web_usage_events
    ADD CONSTRAINT web_usage_events_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE SET NULL;


--
-- Name: workspace_disks workspace_disks_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_disks
    ADD CONSTRAINT workspace_disks_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: workspace_events workspace_events_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_events
    ADD CONSTRAINT workspace_events_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: workspace_files workspace_files_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_files
    ADD CONSTRAINT workspace_files_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id);


--
-- Name: workspace_files workspace_files_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_files
    ADD CONSTRAINT workspace_files_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.workspace_files(id) ON DELETE CASCADE;


--
-- Name: workspace_files workspace_files_workspace_disk_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_files
    ADD CONSTRAINT workspace_files_workspace_disk_id_foreign FOREIGN KEY (workspace_disk_id) REFERENCES public.workspace_disks(id) ON DELETE SET NULL;


--
-- Name: workspace_files workspace_files_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_files
    ADD CONSTRAINT workspace_files_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: workspace_invitations workspace_invitations_inviter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_invitations
    ADD CONSTRAINT workspace_invitations_inviter_id_foreign FOREIGN KEY (inviter_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: workspace_invitations workspace_invitations_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_invitations
    ADD CONSTRAINT workspace_invitations_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: workspace_members workspace_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_members
    ADD CONSTRAINT workspace_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: workspace_members workspace_members_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspace_members
    ADD CONSTRAINT workspace_members_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: workspaces workspaces_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workspaces
    ADD CONSTRAINT workspaces_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	0001_01_01_000003_create_channels_table	1
5	0001_01_01_000004_create_stats_table	1
6	0001_01_01_000005_create_activity_steps_table	1
7	0001_01_01_000006_create_channel_members_table	1
8	0001_01_01_000007_create_approval_requests_table	1
9	0001_01_01_000008_create_messages_table	1
10	0001_01_01_000009_create_message_reactions_table	1
11	0001_01_01_000010_create_message_attachments_table	1
12	0001_01_01_000011_create_tasks_table	1
13	0001_01_01_000012_create_task_collaborators_table	1
14	0001_01_01_000013_create_task_comments_table	1
15	0001_01_01_000014_create_task_templates_table	1
16	0001_01_01_000015_create_task_automation_rules_table	1
17	0001_01_01_000016_create_documents_table	1
18	0001_01_01_000017_create_document_permissions_table	1
19	0001_01_01_000018_create_document_comments_table	1
20	0001_01_01_000019_create_document_versions_table	1
21	0001_01_01_000020_create_document_attachments_table	1
22	0001_01_01_000021_create_activities_table	1
23	0001_01_01_000022_create_notifications_table	1
24	0001_01_01_000023_create_credit_transactions_table	1
25	0001_01_01_000024_create_direct_messages_table	1
26	2022_08_03_000000_create_vector_extension	1
27	2025_01_01_000000_create_codex_tokens_table	1
28	2026_01_21_000001_fix_messages_and_channel_members_columns	1
29	2026_01_21_000002_add_timestamps_to_activities_table	1
30	2026_01_30_231819_add_last_message_at_to_channels_table	1
31	2026_01_30_234257_add_creator_id_to_tasks_table	1
32	2026_01_31_002654_create_calendar_events_table	1
33	2026_01_31_002655_create_calendar_event_attendees_table	1
34	2026_01_31_002656_create_data_tables_table	1
35	2026_01_31_002658_create_data_table_columns_table	1
36	2026_01_31_002659_create_data_table_rows_table	1
37	2026_01_31_002660_create_data_table_views_table	1
38	2026_01_31_003834_make_created_by_nullable_in_data_tables	1
39	2026_01_31_132834_add_external_fields_to_channels_table	1
40	2026_01_31_132938_update_channels_type_enum	1
41	2026_01_31_161928_add_project_support_to_tasks_table	1
42	2026_01_31_162944_make_task_assignee_nullable	1
43	2026_01_31_172521_remove_credit_system	1
44	2026_01_31_172915_rename_is_temporary_to_is_ephemeral	1
45	2026_01_31_200000_create_integration_settings_table	1
46	2026_01_31_200001_add_brain_and_docs_folder_to_users_table	1
47	2026_02_05_195736_create_agent_conversations_table	1
48	2026_02_05_220000_rename_tasks_to_list_items	1
49	2026_02_05_220001_create_tasks_table	1
50	2026_02_05_220002_create_task_steps_table	1
51	2026_02_06_000001_create_agent_permissions_table	1
52	2026_02_06_000002_add_behavior_mode_to_users_table	1
53	2026_02_06_000003_add_tool_context_to_approval_requests	1
54	2026_02_06_100000_add_approval_waiting_to_users_table	1
55	2026_02_06_100000_remove_agent_channel_type	1
56	2026_02_06_130634_add_source_to_tasks_table	1
57	2026_02_06_223132_add_is_system_to_documents_table	1
58	2026_02_06_230537_add_bootstrapped_at_to_users_table	1
59	2026_02_06_232224_drop_unused_tables	1
60	2026_02_07_000001_add_sleeping_fields_to_users_table	1
61	2026_02_08_100001_add_source_to_messages_table	1
62	2026_02_08_150000_update_users_status_check_constraint	1
63	2026_02_08_200001_add_calendar_feeds_and_recurrence_end	1
64	2026_02_09_100001_add_due_date_to_list_items	1
65	2026_02_10_100000_create_ai_gateway_api_keys_table	1
66	2026_02_10_100001_create_list_statuses_table	1
67	2026_02_10_100002_change_list_items_status_to_string	1
68	2026_02_11_100001_add_color_and_icon_to_documents_table	1
69	2026_02_11_200001_create_user_external_identities_table	1
70	2026_02_12_100001_create_app_settings_table	1
71	2026_02_13_100001_add_awaiting_delegation_ids_to_users_table	1
72	2026_02_14_100000_create_scheduled_automations_table	1
73	2026_02_14_100001_create_mcp_servers_table	1
74	2026_02_14_200000_add_keep_history_to_scheduled_automations	1
75	2026_02_14_200001_add_external_message_id_to_messages_table	1
76	2026_02_15_100001_create_conversation_summaries_table	1
77	2026_02_15_100002_add_flush_count_to_conversation_summaries_table	1
78	2026_02_16_000001_create_document_chunks_table	1
79	2026_02_16_000002_create_embedding_cache_table	1
80	2026_02_16_000003_remove_vector_dimension_constraints	1
81	2026_02_17_000001_add_search_vector_to_document_chunks	1
82	2026_02_18_000001_add_multi_workspace_support	1
83	2026_02_18_000002_add_icon_color_to_workspaces	1
84	2026_02_20_214031_add_trigger_message_id_to_tasks_table	1
85	2026_02_20_220023_add_config_to_data_table_views_table	1
86	2026_02_21_144134_rename_scheduled_automations_to_automations_and_add_trigger_type	1
87	2026_02_22_211552_migrate_tool_permissions_to_atomic	1
88	2026_02_28_153352_add_content_format_to_documents_table	1
89	2026_02_28_170000_scope_mcp_servers_slug_to_workspace	1
90	2026_02_28_180000_change_task_steps_description_to_text	1
91	2026_02_28_190701_create_telescope_entries_table	1
92	2026_02_28_220506_add_script_support_to_automations	1
93	2026_02_28_225205_make_prompt_nullable_on_automations	1
94	2026_03_01_000001_create_workspace_files_table	1
95	2026_03_01_000002_add_description_to_workspace_files_table	1
96	2026_03_01_000003_create_workspace_disks_table	1
97	2026_03_01_000004_add_workspace_disk_id_to_workspace_files	1
98	2026_04_05_000001_add_multi_account_to_integration_settings	1
99	2026_04_07_000001_refactor_identity_files	1
100	2026_04_09_120000_add_compaction_failure_tracking_to_conversation_summaries_table	1
101	2026_05_24_000001_create_llm_usage_events_table	1
102	2026_05_24_000002_create_integration_webhooks_table	1
103	2026_05_24_000003_create_web_usage_events_table	1
104	2026_05_24_000004_create_telegram_runtime_tables	1
105	2026_05_25_000001_create_telegram_message_mappings_table	1
106	2026_05_25_000002_add_telegram_receipt_observability_columns	1
107	2026_05_25_120000_add_vfs_trigram_indexes	1
108	2026_05_25_130000_create_vfs_operation_events_table	1
109	2026_08_09_000001_create_agent_runs_and_scope_direct_messages	1
110	2026_08_09_000002_create_agent_tool_executions_table	1
111	2026_08_09_000003_add_vfs_pagination_indexes	1
112	2026_09_08_000001_pin_mruby_runtime_on_automations	1
113	2026_11_02_000001_add_workspace_id_to_automation_rules_and_approvals	2
114	2026_11_02_000002_replace_behavior_modes_with_policy_grants	2
115	2026_11_02_000003_create_event_log_and_connections	2
116	2026_11_02_000004_add_causality_to_tasks_and_runs	2
117	2026_11_02_000005_replace_automations_with_routines	2
118	2026_11_02_000006_add_governance_presets_principals_and_change_records	2
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 118, true);


--
-- PostgreSQL database dump complete
--


