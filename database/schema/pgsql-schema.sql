--
-- PostgreSQL database dump
--

\restrict vtQzJXkhsdAI3X7aV0zjzaIWec8iaFLwTgcnajPKlAVbfUU46Ub8FaUfCo9YCwI

-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- Name: btree_gist; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;


--
-- Name: EXTENSION btree_gist; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION btree_gist IS 'support for indexing common datatypes in GiST';


--
-- Name: ltree; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS ltree WITH SCHEMA public;


--
-- Name: EXTENSION ltree; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION ltree IS 'data type for hierarchical tree-like structures';


--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry and geography spatial types and functions';


--
-- Name: vector; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;


--
-- Name: EXTENSION vector; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION vector IS 'vector data type and ivfflat and hnsw access methods';


--
-- Name: tenancy_log_takes_session_company(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.tenancy_log_takes_session_company() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    session_company bigint := NULLIF(current_setting('app.company_id', true), '')::bigint;
BEGIN
    IF session_company IS NOT NULL THEN
        NEW.company_id := session_company;
    END IF;
    RETURN NEW;
END $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id bigint,
    causer_type character varying(255),
    causer_id bigint,
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event character varying(255),
    batch_uuid uuid,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.activity_log FORCE ROW LEVEL SECURITY;


--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


--
-- Name: ai_chat_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_chat_messages (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    session_id character varying(255) NOT NULL,
    role character varying(255) NOT NULL,
    content text NOT NULL,
    metadata json,
    tokens_used integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    CONSTRAINT ai_chat_messages_role_check CHECK (((role)::text = ANY (ARRAY[('user'::character varying)::text, ('assistant'::character varying)::text, ('system'::character varying)::text])))
);

ALTER TABLE ONLY public.ai_chat_messages FORCE ROW LEVEL SECURITY;


--
-- Name: ai_chat_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_chat_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_chat_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_chat_messages_id_seq OWNED BY public.ai_chat_messages.id;


--
-- Name: ai_chat_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_chat_sessions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    session_id character varying(255) NOT NULL,
    title character varying(255),
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    CONSTRAINT ai_chat_sessions_status_check CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('archived'::character varying)::text])))
);

ALTER TABLE ONLY public.ai_chat_sessions FORCE ROW LEVEL SECURITY;


--
-- Name: ai_chat_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_chat_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_chat_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_chat_sessions_id_seq OWNED BY public.ai_chat_sessions.id;


--
-- Name: ai_usage_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_usage_log (
    id bigint NOT NULL,
    user_id bigint,
    employee_id bigint,
    session_id character varying(100),
    feature character varying(255) NOT NULL,
    model_used character varying(100),
    prompt_tokens integer,
    completion_tokens integer,
    total_tokens integer,
    input_summary text,
    output_summary text,
    reference_type character varying(100),
    reference_id bigint,
    latency_ms integer,
    success boolean DEFAULT true NOT NULL,
    error_message text,
    cost_usd numeric(10,6),
    pii_tokenized boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    tokens_estimated boolean DEFAULT false NOT NULL
);

ALTER TABLE ONLY public.ai_usage_log FORCE ROW LEVEL SECURITY;


--
-- Name: ai_usage_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_usage_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_usage_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_usage_log_id_seq OWNED BY public.ai_usage_log.id;


