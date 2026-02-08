--
-- PostgreSQL database dump
--

\restrict RGgxfFWt9r5bdQSrUcwXNtjwI51zZvHbedeffPp5APKzAjoTa5NHaHfwlVTytOD

-- Dumped from database version 14.20 (Homebrew)
-- Dumped by pg_dump version 14.20 (Homebrew)

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
-- Name: app_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_settings (
    id character varying(255) NOT NULL,
    key character varying(255) NOT NULL,
    category character varying(255) NOT NULL,
    value json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
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
    recurrence_end timestamp(0) without time zone
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
    updated_at timestamp(0) without time zone
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
    is_ephemeral boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_message_at timestamp(0) without time zone,
    external_provider character varying(255),
    external_id character varying(255),
    external_config json,
    CONSTRAINT channels_type_check CHECK (((type)::text = ANY ((ARRAY['public'::character varying, 'private'::character varying, 'dm'::character varying, 'external'::character varying])::text[])))
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
    updated_at timestamp(0) without time zone
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
    updated_at timestamp(0) without time zone
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
    updated_at timestamp(0) without time zone
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
    updated_at timestamp(0) without time zone
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
    updated_at timestamp(0) without time zone
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
    icon character varying(255)
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
    updated_at timestamp(0) without time zone
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
-- Name: list_automation_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_automation_rules (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    trigger_type character varying(255) NOT NULL,
    trigger_conditions json,
    action_type character varying(255) NOT NULL,
    action_config json,
    list_template_id character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    last_triggered_at timestamp(0) without time zone,
    trigger_count integer DEFAULT 0 NOT NULL,
    created_by_id character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT task_automation_rules_action_type_check CHECK (((action_type)::text = ANY ((ARRAY['create_task'::character varying, 'assign_task'::character varying, 'send_notification'::character varying, 'update_task'::character varying, 'spawn_agent'::character varying])::text[]))),
    CONSTRAINT task_automation_rules_trigger_type_check CHECK (((trigger_type)::text = ANY ((ARRAY['task_created'::character varying, 'task_completed'::character varying, 'task_assigned'::character varying, 'approval_granted'::character varying, 'approval_rejected'::character varying, 'schedule'::character varying])::text[])))
);


--
-- Name: list_item_collaborators; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_item_collaborators (
    id character varying(255) NOT NULL,
    list_item_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL
);


--
-- Name: list_item_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_item_comments (
    id character varying(255) NOT NULL,
    list_item_id character varying(255) NOT NULL,
    author_id character varying(255) NOT NULL,
    content text NOT NULL,
    parent_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: list_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_items (
    id character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    description text NOT NULL,
    status character varying(50) DEFAULT 'backlog'::character varying NOT NULL,
    assignee_id character varying(255),
    priority character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    cost numeric(12,2),
    estimated_cost numeric(12,2),
    channel_id character varying(255),
    "position" integer DEFAULT 0 NOT NULL,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    creator_id character varying(255),
    parent_id character varying(255),
    is_folder boolean DEFAULT false NOT NULL,
    due_date date,
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
    updated_at timestamp(0) without time zone
);


--
-- Name: list_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_templates (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    default_title character varying(255) NOT NULL,
    default_description text,
    default_priority character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    default_assignee_id character varying(255),
    estimated_cost numeric(12,2),
    tags json,
    created_by_id character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT task_templates_default_priority_check CHECK (((default_priority)::text = ANY ((ARRAY['low'::character varying, 'medium'::character varying, 'high'::character varying, 'urgent'::character varying])::text[])))
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
    source character varying(255)
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
    description character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    step_type character varying(255) DEFAULT 'action'::character varying NOT NULL,
    metadata json,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tasks (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    type character varying(255) DEFAULT 'custom'::character varying NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    priority character varying(255) DEFAULT 'normal'::character varying NOT NULL,
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
    source character varying(255)
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
    is_ephemeral boolean DEFAULT false NOT NULL,
    manager_id character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    brain character varying(255),
    docs_folder_id character varying(255),
    behavior_mode character varying(255),
    awaiting_approval_id character varying(255),
    must_wait_for_approval boolean DEFAULT false NOT NULL,
    bootstrapped_at timestamp(0) without time zone,
    sleeping_until timestamp(0) without time zone,
    sleeping_reason character varying(255),
    CONSTRAINT users_presence_check CHECK (((presence)::text = ANY ((ARRAY['online'::character varying, 'away'::character varying, 'busy'::character varying, 'offline'::character varying])::text[]))),
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['idle'::character varying, 'working'::character varying, 'offline'::character varying, 'sleeping'::character varying, 'awaiting_approval'::character varying, 'awaiting_delegation'::character varying])::text[]))),
    CONSTRAINT users_type_check CHECK (((type)::text = ANY ((ARRAY['human'::character varying, 'agent'::character varying])::text[])))
);


--
-- Name: channel_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.channel_members ALTER COLUMN id SET DEFAULT nextval('public.channel_members_id_seq'::regclass);


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
-- Name: app_settings app_settings_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings
    ADD CONSTRAINT app_settings_key_unique UNIQUE (key);


--
-- Name: app_settings app_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings
    ADD CONSTRAINT app_settings_pkey PRIMARY KEY (id);


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
-- Name: direct_messages direct_messages_user1_id_user2_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direct_messages
    ADD CONSTRAINT direct_messages_user1_id_user2_id_unique UNIQUE (user1_id, user2_id);


--
-- Name: document_attachments document_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_attachments
    ADD CONSTRAINT document_attachments_pkey PRIMARY KEY (id);


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
-- Name: integration_settings integration_settings_integration_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_settings
    ADD CONSTRAINT integration_settings_integration_id_unique UNIQUE (integration_id);


--
-- Name: integration_settings integration_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_settings
    ADD CONSTRAINT integration_settings_pkey PRIMARY KEY (id);


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
-- Name: list_statuses list_statuses_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_statuses
    ADD CONSTRAINT list_statuses_slug_unique UNIQUE (slug);


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
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: list_automation_rules task_automation_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_automation_rules
    ADD CONSTRAINT task_automation_rules_pkey PRIMARY KEY (id);


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
-- Name: app_settings_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX app_settings_category_index ON public.app_settings USING btree (category);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: conversation_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_index ON public.agent_conversation_messages USING btree (conversation_id, user_id, updated_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


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
-- Name: tasks_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_type_index ON public.tasks USING btree (type);


--
-- Name: activities activities_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activities
    ADD CONSTRAINT activities_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id);


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
-- Name: calendar_feeds calendar_feeds_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_feeds
    ADD CONSTRAINT calendar_feeds_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


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
-- Name: list_automation_rules task_automation_rules_created_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_automation_rules
    ADD CONSTRAINT task_automation_rules_created_by_id_foreign FOREIGN KEY (created_by_id) REFERENCES public.users(id);


--
-- Name: list_automation_rules task_automation_rules_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_automation_rules
    ADD CONSTRAINT task_automation_rules_template_id_foreign FOREIGN KEY (list_template_id) REFERENCES public.list_templates(id) ON DELETE SET NULL;


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
-- PostgreSQL database dump complete
--

\unrestrict RGgxfFWt9r5bdQSrUcwXNtjwI51zZvHbedeffPp5APKzAjoTa5NHaHfwlVTytOD

--
-- PostgreSQL database dump
--

\restrict 0ta1Qe2vw435KKBqS8C6JKLO8F9oGfejm2OTp9DOg1jSfCPHqbkFy6dt09sY9Yj

-- Dumped from database version 14.20 (Homebrew)
-- Dumped by pg_dump version 14.20 (Homebrew)

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
26	2026_01_21_000001_fix_messages_and_channel_members_columns	1
27	2026_01_21_000002_add_timestamps_to_activities_table	1
28	2026_01_30_231819_add_last_message_at_to_channels_table	1
29	2026_01_30_234257_add_creator_id_to_tasks_table	1
30	2026_01_31_002654_create_calendar_events_table	1
31	2026_01_31_002655_create_calendar_event_attendees_table	1
32	2026_01_31_002656_create_data_tables_table	1
33	2026_01_31_002658_create_data_table_columns_table	1
34	2026_01_31_002659_create_data_table_rows_table	1
35	2026_01_31_002660_create_data_table_views_table	1
36	2026_01_31_003834_make_created_by_nullable_in_data_tables	1
37	2026_01_31_132834_add_external_fields_to_channels_table	1
38	2026_01_31_132938_update_channels_type_enum	1
39	2026_01_31_161928_add_project_support_to_tasks_table	1
40	2026_01_31_162944_make_task_assignee_nullable	1
41	2026_01_31_172521_remove_credit_system	1
42	2026_01_31_172915_rename_is_temporary_to_is_ephemeral	1
43	2026_01_31_200000_create_integration_settings_table	1
44	2026_01_31_200001_add_brain_and_docs_folder_to_users_table	1
45	2026_02_05_195736_create_agent_conversations_table	1
46	2026_02_05_220000_rename_tasks_to_list_items	1
47	2026_02_05_220001_create_tasks_table	1
48	2026_02_05_220002_create_task_steps_table	1
49	2026_02_06_000001_create_agent_permissions_table	1
50	2026_02_06_000002_add_behavior_mode_to_users_table	1
51	2026_02_06_000003_add_tool_context_to_approval_requests	1
52	2026_02_06_100000_add_approval_waiting_to_users_table	1
53	2026_02_06_100000_remove_agent_channel_type	1
54	2026_02_06_130634_add_source_to_tasks_table	1
55	2026_02_06_223132_add_is_system_to_documents_table	1
56	2026_02_06_230537_add_bootstrapped_at_to_users_table	1
57	2026_02_06_232224_drop_unused_tables	1
58	2026_02_07_000001_add_sleeping_fields_to_users_table	1
59	2026_02_08_100001_add_source_to_messages_table	1
60	2026_02_08_200001_add_calendar_feeds_and_recurrence_end	1
61	2026_02_09_100001_add_due_date_to_list_items	1
62	2026_02_10_100001_create_list_statuses_table	1
63	2026_02_10_100002_change_list_items_status_to_string	1
64	2026_02_11_100001_add_color_and_icon_to_documents_table	1
65	2026_02_11_200001_create_user_external_identities_table	2
66	2026_02_12_100001_create_app_settings_table	3
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 66, true);


--
-- PostgreSQL database dump complete
--

\unrestrict 0ta1Qe2vw435KKBqS8C6JKLO8F9oGfejm2OTp9DOg1jSfCPHqbkFy6dt09sY9Yj