--
-- Name: app_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_settings (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    value json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.app_settings FORCE ROW LEVEL SECURITY;


--
-- Name: app_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.app_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: app_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.app_settings_id_seq OWNED BY public.app_settings.id;


--
-- Name: audit_ai_calls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_ai_calls (
    id uuid NOT NULL,
    module character varying(60) NOT NULL,
    provider character varying(30) NOT NULL,
    prompt_hash character varying(64) NOT NULL,
    request_json jsonb,
    response_json jsonb,
    tokens_used integer DEFAULT 0 NOT NULL,
    duration_ms integer DEFAULT 0 NOT NULL,
    user_id uuid,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.audit_ai_calls FORCE ROW LEVEL SECURITY;


--
-- Name: billing_addons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.billing_addons (
    id bigint NOT NULL,
    type character varying(20) NOT NULL,
    code character varying(60) NOT NULL,
    name character varying(120) NOT NULL,
    unit_label character varying(40),
    units bigint,
    price_monthly numeric(12,2),
    sort integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tiers json
);


--
-- Name: billing_addons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.billing_addons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: billing_addons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.billing_addons_id_seq OWNED BY public.billing_addons.id;


--
-- Name: billing_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.billing_modules (
    id bigint NOT NULL,
    code character varying(60) NOT NULL,
    name character varying(120) NOT NULL,
    category character varying(60),
    price_monthly numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    is_sellable boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    websites_per_month integer,
    backlinks_per_month integer,
    entities_included integer
);


--
-- Name: billing_modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.billing_modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: billing_modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.billing_modules_id_seq OWNED BY public.billing_modules.id;


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
-- Name: cities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cities (
    id bigint NOT NULL,
    country_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cities_id_seq OWNED BY public.cities.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    slug character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    registration_number character varying(60),
    vat_number character varying(40),
    contact_person character varying(255),
    contact_email character varying(255),
    contact_phone character varying(40),
    status character varying(20) DEFAULT 'trial'::character varying NOT NULL,
    plan character varying(40) DEFAULT 'starter'::character varying,
    monthly_total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    trial_ends_at timestamp(0) without time zone,
    approved_at timestamp(0) without time zone,
    suspended_at timestamp(0) without time zone,
    suspended_reason character varying(255),
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    ai_token_quota bigint,
    sms_quota integer,
    founding_customer boolean DEFAULT false NOT NULL,
    price_locked_until date,
    white_label boolean DEFAULT false NOT NULL,
    reseller_id bigint,
    trial_alert_stage smallint,
    trial_alert_ends_at date,
    archived_at timestamp(0) without time zone,
    purge_after date,
    purged_at timestamp(0) without time zone,
    converted_to_reseller_id bigint,
    demo_reseller_id bigint,
    demo_status character varying(20),
    demo_note text,
    demo_built_at timestamp(0) without time zone,
    managed_by_practice_id bigint
);


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: company_addons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_addons (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    addon_code character varying(60) NOT NULL,
    type character varying(20) NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    price_monthly numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_addons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_addons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_addons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_addons_id_seq OWNED BY public.company_addons.id;


--
-- Name: company_identities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_identities (
    id bigint NOT NULL,
    company_name character varying(255),
    full_company_name character varying(255),
    header_name_bold character varying(255),
    header_name_regular character varying(255),
    header_acronym_bold character varying(255),
    header_acronym_regular character varying(255),
    company_logo text,
    sys_theme_color character varying(255),
    mailing_name character varying(255),
    mailing_address text,
    support_email character varying(255),
    company_website character varying(255),
    password_expiring_month integer,
    system_background_image character varying(255),
    login_background_image character varying(255),
    brought_to_text character varying(255),
    brought_to_text_image character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.company_identities FORCE ROW LEVEL SECURITY;


--
-- Name: company_identities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_identities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_identities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_identities_id_seq OWNED BY public.company_identities.id;


--
-- Name: company_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_modules (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    module_code character varying(60) NOT NULL,
    price_monthly numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    source character varying(12) DEFAULT 'addon'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_modules_id_seq OWNED BY public.company_modules.id;


--
-- Name: company_monthly_credits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_monthly_credits (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    type character varying(8) NOT NULL,
    period character varying(7) NOT NULL,
    amount bigint NOT NULL,
    source character varying(20) DEFAULT 'grant'::character varying NOT NULL,
    token_request_id bigint,
    note character varying(255),
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_monthly_credits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_monthly_credits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_monthly_credits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_monthly_credits_id_seq OWNED BY public.company_monthly_credits.id;


--
-- Name: company_storage_credits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_storage_credits (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    bytes bigint NOT NULL,
    source character varying(20) DEFAULT 'purchase'::character varying NOT NULL,
    note character varying(255),
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_storage_credits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_storage_credits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_storage_credits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_storage_credits_id_seq OWNED BY public.company_storage_credits.id;


--
-- Name: company_storage_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_storage_stats (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    data_bytes bigint DEFAULT '0'::bigint NOT NULL,
    computed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_storage_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_storage_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_storage_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_storage_stats_id_seq OWNED BY public.company_storage_stats.id;


--
-- Name: company_unit_user_pivots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_unit_user_pivots (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.company_unit_user_pivots FORCE ROW LEVEL SECURITY;


--
-- Name: company_unit_user_pivots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_unit_user_pivots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_unit_user_pivots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_unit_user_pivots_id_seq OWNED BY public.company_unit_user_pivots.id;


--
-- Name: company_units; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_units (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    parent_id bigint,
    user_id bigint,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint NOT NULL,
    type character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.company_units FORCE ROW LEVEL SECURITY;


--
-- Name: company_units_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_units_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_units_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_units_id_seq OWNED BY public.company_units.id;


--
-- Name: company_user_pivots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_user_pivots (
    id bigint NOT NULL,
    type smallint NOT NULL,
    user_id bigint NOT NULL,
    company_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_user_pivots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_user_pivots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_user_pivots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_user_pivots_id_seq OWNED BY public.company_user_pivots.id;


--
-- Name: countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.countries (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    a2_code character varying(2),
    a3_code character varying(3),
    numeric_code integer,
    dialing_code character varying(10),
    abbreviation character varying(10),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: countries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.countries_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.countries_id_seq OWNED BY public.countries.id;


--
-- Name: data_breach_incidents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.data_breach_incidents (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    severity character varying(20) DEFAULT 'medium'::character varying NOT NULL,
    status character varying(30) DEFAULT 'open'::character varying NOT NULL,
    detected_at timestamp(0) without time zone NOT NULL,
    notified_regulator_at timestamp(0) without time zone,
    notified_subjects_at timestamp(0) without time zone,
    contained_at timestamp(0) without time zone,
    closed_at timestamp(0) without time zone,
    affected_record_count integer,
    affected_subject_types json,
    reported_by bigint,
    assigned_to bigint,
    remediation_notes text,
    regulator_reference character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.data_breach_incidents FORCE ROW LEVEL SECURITY;


--
-- Name: data_breach_incidents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.data_breach_incidents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: data_breach_incidents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.data_breach_incidents_id_seq OWNED BY public.data_breach_incidents.id;


--
-- Name: email_delivery_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_delivery_log (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    "to" character varying(255) NOT NULL,
    subject character varying(300),
    mailable character varying(190),
    status character varying(12) NOT NULL,
    error text,
    message_id character varying(190),
    mailer character varying(40),
    user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT email_delivery_log_status_chk CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('sent'::character varying)::text, ('failed'::character varying)::text])))
);

ALTER TABLE ONLY public.email_delivery_log FORCE ROW LEVEL SECURITY;


--
-- Name: email_delivery_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_delivery_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_delivery_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_delivery_log_id_seq OWNED BY public.email_delivery_log.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) DEFAULT (gen_random_uuid())::text NOT NULL,
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
-- Name: hr_people; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hr_people (
    id bigint NOT NULL,
    user_id integer,
    title character varying(255),
    first_name character varying(255),
    surname character varying(255),
    initial character varying(255),
    "position" integer,
    email character varying(255),
    cell_number character varying(255),
    phone_number character varying(255),
    id_number character varying(255),
    profile_pic jsonb,
    status smallint DEFAULT 1,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL,
    division_level_1 integer,
    division_level_2 integer,
    division_level_3 integer,
    division_level_4 integer,
    division_level_5 integer,
    manager_id bigint,
    date_joined bigint,
    date_left bigint,
    role_id bigint,
    second_manager_id bigint DEFAULT '0'::bigint NOT NULL,
    uuid uuid,
    known_as character varying(255),
    position_id bigint,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    profile_pic_path character varying(512)
);

ALTER TABLE ONLY public.hr_people FORCE ROW LEVEL SECURITY;


--
-- Name: hr_people_changes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hr_people_changes (
    id integer NOT NULL,
    hr_main_id integer,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    title character varying(255),
    first_name character varying(255),
    surname character varying(255),
    initial character varying(255),
    "position" integer,
    email character varying(255),
    cell_number character varying(255),
    phone_number character varying(255),
    id_number character varying(255),
    profile_pic jsonb,
    status smallint,
    date_left bigint,
    date_joined bigint,
    manager_id integer,
    division_level_1 integer,
    division_level_2 integer,
    division_level_3 integer,
    division_level_4 integer,
    division_level_5 integer,
    known_as character varying(100),
    is_approved boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    position_id bigint,
    company_unit_ids text,
    user_changes text,
    second_manager_id integer,
    role_id integer,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    profile_pic_path character varying(512)
);

ALTER TABLE ONLY public.hr_people_changes FORCE ROW LEVEL SECURITY;


--
-- Name: hr_people_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hr_people_changes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hr_people_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hr_people_changes_id_seq OWNED BY public.hr_people_changes.id;


--
-- Name: hr_people_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hr_people_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hr_people_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hr_people_id_seq OWNED BY public.hr_people.id;


--
-- Name: hr_positions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hr_positions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    job_category_id bigint NOT NULL,
    description character varying(255),
    status smallint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    ofo_code character varying(10),
    nqf_level smallint,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.hr_positions FORCE ROW LEVEL SECURITY;


--
-- Name: hr_positions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hr_positions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hr_positions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hr_positions_id_seq OWNED BY public.hr_positions.id;


--
-- Name: invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invitations (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    conference_id bigint NOT NULL,
    email character varying(255) NOT NULL,
    name character varying(255),
    user_id bigint,
    token character varying(64) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    sent_at timestamp(0) without time zone,
    scheduled_for timestamp(0) without time zone,
    invited_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.invitations FORCE ROW LEVEL SECURITY;


--
-- Name: invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invitations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invitations_id_seq OWNED BY public.invitations.id;


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
-- Name: job_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_categories (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    status smallint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.job_categories FORCE ROW LEVEL SECURITY;


--
-- Name: job_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_categories_id_seq OWNED BY public.job_categories.id;


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
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: module_how_tos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.module_how_tos (
    id bigint NOT NULL,
    module_id bigint NOT NULL,
    ribbon_id bigint NOT NULL,
    question character varying(255) NOT NULL,
    answer text,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source character varying(255) DEFAULT 'curated'::character varying NOT NULL
);


--
-- Name: module_how_tos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.module_how_tos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: module_how_tos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.module_how_tos_id_seq OWNED BY public.module_how_tos.id;


--
-- Name: module_ribbon; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.module_ribbon (
    id bigint NOT NULL,
    active boolean DEFAULT true NOT NULL,
    sort_order integer,
    ribbon_name character varying(255) NOT NULL,
    description character varying(255),
    ribbon_path character varying(255) NOT NULL,
    font_awesome character varying(255),
    access_level integer DEFAULT 1 NOT NULL,
    module_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: module_ribbon_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.module_ribbon_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: module_ribbon_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.module_ribbon_id_seq OWNED BY public.module_ribbon.id;


--
-- Name: modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modules (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    display_name character varying(255),
    description text,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modules_id_seq OWNED BY public.modules.id;


--
-- Name: notification_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_logs (
    id bigint NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    channel character varying(40) DEFAULT 'mail'::character varying NOT NULL,
    status character varying(12) DEFAULT 'sent'::character varying NOT NULL,
    notification character varying(191),
    notifiable_type character varying(191),
    notifiable_id bigint,
    error character varying(500),
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.notification_logs FORCE ROW LEVEL SECURITY;


--
-- Name: notification_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_logs_id_seq OWNED BY public.notification_logs.id;


--
-- Name: notification_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_settings (
    id bigint NOT NULL,
    conference_id bigint NOT NULL,
    reminder_offsets json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.notification_settings FORCE ROW LEVEL SECURITY;


--
-- Name: notification_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_settings_id_seq OWNED BY public.notification_settings.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: offline_policies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.offline_policies (
    id bigint NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    max_offline_minutes smallint DEFAULT '120'::smallint NOT NULL,
    warn_before_minutes smallint DEFAULT '15'::smallint NOT NULL,
    lockout_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.offline_policies FORCE ROW LEVEL SECURITY;


--
-- Name: offline_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.offline_policies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: offline_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.offline_policies_id_seq OWNED BY public.offline_policies.id;


--
-- Name: password_histories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_histories (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    password character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_histories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.password_histories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: password_histories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.password_histories_id_seq OWNED BY public.password_histories.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: password_securities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_securities (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    password_expiry_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    force_change boolean DEFAULT false NOT NULL,
    expires_at timestamp(0) without time zone
);


--
-- Name: password_securities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.password_securities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: password_securities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.password_securities_id_seq OWNED BY public.password_securities.id;


--
-- Name: payment_gateways; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_gateways (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    label character varying(60) NOT NULL,
    is_enabled boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    mode character varying(8) DEFAULT 'test'::character varying NOT NULL,
    credentials text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payment_gateways_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_gateways_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_gateways_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_gateways_id_seq OWNED BY public.payment_gateways.id;


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    company_id bigint NOT NULL,
    gateway character varying(20) NOT NULL,
    purpose character varying(20) NOT NULL,
    amount numeric(12,2) NOT NULL,
    currency character varying(3) DEFAULT 'ZAR'::character varying NOT NULL,
    status character varying(12) DEFAULT 'pending'::character varying NOT NULL,
    our_ref character varying(64) NOT NULL,
    gateway_ref character varying(120),
    meta json,
    paid_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: plans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plans (
    id bigint NOT NULL,
    code character varying(40) NOT NULL,
    name character varying(255) NOT NULL,
    price_monthly numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    max_users integer,
    modules json,
    is_active boolean DEFAULT true NOT NULL,
    sort integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    ai_token_quota bigint,
    sms_quota integer,
    regular_price_monthly numeric(12,2),
    storage_gb integer,
    support json
);


--
-- Name: plans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.plans_id_seq OWNED BY public.plans.id;


--
-- Name: provinces; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.provinces (
    id integer NOT NULL,
    country_id integer,
    name character varying(255),
    abbreviation character varying(10),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: provinces_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.provinces_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: provinces_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.provinces_id_seq OWNED BY public.provinces.id;


--
-- Name: quick_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.quick_access (
    id bigint NOT NULL,
    ribbon_id bigint,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: quick_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.quick_access_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quick_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quick_access_id_seq OWNED BY public.quick_access.id;


--
-- Name: quick_access_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.quick_access_users (
    id bigint NOT NULL,
    ribbon_id bigint,
    user_id bigint,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255),
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.quick_access_users FORCE ROW LEVEL SECURITY;


--
-- Name: quick_access_users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.quick_access_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quick_access_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quick_access_users_id_seq OWNED BY public.quick_access_users.id;


--
-- Name: reporting_logins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reporting_logins (
    id bigint NOT NULL,
    role_name character varying(63) NOT NULL,
    company_id bigint NOT NULL,
    label character varying(120),
    created_by bigint,
    created_at timestamp(0) without time zone,
    revoked_at timestamp(0) without time zone,
    CONSTRAINT reporting_logins_role_name_shape CHECK (((role_name)::text ~ '^[a-z][a-z0-9_]{2,62}$'::text))
);


--
-- Name: TABLE reporting_logins; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.reporting_logins IS 'Power BI / SQL reporting logins, each bound to exactly one company. Never granted to a reporting login.';


--
-- Name: reporting_logins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reporting_logins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reporting_logins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reporting_logins_id_seq OWNED BY public.reporting_logins.id;


--
-- Name: reseller_commissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reseller_commissions (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    reseller_id bigint NOT NULL,
    company_id bigint NOT NULL,
    subscription_invoice_id bigint,
    period character varying(7) NOT NULL,
    base_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    commission_type character varying(10) NOT NULL,
    commission_value numeric(12,2) NOT NULL,
    commission_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(12) DEFAULT 'pending'::character varying NOT NULL,
    paid_at timestamp(0) without time zone,
    reference character varying(120),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reseller_commissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reseller_commissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reseller_commissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reseller_commissions_id_seq OWNED BY public.reseller_commissions.id;


--
-- Name: reseller_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reseller_users (
    id bigint NOT NULL,
    reseller_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role character varying(10) DEFAULT 'staff'::character varying NOT NULL,
    first_name character varying(100),
    surname character varying(100),
    phone character varying(40),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT reseller_users_role CHECK (((role)::text = ANY (ARRAY[('owner'::character varying)::text, ('staff'::character varying)::text])))
);


--
-- Name: reseller_users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reseller_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reseller_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reseller_users_id_seq OWNED BY public.reseller_users.id;


--
-- Name: resellers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.resellers (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    name character varying(255) NOT NULL,
    contact_person character varying(255),
    contact_email character varying(255),
    contact_phone character varying(40),
    commission_type character varying(10) DEFAULT 'percent'::character varying NOT NULL,
    commission_value numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(12) DEFAULT 'active'::character varying NOT NULL,
    suspended_at timestamp(0) without time zone,
    suspended_reason character varying(255),
    notes text,
    user_id bigint,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(12) DEFAULT 'individual'::character varying NOT NULL,
    registration_number character varying(255),
    vat_number character varying(60),
    address text,
    website character varying(255),
    support_email character varying(255),
    logo text,
    converted_from_company_id bigint,
    converted_at timestamp(0) without time zone,
    converted_by bigint
);


--
-- Name: resellers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.resellers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: resellers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.resellers_id_seq OWNED BY public.resellers.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: scim_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scim_tokens (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    company_id bigint NOT NULL,
    name character varying(120) NOT NULL,
    token_hash character varying(64) NOT NULL,
    token_hint character varying(20) NOT NULL,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.scim_tokens FORCE ROW LEVEL SECURITY;


--
-- Name: scim_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scim_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scim_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scim_tokens_id_seq OWNED BY public.scim_tokens.id;


--
-- Name: security_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_categories (
    id bigint NOT NULL,
    name character varying(255),
    icon character varying(255),
    description character varying(255),
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.security_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: security_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.security_categories_id_seq OWNED BY public.security_categories.id;


--
-- Name: security_category_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_category_access (
    id bigint NOT NULL,
    user_id bigint,
    category_id bigint,
    access_level character varying(255),
    active boolean,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_category_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.security_category_access_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: security_category_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.security_category_access_id_seq OWNED BY public.security_category_access.id;


--
-- Name: security_module_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_module_access (
    id bigint NOT NULL,
    module_id integer,
    user_id integer,
    access_level integer,
    active integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_module_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.security_module_access_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: security_module_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.security_module_access_id_seq OWNED BY public.security_module_access.id;


--
-- Name: security_module_ribbons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_module_ribbons (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    module_id integer,
    sort_order integer,
    access_level integer,
    active integer,
    ribbon_name character varying(255),
    ribbon_path character varying(255),
    description character varying(255),
    font_awesome character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_module_ribbons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.security_module_ribbons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: security_module_ribbons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.security_module_ribbons_id_seq OWNED BY public.security_module_ribbons.id;


--
-- Name: security_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_modules (
    id bigint NOT NULL,
    category_id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    name character varying(255),
    path character varying(255),
    code_name character varying(255),
    active integer,
    font_awesome character varying(255),
    glyph_icon character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.security_modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: security_modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.security_modules_id_seq OWNED BY public.security_modules.id;


--
-- Name: security_ribbons_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_ribbons_access (
    id bigint NOT NULL,
    ribbon_id bigint NOT NULL,
    access_level integer,
    user_id integer,
    active integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_ribbons_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.security_ribbons_access_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: security_ribbons_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.security_ribbons_access_id_seq OWNED BY public.security_ribbons_access.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: sms_configurations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sms_configurations (
    id bigint NOT NULL,
    provider character varying(255) DEFAULT 'bulksms'::character varying NOT NULL,
    sms_username character varying(255),
    sms_password text,
    is_active boolean DEFAULT true NOT NULL,
    two_factor_enabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.sms_configurations FORCE ROW LEVEL SECURITY;


--
-- Name: sms_configurations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sms_configurations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sms_configurations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sms_configurations_id_seq OWNED BY public.sms_configurations.id;


--
-- Name: sms_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sms_log (
    id bigint NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    user_id bigint,
    recipient character varying(40),
    purpose character varying(40),
    segments integer DEFAULT 1 NOT NULL,
    status character varying(12) DEFAULT 'sent'::character varying NOT NULL,
    provider character varying(30),
    error character varying(255),
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.sms_log FORCE ROW LEVEL SECURITY;


--
-- Name: sms_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sms_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sms_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sms_log_id_seq OWNED BY public.sms_log.id;


--
-- Name: sso_connections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sso_connections (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    company_id bigint NOT NULL,
    provider character varying(30) DEFAULT 'oidc'::character varying NOT NULL,
    display_name character varying(120),
    client_id character varying(255) NOT NULL,
    client_secret text NOT NULL,
    issuer character varying(255) NOT NULL,
    email_domains jsonb NOT NULL,
    jit_provisioning boolean DEFAULT false NOT NULL,
    default_role character varying(60),
    enforce_sso boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    last_login_at timestamp(0) without time zone,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.sso_connections FORCE ROW LEVEL SECURITY;


--
-- Name: sso_connections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sso_connections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sso_connections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sso_connections_id_seq OWNED BY public.sso_connections.id;


--
-- Name: subscription_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_invoices (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    company_id bigint NOT NULL,
    period character varying(7) NOT NULL,
    amount numeric(12,2) NOT NULL,
    status character varying(12) DEFAULT 'open'::character varying NOT NULL,
    due_date date NOT NULL,
    paid_at timestamp(0) without time zone,
    reference character varying(120),
    gateway character varying(30),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscription_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_invoices_id_seq OWNED BY public.subscription_invoices.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    plan_code character varying(40) NOT NULL,
    status character varying(20) DEFAULT 'trialing'::character varying NOT NULL,
    current_period_start timestamp(0) without time zone,
    current_period_end timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: tenant_files; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenant_files (
    id bigint NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    disk character varying(40) DEFAULT 'local'::character varying NOT NULL,
    path character varying(1024) NOT NULL,
    name character varying(255),
    mime character varying(191),
    size bigint DEFAULT '0'::bigint NOT NULL,
    visibility character varying(12) DEFAULT 'private'::character varying NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    encryption character varying(32) DEFAULT 'none'::character varying NOT NULL,
    content_hash character varying(64),
    contents bytea NOT NULL
);

ALTER TABLE ONLY public.tenant_files FORCE ROW LEVEL SECURITY;


--
-- Name: tenant_files_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tenant_files_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tenant_files_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tenant_files_id_seq OWNED BY public.tenant_files.id;


--
-- Name: token_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.token_requests (
    id bigint NOT NULL,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    company_id bigint NOT NULL,
    type character varying(8) NOT NULL,
    amount bigint NOT NULL,
    mode character varying(16) DEFAULT 'request'::character varying NOT NULL,
    unit_price numeric(12,4),
    total_price numeric(12,2),
    status character varying(12) DEFAULT 'pending'::character varying NOT NULL,
    note character varying(500),
    requested_by bigint,
    decided_by bigint,
    decided_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: token_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.token_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: token_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.token_requests_id_seq OWNED BY public.token_requests.id;


--
-- Name: user_module_access; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_module_access (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    module_id bigint NOT NULL,
    access_level integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint
);

ALTER TABLE ONLY public.user_module_access FORCE ROW LEVEL SECURITY;


--
-- Name: user_module_access_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_module_access_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_module_access_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_module_access_id_seq OWNED BY public.user_module_access.id;


--
-- Name: user_otps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_otps (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    otp character varying(255) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    is_used boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_otps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_otps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_otps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_otps_id_seq OWNED BY public.user_otps.id;


--
-- Name: user_theme_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_theme_settings (
    user_id bigint NOT NULL,
    settings json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint
);

ALTER TABLE ONLY public.user_theme_settings FORCE ROW LEVEL SECURITY;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    type smallint,
    status smallint,
    lockout_time integer,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    two_factor_secret text,
    uuid uuid DEFAULT gen_random_uuid() NOT NULL,
    is_platform_admin boolean DEFAULT false NOT NULL,
    is_master_admin boolean DEFAULT false NOT NULL,
    platform_admin_granted_by bigint,
    is_information_officer boolean DEFAULT false NOT NULL,
    locale character varying(10) DEFAULT 'en'::character varying NOT NULL,
    password_changed_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    company_id bigint,
    sso_provider character varying(30),
    external_id character varying(255),
    sso_last_login_at timestamp(0) without time zone,
    deprovisioned_at timestamp(0) without time zone,
    fin_entity_id bigint
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: whatsapp_configurations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.whatsapp_configurations (
    id bigint NOT NULL,
    company_id bigint DEFAULT (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint,
    display_number character varying(30),
    phone_number_id character varying(60),
    business_account_id character varying(60),
    access_token text,
    api_version character varying(10) DEFAULT 'v21.0'::character varying NOT NULL,
    default_country_code character varying(4) DEFAULT '27'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    last_tested_at timestamp(0) without time zone,
    last_test_result character varying(300),
    updated_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.whatsapp_configurations FORCE ROW LEVEL SECURITY;


--
-- Name: whatsapp_configurations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.whatsapp_configurations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_configurations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_configurations_id_seq OWNED BY public.whatsapp_configurations.id;


--
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: ai_chat_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_messages ALTER COLUMN id SET DEFAULT nextval('public.ai_chat_messages_id_seq'::regclass);


--
-- Name: ai_chat_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_sessions ALTER COLUMN id SET DEFAULT nextval('public.ai_chat_sessions_id_seq'::regclass);


--
-- Name: ai_usage_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_log ALTER COLUMN id SET DEFAULT nextval('public.ai_usage_log_id_seq'::regclass);


--
-- Name: app_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings ALTER COLUMN id SET DEFAULT nextval('public.app_settings_id_seq'::regclass);


--
-- Name: billing_addons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_addons ALTER COLUMN id SET DEFAULT nextval('public.billing_addons_id_seq'::regclass);


--
-- Name: billing_modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_modules ALTER COLUMN id SET DEFAULT nextval('public.billing_modules_id_seq'::regclass);


--
-- Name: cities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities ALTER COLUMN id SET DEFAULT nextval('public.cities_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: company_addons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_addons ALTER COLUMN id SET DEFAULT nextval('public.company_addons_id_seq'::regclass);


--
-- Name: company_identities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_identities ALTER COLUMN id SET DEFAULT nextval('public.company_identities_id_seq'::regclass);


--
-- Name: company_modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules ALTER COLUMN id SET DEFAULT nextval('public.company_modules_id_seq'::regclass);


--
-- Name: company_monthly_credits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_monthly_credits ALTER COLUMN id SET DEFAULT nextval('public.company_monthly_credits_id_seq'::regclass);


--
-- Name: company_storage_credits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_storage_credits ALTER COLUMN id SET DEFAULT nextval('public.company_storage_credits_id_seq'::regclass);


--
-- Name: company_storage_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_storage_stats ALTER COLUMN id SET DEFAULT nextval('public.company_storage_stats_id_seq'::regclass);


--
-- Name: company_unit_user_pivots id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_unit_user_pivots ALTER COLUMN id SET DEFAULT nextval('public.company_unit_user_pivots_id_seq'::regclass);


--
-- Name: company_units id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_units ALTER COLUMN id SET DEFAULT nextval('public.company_units_id_seq'::regclass);


--
-- Name: company_user_pivots id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user_pivots ALTER COLUMN id SET DEFAULT nextval('public.company_user_pivots_id_seq'::regclass);


--
-- Name: countries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries ALTER COLUMN id SET DEFAULT nextval('public.countries_id_seq'::regclass);


--
-- Name: data_breach_incidents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breach_incidents ALTER COLUMN id SET DEFAULT nextval('public.data_breach_incidents_id_seq'::regclass);


--
-- Name: email_delivery_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_delivery_log ALTER COLUMN id SET DEFAULT nextval('public.email_delivery_log_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: hr_people id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_people ALTER COLUMN id SET DEFAULT nextval('public.hr_people_id_seq'::regclass);


--
-- Name: hr_people_changes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_people_changes ALTER COLUMN id SET DEFAULT nextval('public.hr_people_changes_id_seq'::regclass);


--
-- Name: hr_positions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_positions ALTER COLUMN id SET DEFAULT nextval('public.hr_positions_id_seq'::regclass);


--
-- Name: invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations ALTER COLUMN id SET DEFAULT nextval('public.invitations_id_seq'::regclass);


--
-- Name: job_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_categories ALTER COLUMN id SET DEFAULT nextval('public.job_categories_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: module_how_tos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_how_tos ALTER COLUMN id SET DEFAULT nextval('public.module_how_tos_id_seq'::regclass);


--
-- Name: module_ribbon id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_ribbon ALTER COLUMN id SET DEFAULT nextval('public.module_ribbon_id_seq'::regclass);


--
-- Name: modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules ALTER COLUMN id SET DEFAULT nextval('public.modules_id_seq'::regclass);


--
-- Name: notification_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_logs ALTER COLUMN id SET DEFAULT nextval('public.notification_logs_id_seq'::regclass);


--
-- Name: notification_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_settings ALTER COLUMN id SET DEFAULT nextval('public.notification_settings_id_seq'::regclass);


--
-- Name: offline_policies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.offline_policies ALTER COLUMN id SET DEFAULT nextval('public.offline_policies_id_seq'::regclass);


--
-- Name: password_histories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_histories ALTER COLUMN id SET DEFAULT nextval('public.password_histories_id_seq'::regclass);


--
-- Name: password_securities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_securities ALTER COLUMN id SET DEFAULT nextval('public.password_securities_id_seq'::regclass);


--
-- Name: payment_gateways id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_gateways ALTER COLUMN id SET DEFAULT nextval('public.payment_gateways_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: plans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans ALTER COLUMN id SET DEFAULT nextval('public.plans_id_seq'::regclass);


--
-- Name: provinces id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provinces ALTER COLUMN id SET DEFAULT nextval('public.provinces_id_seq'::regclass);


--
-- Name: quick_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access ALTER COLUMN id SET DEFAULT nextval('public.quick_access_id_seq'::regclass);


--
-- Name: quick_access_users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access_users ALTER COLUMN id SET DEFAULT nextval('public.quick_access_users_id_seq'::regclass);


--
-- Name: reporting_logins id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reporting_logins ALTER COLUMN id SET DEFAULT nextval('public.reporting_logins_id_seq'::regclass);


--
-- Name: reseller_commissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_commissions ALTER COLUMN id SET DEFAULT nextval('public.reseller_commissions_id_seq'::regclass);


--
-- Name: reseller_users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_users ALTER COLUMN id SET DEFAULT nextval('public.reseller_users_id_seq'::regclass);


--
-- Name: resellers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resellers ALTER COLUMN id SET DEFAULT nextval('public.resellers_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: scim_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scim_tokens ALTER COLUMN id SET DEFAULT nextval('public.scim_tokens_id_seq'::regclass);


--
-- Name: security_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_categories ALTER COLUMN id SET DEFAULT nextval('public.security_categories_id_seq'::regclass);


--
-- Name: security_category_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_category_access ALTER COLUMN id SET DEFAULT nextval('public.security_category_access_id_seq'::regclass);


--
-- Name: security_module_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_module_access ALTER COLUMN id SET DEFAULT nextval('public.security_module_access_id_seq'::regclass);


--
-- Name: security_module_ribbons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_module_ribbons ALTER COLUMN id SET DEFAULT nextval('public.security_module_ribbons_id_seq'::regclass);


--
-- Name: security_modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_modules ALTER COLUMN id SET DEFAULT nextval('public.security_modules_id_seq'::regclass);


--
-- Name: security_ribbons_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_ribbons_access ALTER COLUMN id SET DEFAULT nextval('public.security_ribbons_access_id_seq'::regclass);


--
-- Name: sms_configurations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_configurations ALTER COLUMN id SET DEFAULT nextval('public.sms_configurations_id_seq'::regclass);


--
-- Name: sms_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_log ALTER COLUMN id SET DEFAULT nextval('public.sms_log_id_seq'::regclass);


--
-- Name: sso_connections id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sso_connections ALTER COLUMN id SET DEFAULT nextval('public.sso_connections_id_seq'::regclass);


--
-- Name: subscription_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_invoices ALTER COLUMN id SET DEFAULT nextval('public.subscription_invoices_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: tenant_files id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_files ALTER COLUMN id SET DEFAULT nextval('public.tenant_files_id_seq'::regclass);


--
-- Name: token_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.token_requests ALTER COLUMN id SET DEFAULT nextval('public.token_requests_id_seq'::regclass);


--
-- Name: user_module_access id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_module_access ALTER COLUMN id SET DEFAULT nextval('public.user_module_access_id_seq'::regclass);


--
-- Name: user_otps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_otps ALTER COLUMN id SET DEFAULT nextval('public.user_otps_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: whatsapp_configurations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_configurations ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_configurations_id_seq'::regclass);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: ai_chat_messages ai_chat_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_messages
    ADD CONSTRAINT ai_chat_messages_pkey PRIMARY KEY (id);


--
-- Name: ai_chat_sessions ai_chat_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_sessions
    ADD CONSTRAINT ai_chat_sessions_pkey PRIMARY KEY (id);


--
-- Name: ai_chat_sessions ai_chat_sessions_session_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_sessions
    ADD CONSTRAINT ai_chat_sessions_session_id_unique UNIQUE (session_id);


--
-- Name: ai_usage_log ai_usage_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_log
    ADD CONSTRAINT ai_usage_log_pkey PRIMARY KEY (id);


--
-- Name: app_settings app_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_settings
    ADD CONSTRAINT app_settings_pkey PRIMARY KEY (id);


--
-- Name: audit_ai_calls audit_ai_calls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_ai_calls
    ADD CONSTRAINT audit_ai_calls_pkey PRIMARY KEY (id);


--
-- Name: billing_addons billing_addons_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_addons
    ADD CONSTRAINT billing_addons_code_unique UNIQUE (code);


--
-- Name: billing_addons billing_addons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_addons
    ADD CONSTRAINT billing_addons_pkey PRIMARY KEY (id);


--
-- Name: billing_modules billing_modules_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_modules
    ADD CONSTRAINT billing_modules_code_unique UNIQUE (code);


--
-- Name: billing_modules billing_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_modules
    ADD CONSTRAINT billing_modules_pkey PRIMARY KEY (id);


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
-- Name: cities cities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_pkey PRIMARY KEY (id);


--
-- Name: companies companies_demo_reseller_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_demo_reseller_id_unique UNIQUE (demo_reseller_id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: companies companies_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_slug_unique UNIQUE (slug);


--
-- Name: companies companies_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_uuid_unique UNIQUE (uuid);


--
-- Name: company_addons company_addons_company_id_addon_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_addons
    ADD CONSTRAINT company_addons_company_id_addon_code_unique UNIQUE (company_id, addon_code);


--
-- Name: company_addons company_addons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_addons
    ADD CONSTRAINT company_addons_pkey PRIMARY KEY (id);


--
-- Name: company_identities company_identities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_identities
    ADD CONSTRAINT company_identities_pkey PRIMARY KEY (id);


--
-- Name: company_modules company_modules_company_id_module_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules
    ADD CONSTRAINT company_modules_company_id_module_code_unique UNIQUE (company_id, module_code);


--
-- Name: company_modules company_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_modules
    ADD CONSTRAINT company_modules_pkey PRIMARY KEY (id);


--
-- Name: company_monthly_credits company_monthly_credits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_monthly_credits
    ADD CONSTRAINT company_monthly_credits_pkey PRIMARY KEY (id);


--
-- Name: company_storage_credits company_storage_credits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_storage_credits
    ADD CONSTRAINT company_storage_credits_pkey PRIMARY KEY (id);


--
-- Name: company_storage_stats company_storage_stats_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_storage_stats
    ADD CONSTRAINT company_storage_stats_company_id_unique UNIQUE (company_id);


--
-- Name: company_storage_stats company_storage_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_storage_stats
    ADD CONSTRAINT company_storage_stats_pkey PRIMARY KEY (id);


--
-- Name: company_unit_user_pivots company_unit_user_pivots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_unit_user_pivots
    ADD CONSTRAINT company_unit_user_pivots_pkey PRIMARY KEY (id);


--
-- Name: company_unit_user_pivots company_unit_user_pivots_user_id_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_unit_user_pivots
    ADD CONSTRAINT company_unit_user_pivots_user_id_company_id_unique UNIQUE (user_id, company_id);


--
-- Name: company_units company_units_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_units
    ADD CONSTRAINT company_units_pkey PRIMARY KEY (id);


--
-- Name: company_user_pivots company_user_pivots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user_pivots
    ADD CONSTRAINT company_user_pivots_pkey PRIMARY KEY (id);


--
-- Name: company_user_pivots company_user_pivots_user_id_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user_pivots
    ADD CONSTRAINT company_user_pivots_user_id_company_id_unique UNIQUE (user_id, company_id);


--
-- Name: countries countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_pkey PRIMARY KEY (id);


--
-- Name: data_breach_incidents data_breach_incidents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breach_incidents
    ADD CONSTRAINT data_breach_incidents_pkey PRIMARY KEY (id);


--
-- Name: data_breach_incidents data_breach_incidents_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breach_incidents
    ADD CONSTRAINT data_breach_incidents_uuid_unique UNIQUE (uuid);


--
-- Name: email_delivery_log email_delivery_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_delivery_log
    ADD CONSTRAINT email_delivery_log_pkey PRIMARY KEY (id);


--
-- Name: email_delivery_log email_delivery_log_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_delivery_log
    ADD CONSTRAINT email_delivery_log_uuid_unique UNIQUE (uuid);


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
-- Name: hr_people_changes hr_people_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_people_changes
    ADD CONSTRAINT hr_people_changes_pkey PRIMARY KEY (id);


--
-- Name: hr_people hr_people_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_people
    ADD CONSTRAINT hr_people_pkey PRIMARY KEY (id);


--
-- Name: hr_positions hr_positions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_positions
    ADD CONSTRAINT hr_positions_pkey PRIMARY KEY (id);


--
-- Name: invitations invitations_conference_id_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_conference_id_email_unique UNIQUE (conference_id, email);


--
-- Name: invitations invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_pkey PRIMARY KEY (id);


--
-- Name: invitations invitations_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_token_unique UNIQUE (token);


--
-- Name: invitations invitations_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: job_categories job_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_categories
    ADD CONSTRAINT job_categories_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: module_how_tos module_how_tos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_how_tos
    ADD CONSTRAINT module_how_tos_pkey PRIMARY KEY (id);


--
-- Name: module_ribbon module_ribbon_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_ribbon
    ADD CONSTRAINT module_ribbon_pkey PRIMARY KEY (id);


--
-- Name: modules modules_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_name_unique UNIQUE (name);


--
-- Name: modules modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_pkey PRIMARY KEY (id);


--
-- Name: notification_logs notification_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_logs
    ADD CONSTRAINT notification_logs_pkey PRIMARY KEY (id);


--
-- Name: notification_settings notification_settings_conference_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_settings
    ADD CONSTRAINT notification_settings_conference_id_unique UNIQUE (conference_id);


--
-- Name: notification_settings notification_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_settings
    ADD CONSTRAINT notification_settings_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: offline_policies offline_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.offline_policies
    ADD CONSTRAINT offline_policies_pkey PRIMARY KEY (id);


--
-- Name: password_histories password_histories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_histories
    ADD CONSTRAINT password_histories_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: password_securities password_securities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_securities
    ADD CONSTRAINT password_securities_pkey PRIMARY KEY (id);


--
-- Name: payment_gateways payment_gateways_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_gateways
    ADD CONSTRAINT payment_gateways_code_unique UNIQUE (code);


--
-- Name: payment_gateways payment_gateways_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_gateways
    ADD CONSTRAINT payment_gateways_pkey PRIMARY KEY (id);


--
-- Name: payments payments_our_ref_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_our_ref_unique UNIQUE (our_ref);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payments payments_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_uuid_unique UNIQUE (uuid);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: plans plans_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_code_unique UNIQUE (code);


--
-- Name: plans plans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);


--
-- Name: provinces provinces_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provinces
    ADD CONSTRAINT provinces_pkey PRIMARY KEY (id);


--
-- Name: quick_access quick_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access
    ADD CONSTRAINT quick_access_pkey PRIMARY KEY (id);


--
-- Name: quick_access_users quick_access_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access_users
    ADD CONSTRAINT quick_access_users_pkey PRIMARY KEY (id);


--
-- Name: reporting_logins reporting_logins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reporting_logins
    ADD CONSTRAINT reporting_logins_pkey PRIMARY KEY (id);


--
-- Name: reporting_logins reporting_logins_role_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reporting_logins
    ADD CONSTRAINT reporting_logins_role_name_key UNIQUE (role_name);


--
-- Name: reseller_commissions reseller_commission_period_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_commissions
    ADD CONSTRAINT reseller_commission_period_unique UNIQUE (reseller_id, company_id, period);


--
-- Name: reseller_commissions reseller_commissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_commissions
    ADD CONSTRAINT reseller_commissions_pkey PRIMARY KEY (id);


--
-- Name: reseller_commissions reseller_commissions_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_commissions
    ADD CONSTRAINT reseller_commissions_uuid_unique UNIQUE (uuid);


--
-- Name: reseller_users reseller_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_users
    ADD CONSTRAINT reseller_users_pkey PRIMARY KEY (id);


--
-- Name: reseller_users reseller_users_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_users
    ADD CONSTRAINT reseller_users_user_id_unique UNIQUE (user_id);


--
-- Name: resellers resellers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resellers
    ADD CONSTRAINT resellers_pkey PRIMARY KEY (id);


--
-- Name: resellers resellers_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resellers
    ADD CONSTRAINT resellers_uuid_unique UNIQUE (uuid);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: scim_tokens scim_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scim_tokens
    ADD CONSTRAINT scim_tokens_pkey PRIMARY KEY (id);


--
-- Name: scim_tokens scim_tokens_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scim_tokens
    ADD CONSTRAINT scim_tokens_token_hash_unique UNIQUE (token_hash);


--
-- Name: scim_tokens scim_tokens_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scim_tokens
    ADD CONSTRAINT scim_tokens_uuid_unique UNIQUE (uuid);


--
-- Name: security_categories security_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_categories
    ADD CONSTRAINT security_categories_pkey PRIMARY KEY (id);


--
-- Name: security_category_access security_category_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_category_access
    ADD CONSTRAINT security_category_access_pkey PRIMARY KEY (id);


--
-- Name: security_module_access security_module_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_module_access
    ADD CONSTRAINT security_module_access_pkey PRIMARY KEY (id);


--
-- Name: security_module_ribbons security_module_ribbons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_module_ribbons
    ADD CONSTRAINT security_module_ribbons_pkey PRIMARY KEY (id);


--
-- Name: security_modules security_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_modules
    ADD CONSTRAINT security_modules_pkey PRIMARY KEY (id);


--
-- Name: security_ribbons_access security_ribbons_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_ribbons_access
    ADD CONSTRAINT security_ribbons_access_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sms_configurations sms_configurations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_configurations
    ADD CONSTRAINT sms_configurations_pkey PRIMARY KEY (id);


--
-- Name: sms_log sms_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_log
    ADD CONSTRAINT sms_log_pkey PRIMARY KEY (id);


--
-- Name: sso_connections sso_connections_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sso_connections
    ADD CONSTRAINT sso_connections_company_id_unique UNIQUE (company_id);


--
-- Name: sso_connections sso_connections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sso_connections
    ADD CONSTRAINT sso_connections_pkey PRIMARY KEY (id);


--
-- Name: sso_connections sso_connections_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sso_connections
    ADD CONSTRAINT sso_connections_uuid_unique UNIQUE (uuid);


--
-- Name: subscription_invoices subscription_invoices_company_id_period_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_invoices
    ADD CONSTRAINT subscription_invoices_company_id_period_unique UNIQUE (company_id, period);


--
-- Name: subscription_invoices subscription_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_invoices
    ADD CONSTRAINT subscription_invoices_pkey PRIMARY KEY (id);


--
-- Name: subscription_invoices subscription_invoices_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_invoices
    ADD CONSTRAINT subscription_invoices_uuid_unique UNIQUE (uuid);


--
-- Name: subscriptions subscriptions_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_company_id_unique UNIQUE (company_id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: tenant_files tenant_files_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_files
    ADD CONSTRAINT tenant_files_pkey PRIMARY KEY (id);


--
-- Name: token_requests token_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.token_requests
    ADD CONSTRAINT token_requests_pkey PRIMARY KEY (id);


--
-- Name: token_requests token_requests_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.token_requests
    ADD CONSTRAINT token_requests_uuid_unique UNIQUE (uuid);


--
-- Name: user_module_access user_module_access_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_module_access
    ADD CONSTRAINT user_module_access_pkey PRIMARY KEY (id);


--
-- Name: user_module_access user_module_access_user_id_module_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_module_access
    ADD CONSTRAINT user_module_access_user_id_module_id_unique UNIQUE (user_id, module_id);


--
-- Name: user_otps user_otps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_otps
    ADD CONSTRAINT user_otps_pkey PRIMARY KEY (id);


--
-- Name: user_theme_settings user_theme_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_theme_settings
    ADD CONSTRAINT user_theme_settings_pkey PRIMARY KEY (user_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_uuid_unique UNIQUE (uuid);


--
-- Name: whatsapp_configurations whatsapp_configurations_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_configurations
    ADD CONSTRAINT whatsapp_configurations_company_id_unique UNIQUE (company_id);


--
-- Name: whatsapp_configurations whatsapp_configurations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_configurations
    ADD CONSTRAINT whatsapp_configurations_pkey PRIMARY KEY (id);


--
-- Name: activity_log_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_company_id_index ON public.activity_log USING btree (company_id);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: ai_chat_messages_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_chat_messages_company_id_index ON public.ai_chat_messages USING btree (company_id);


--
-- Name: ai_chat_messages_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_chat_messages_session_id_index ON public.ai_chat_messages USING btree (session_id);


--
-- Name: ai_chat_sessions_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_chat_sessions_company_id_index ON public.ai_chat_sessions USING btree (company_id);


--
-- Name: ai_usage_log_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_usage_log_company_id_index ON public.ai_usage_log USING btree (company_id);


--
-- Name: ai_usage_log_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_usage_log_created_at_index ON public.ai_usage_log USING btree (created_at);


--
-- Name: ai_usage_log_employee_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_usage_log_employee_id_index ON public.ai_usage_log USING btree (employee_id);


--
-- Name: ai_usage_log_feature_success_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_usage_log_feature_success_index ON public.ai_usage_log USING btree (feature, success);


--
-- Name: ai_usage_log_user_id_feature_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_usage_log_user_id_feature_index ON public.ai_usage_log USING btree (user_id, feature);


--
-- Name: app_settings_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX app_settings_company_id_index ON public.app_settings USING btree (company_id);


--
-- Name: app_settings_company_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX app_settings_company_key_unique ON public.app_settings USING btree (company_id, key);


--
-- Name: app_settings_global_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX app_settings_global_key_unique ON public.app_settings USING btree (key) WHERE (company_id IS NULL);


--
-- Name: audit_ai_calls_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_ai_calls_company_id_index ON public.audit_ai_calls USING btree (company_id);


--
-- Name: audit_ai_calls_module_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_ai_calls_module_created_at_index ON public.audit_ai_calls USING btree (module, created_at);


--
-- Name: audit_ai_calls_prompt_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_ai_calls_prompt_hash_index ON public.audit_ai_calls USING btree (prompt_hash);


--
-- Name: audit_ai_calls_provider_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_ai_calls_provider_created_at_index ON public.audit_ai_calls USING btree (provider, created_at);


--
-- Name: audit_ai_calls_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_ai_calls_user_id_index ON public.audit_ai_calls USING btree (user_id);


--
-- Name: billing_addons_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX billing_addons_type_index ON public.billing_addons USING btree (type);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: cities_country_id_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cities_country_id_name_index ON public.cities USING btree (country_id, name);


--
-- Name: companies_converted_to_reseller_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_converted_to_reseller_id_index ON public.companies USING btree (converted_to_reseller_id);


--
-- Name: companies_managed_by_practice_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_managed_by_practice_id_index ON public.companies USING btree (managed_by_practice_id);


--
-- Name: companies_purge_after_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_purge_after_index ON public.companies USING btree (purge_after);


--
-- Name: companies_reseller_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_reseller_id_index ON public.companies USING btree (reseller_id);


--
-- Name: companies_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_status_index ON public.companies USING btree (status);


--
-- Name: company_addons_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_addons_company_id_index ON public.company_addons USING btree (company_id);


--
-- Name: company_identities_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_identities_company_id_index ON public.company_identities USING btree (company_id);


--
-- Name: company_modules_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_modules_company_id_index ON public.company_modules USING btree (company_id);


--
-- Name: company_monthly_credits_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_monthly_credits_company_id_index ON public.company_monthly_credits USING btree (company_id);


--
-- Name: company_monthly_credits_company_id_type_period_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_monthly_credits_company_id_type_period_index ON public.company_monthly_credits USING btree (company_id, type, period);


--
-- Name: company_monthly_credits_period_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_monthly_credits_period_index ON public.company_monthly_credits USING btree (period);


--
-- Name: company_storage_credits_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_storage_credits_company_id_index ON public.company_storage_credits USING btree (company_id);


--
-- Name: company_unit_user_pivots_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_unit_user_pivots_company_id_index ON public.company_unit_user_pivots USING btree (company_id);


--
-- Name: company_unit_user_pivots_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_unit_user_pivots_user_id_index ON public.company_unit_user_pivots USING btree (user_id);


--
-- Name: company_units_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_units_company_id_index ON public.company_units USING btree (company_id);


--
-- Name: company_units_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_units_name_index ON public.company_units USING btree (name);


--
-- Name: company_user_pivots_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_user_pivots_company_id_index ON public.company_user_pivots USING btree (company_id);


--
-- Name: company_user_pivots_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_user_pivots_type_index ON public.company_user_pivots USING btree (type);


--
-- Name: company_user_pivots_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_user_pivots_user_id_index ON public.company_user_pivots USING btree (user_id);


--
-- Name: data_breach_incidents_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_breach_incidents_company_id_index ON public.data_breach_incidents USING btree (company_id);


--
-- Name: data_breach_incidents_detected_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_breach_incidents_detected_at_index ON public.data_breach_incidents USING btree (detected_at);


--
-- Name: data_breach_incidents_severity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_breach_incidents_severity_index ON public.data_breach_incidents USING btree (severity);


--
-- Name: data_breach_incidents_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX data_breach_incidents_status_index ON public.data_breach_incidents USING btree (status);


--
-- Name: email_delivery_log_company_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_company_id_created_at_index ON public.email_delivery_log USING btree (company_id, created_at);


--
-- Name: email_delivery_log_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_company_id_index ON public.email_delivery_log USING btree (company_id);


--
-- Name: email_delivery_log_mailable_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_mailable_index ON public.email_delivery_log USING btree (mailable);


--
-- Name: email_delivery_log_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_message_id_index ON public.email_delivery_log USING btree (message_id);


--
-- Name: email_delivery_log_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_status_created_at_index ON public.email_delivery_log USING btree (status, created_at);


--
-- Name: email_delivery_log_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_status_index ON public.email_delivery_log USING btree (status);


--
-- Name: email_delivery_log_to_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_delivery_log_to_index ON public.email_delivery_log USING btree ("to");


--
-- Name: hr_people_changes_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_company_id_index ON public.hr_people_changes USING btree (company_id);


--
-- Name: hr_people_changes_division_level_1_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_division_level_1_index ON public.hr_people_changes USING btree (division_level_1);


--
-- Name: hr_people_changes_division_level_2_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_division_level_2_index ON public.hr_people_changes USING btree (division_level_2);


--
-- Name: hr_people_changes_division_level_3_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_division_level_3_index ON public.hr_people_changes USING btree (division_level_3);


--
-- Name: hr_people_changes_division_level_4_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_division_level_4_index ON public.hr_people_changes USING btree (division_level_4);


--
-- Name: hr_people_changes_division_level_5_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_division_level_5_index ON public.hr_people_changes USING btree (division_level_5);


--
-- Name: hr_people_changes_hr_main_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_hr_main_id_index ON public.hr_people_changes USING btree (hr_main_id);


--
-- Name: hr_people_changes_manager_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_manager_id_index ON public.hr_people_changes USING btree (manager_id);


--
-- Name: hr_people_changes_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_position_index ON public.hr_people_changes USING btree ("position");


--
-- Name: hr_people_changes_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_changes_uuid_index ON public.hr_people_changes USING btree (uuid);


--
-- Name: hr_people_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_company_id_index ON public.hr_people USING btree (company_id);


--
-- Name: hr_people_division_level_1_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_division_level_1_index ON public.hr_people USING btree (division_level_1);


--
-- Name: hr_people_division_level_2_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_division_level_2_index ON public.hr_people USING btree (division_level_2);


--
-- Name: hr_people_division_level_3_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_division_level_3_index ON public.hr_people USING btree (division_level_3);


--
-- Name: hr_people_division_level_4_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_division_level_4_index ON public.hr_people USING btree (division_level_4);


--
-- Name: hr_people_division_level_5_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_division_level_5_index ON public.hr_people USING btree (division_level_5);


--
-- Name: hr_people_email_ctuq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX hr_people_email_ctuq ON public.hr_people USING btree (company_id, email);


--
-- Name: hr_people_manager_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_manager_id_index ON public.hr_people USING btree (manager_id);


--
-- Name: hr_people_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_position_index ON public.hr_people USING btree ("position");


--
-- Name: hr_people_role_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_role_id_index ON public.hr_people USING btree (role_id);


--
-- Name: hr_people_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_user_id_index ON public.hr_people USING btree (user_id);


--
-- Name: hr_people_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_people_uuid_index ON public.hr_people USING btree (uuid);


--
-- Name: hr_positions_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_positions_company_id_index ON public.hr_positions USING btree (company_id);


--
-- Name: hr_positions_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hr_positions_name_index ON public.hr_positions USING btree (name);


--
-- Name: invitations_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitations_company_id_index ON public.invitations USING btree (company_id);


--
-- Name: invitations_conference_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitations_conference_id_status_index ON public.invitations USING btree (conference_id, status);


--
-- Name: job_categories_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX job_categories_company_id_index ON public.job_categories USING btree (company_id);


--
-- Name: job_categories_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX job_categories_name_index ON public.job_categories USING btree (name);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: module_how_tos_source_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX module_how_tos_source_index ON public.module_how_tos USING btree (source);


--
-- Name: notification_logs_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_logs_company_id_index ON public.notification_logs USING btree (company_id);


--
-- Name: notification_logs_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_logs_created_at_index ON public.notification_logs USING btree (created_at);


--
-- Name: notification_settings_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_settings_company_id_index ON public.notification_settings USING btree (company_id);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: offline_policies_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX offline_policies_company_id_index ON public.offline_policies USING btree (company_id);


--
-- Name: payments_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_company_id_index ON public.payments USING btree (company_id);


--
-- Name: payments_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_company_id_status_index ON public.payments USING btree (company_id, status);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: provinces_country_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX provinces_country_id_index ON public.provinces USING btree (country_id);


--
-- Name: quick_access_users_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quick_access_users_company_id_index ON public.quick_access_users USING btree (company_id);


--
-- Name: reseller_commissions_reseller_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reseller_commissions_reseller_id_status_index ON public.reseller_commissions USING btree (reseller_id, status);


--
-- Name: reseller_users_reseller_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reseller_users_reseller_id_index ON public.reseller_users USING btree (reseller_id);


--
-- Name: resellers_converted_from_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX resellers_converted_from_company_id_index ON public.resellers USING btree (converted_from_company_id);


--
-- Name: scim_tokens_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scim_tokens_company_id_index ON public.scim_tokens USING btree (company_id);


--
-- Name: security_module_ribbons_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_module_ribbons_uuid_index ON public.security_module_ribbons USING btree (uuid);


--
-- Name: security_modules_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_modules_uuid_index ON public.security_modules USING btree (uuid);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: sms_configurations_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_configurations_company_id_index ON public.sms_configurations USING btree (company_id);


--
-- Name: sms_log_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_log_company_id_index ON public.sms_log USING btree (company_id);


--
-- Name: sms_log_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_log_created_at_index ON public.sms_log USING btree (created_at);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: tenant_files_company_disk_path_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tenant_files_company_disk_path_uq ON public.tenant_files USING btree (company_id, disk, md5((path)::text));


--
-- Name: tenant_files_company_id_disk_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tenant_files_company_id_disk_index ON public.tenant_files USING btree (company_id, disk);


--
-- Name: tenant_files_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tenant_files_company_id_index ON public.tenant_files USING btree (company_id);


--
-- Name: token_requests_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX token_requests_company_id_index ON public.token_requests USING btree (company_id);


--
-- Name: token_requests_company_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX token_requests_company_id_status_index ON public.token_requests USING btree (company_id, status);


--
-- Name: user_module_access_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_module_access_company_id_index ON public.user_module_access USING btree (company_id);


--
-- Name: user_otps_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_otps_email_index ON public.user_otps USING btree (email);


--
-- Name: user_otps_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_otps_expires_at_index ON public.user_otps USING btree (expires_at);


--
-- Name: user_otps_otp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_otps_otp_index ON public.user_otps USING btree (otp);


--
-- Name: user_theme_settings_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_theme_settings_company_id_index ON public.user_theme_settings USING btree (company_id);


--
-- Name: users_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_company_id_index ON public.users USING btree (company_id);


--
-- Name: users_sso_provider_external_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_sso_provider_external_id_index ON public.users USING btree (sso_provider, external_id);


--
-- Name: activity_log log_takes_session_company; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER log_takes_session_company BEFORE INSERT ON public.activity_log FOR EACH ROW EXECUTE FUNCTION public.tenancy_log_takes_session_company();


--
-- Name: ai_usage_log log_takes_session_company; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER log_takes_session_company BEFORE INSERT ON public.ai_usage_log FOR EACH ROW EXECUTE FUNCTION public.tenancy_log_takes_session_company();


--
-- Name: notification_logs log_takes_session_company; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER log_takes_session_company BEFORE INSERT ON public.notification_logs FOR EACH ROW EXECUTE FUNCTION public.tenancy_log_takes_session_company();


--
-- Name: sms_log log_takes_session_company; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER log_takes_session_company BEFORE INSERT ON public.sms_log FOR EACH ROW EXECUTE FUNCTION public.tenancy_log_takes_session_company();


--
-- Name: ai_chat_messages ai_chat_messages_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_messages
    ADD CONSTRAINT ai_chat_messages_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ai_chat_sessions ai_chat_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_chat_sessions
    ADD CONSTRAINT ai_chat_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ai_usage_log ai_usage_log_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_log
    ADD CONSTRAINT ai_usage_log_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.hr_people(id) ON DELETE SET NULL;


--
-- Name: ai_usage_log ai_usage_log_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_log
    ADD CONSTRAINT ai_usage_log_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cities cities_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.countries(id) ON DELETE CASCADE;


--
-- Name: companies companies_demo_reseller_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_demo_reseller_id_foreign FOREIGN KEY (demo_reseller_id) REFERENCES public.resellers(id) ON DELETE SET NULL;


--
-- Name: company_unit_user_pivots company_unit_user_pivots_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_unit_user_pivots
    ADD CONSTRAINT company_unit_user_pivots_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.company_units(id) ON DELETE CASCADE;


--
-- Name: company_unit_user_pivots company_unit_user_pivots_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_unit_user_pivots
    ADD CONSTRAINT company_unit_user_pivots_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: company_units company_units_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_units
    ADD CONSTRAINT company_units_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.company_units(id) ON DELETE SET NULL;


--
-- Name: company_units company_units_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_units
    ADD CONSTRAINT company_units_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: company_user_pivots company_user_pivots_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user_pivots
    ADD CONSTRAINT company_user_pivots_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.company_identities(id) ON DELETE CASCADE;


--
-- Name: company_user_pivots company_user_pivots_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_user_pivots
    ADD CONSTRAINT company_user_pivots_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: data_breach_incidents data_breach_incidents_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breach_incidents
    ADD CONSTRAINT data_breach_incidents_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: data_breach_incidents data_breach_incidents_reported_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.data_breach_incidents
    ADD CONSTRAINT data_breach_incidents_reported_by_foreign FOREIGN KEY (reported_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: hr_people hr_people_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_people
    ADD CONSTRAINT hr_people_position_id_foreign FOREIGN KEY (position_id) REFERENCES public.hr_positions(id) ON DELETE SET NULL;


--
-- Name: hr_people hr_people_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_people
    ADD CONSTRAINT hr_people_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: invitations invitations_invited_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_invited_by_foreign FOREIGN KEY (invited_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: invitations invitations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: module_how_tos module_how_tos_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_how_tos
    ADD CONSTRAINT module_how_tos_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.security_modules(id) ON DELETE CASCADE;


--
-- Name: module_how_tos module_how_tos_ribbon_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_how_tos
    ADD CONSTRAINT module_how_tos_ribbon_id_foreign FOREIGN KEY (ribbon_id) REFERENCES public.security_module_ribbons(id) ON DELETE CASCADE;


--
-- Name: module_ribbon module_ribbon_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.module_ribbon
    ADD CONSTRAINT module_ribbon_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.modules(id) ON DELETE CASCADE;


--
-- Name: password_histories password_histories_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_histories
    ADD CONSTRAINT password_histories_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: password_securities password_securities_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_securities
    ADD CONSTRAINT password_securities_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: quick_access quick_access_ribbon_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access
    ADD CONSTRAINT quick_access_ribbon_id_foreign FOREIGN KEY (ribbon_id) REFERENCES public.security_module_ribbons(id) ON DELETE CASCADE;


--
-- Name: quick_access_users quick_access_users_ribbon_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access_users
    ADD CONSTRAINT quick_access_users_ribbon_id_foreign FOREIGN KEY (ribbon_id) REFERENCES public.security_module_ribbons(id) ON DELETE CASCADE;


--
-- Name: quick_access_users quick_access_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quick_access_users
    ADD CONSTRAINT quick_access_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: reporting_logins reporting_logins_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reporting_logins
    ADD CONSTRAINT reporting_logins_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: security_category_access security_category_access_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_category_access
    ADD CONSTRAINT security_category_access_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.security_categories(id) ON DELETE CASCADE;


--
-- Name: security_category_access security_category_access_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_category_access
    ADD CONSTRAINT security_category_access_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: security_module_access security_module_access_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_module_access
    ADD CONSTRAINT security_module_access_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.security_modules(id) ON DELETE CASCADE;


--
-- Name: security_module_ribbons security_module_ribbons_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_module_ribbons
    ADD CONSTRAINT security_module_ribbons_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.security_modules(id) ON DELETE CASCADE;


--
-- Name: security_modules security_modules_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_modules
    ADD CONSTRAINT security_modules_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.security_categories(id) ON DELETE CASCADE;


--
-- Name: security_ribbons_access security_ribbons_access_ribbon_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_ribbons_access
    ADD CONSTRAINT security_ribbons_access_ribbon_id_foreign FOREIGN KEY (ribbon_id) REFERENCES public.security_module_ribbons(id) ON DELETE CASCADE;


--
-- Name: security_ribbons_access security_ribbons_access_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_ribbons_access
    ADD CONSTRAINT security_ribbons_access_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_module_access user_module_access_module_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_module_access
    ADD CONSTRAINT user_module_access_module_id_foreign FOREIGN KEY (module_id) REFERENCES public.modules(id) ON DELETE CASCADE;


--
-- Name: user_module_access user_module_access_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_module_access
    ADD CONSTRAINT user_module_access_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_theme_settings user_theme_settings_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_theme_settings
    ADD CONSTRAINT user_theme_settings_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_platform_admin_granted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_platform_admin_granted_by_foreign FOREIGN KEY (platform_admin_granted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: activity_log; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.activity_log ENABLE ROW LEVEL SECURITY;

--
-- Name: ai_chat_messages; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ai_chat_messages ENABLE ROW LEVEL SECURITY;

--
-- Name: ai_chat_sessions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ai_chat_sessions ENABLE ROW LEVEL SECURITY;

--
-- Name: ai_usage_log; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ai_usage_log ENABLE ROW LEVEL SECURITY;

--
-- Name: app_settings; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.app_settings ENABLE ROW LEVEL SECURITY;

--
-- Name: audit_ai_calls; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.audit_ai_calls ENABLE ROW LEVEL SECURITY;

--
-- Name: company_identities; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.company_identities ENABLE ROW LEVEL SECURITY;

--
-- Name: company_unit_user_pivots; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.company_unit_user_pivots ENABLE ROW LEVEL SECURITY;

--
-- Name: company_units; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.company_units ENABLE ROW LEVEL SECURITY;

--
-- Name: data_breach_incidents; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.data_breach_incidents ENABLE ROW LEVEL SECURITY;

--
-- Name: email_delivery_log; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.email_delivery_log ENABLE ROW LEVEL SECURITY;

--
-- Name: hr_people; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hr_people ENABLE ROW LEVEL SECURITY;

--
-- Name: hr_people_changes; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hr_people_changes ENABLE ROW LEVEL SECURITY;

--
-- Name: hr_positions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hr_positions ENABLE ROW LEVEL SECURITY;

--
-- Name: invitations; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.invitations ENABLE ROW LEVEL SECURITY;

--
-- Name: job_categories; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.job_categories ENABLE ROW LEVEL SECURITY;

--
-- Name: notification_logs; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.notification_logs ENABLE ROW LEVEL SECURITY;

--
-- Name: notification_settings; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.notification_settings ENABLE ROW LEVEL SECURITY;

--
-- Name: offline_policies; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.offline_policies ENABLE ROW LEVEL SECURITY;

--
-- Name: quick_access_users; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.quick_access_users ENABLE ROW LEVEL SECURITY;

--
-- Name: scim_tokens; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.scim_tokens ENABLE ROW LEVEL SECURITY;

--
-- Name: sms_configurations; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.sms_configurations ENABLE ROW LEVEL SECURITY;

--
-- Name: sms_log; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.sms_log ENABLE ROW LEVEL SECURITY;

--
-- Name: sso_connections; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.sso_connections ENABLE ROW LEVEL SECURITY;

--
-- Name: tenant_files; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.tenant_files ENABLE ROW LEVEL SECURITY;

--
-- Name: activity_log tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.activity_log USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (true);


--
-- Name: ai_chat_messages tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.ai_chat_messages USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: ai_chat_sessions tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.ai_chat_sessions USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: ai_usage_log tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.ai_usage_log USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (true);


--
-- Name: app_settings tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.app_settings USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: audit_ai_calls tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.audit_ai_calls USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: company_identities tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.company_identities USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: company_unit_user_pivots tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.company_unit_user_pivots USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: company_units tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.company_units USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: data_breach_incidents tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.data_breach_incidents USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: email_delivery_log tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.email_delivery_log USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: hr_people tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.hr_people USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: hr_people_changes tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.hr_people_changes USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: hr_positions tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.hr_positions USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: invitations tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.invitations USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: job_categories tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.job_categories USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: notification_logs tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.notification_logs USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (true);


--
-- Name: notification_settings tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.notification_settings USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: offline_policies tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.offline_policies USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: quick_access_users tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.quick_access_users USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: scim_tokens tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.scim_tokens USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: sms_configurations tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.sms_configurations USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: sms_log tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.sms_log USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (true);


--
-- Name: sso_connections tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.sso_connections USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: tenant_files tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.tenant_files USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: user_module_access tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.user_module_access USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: user_theme_settings tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.user_theme_settings USING (((company_id IS NULL) OR (NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((company_id IS NULL) OR (NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: whatsapp_configurations tenant_isolation; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_isolation ON public.whatsapp_configurations USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint))) WITH CHECK (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: app_settings tenant_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_read ON public.app_settings FOR SELECT USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: offline_policies tenant_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_read ON public.offline_policies FOR SELECT USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: sms_configurations tenant_read; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY tenant_read ON public.sms_configurations FOR SELECT USING (((NULLIF(current_setting('app.company_id'::text, true), ''::text) IS NULL) OR (company_id IS NULL) OR (company_id = (NULLIF(current_setting('app.company_id'::text, true), ''::text))::bigint)));


--
-- Name: user_module_access; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.user_module_access ENABLE ROW LEVEL SECURITY;

--
-- Name: user_theme_settings; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.user_theme_settings ENABLE ROW LEVEL SECURITY;

--
-- Name: whatsapp_configurations; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.whatsapp_configurations ENABLE ROW LEVEL SECURITY;

--
-- PostgreSQL database dump complete
--

\unrestrict vtQzJXkhsdAI3X7aV0zjzaIWec8iaFLwTgcnajPKlAVbfUU46Ub8FaUfCo9YCwI

