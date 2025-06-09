CREATE TABLE tx_aisuite_domain_model_server_prompt_template (
    name varchar(255) DEFAULT '',
    prompt text,
    scope varchar(255) DEFAULT '',
    type varchar(255) DEFAULT '',
);

CREATE TABLE tx_aisuite_domain_model_custom_prompt_template (
    name varchar(255) DEFAULT '',
    prompt text,
    scope varchar(255) DEFAULT '',
    type varchar(255) DEFAULT '',
);

CREATE TABLE tx_aisuite_domain_model_requests (
    free_requests int(10) DEFAULT '0',
    paid_requests int(10) DEFAULT '0',
    abo_requests int(10) DEFAULT '0',
    model_type varchar(255) DEFAULT '',
    api_key varchar(255) DEFAULT ''
);

CREATE TABLE tx_aisuite_domain_model_backgroundtask (
    uuid varchar(64) DEFAULT '',
    type varchar(255) DEFAULT '',
    scope varchar(255) DEFAULT '',

    colPos int(11) DEFAULT '0',
    sys_language_uid int(11) DEFAULT '0',
    parent_uuid varchar(64) DEFAULT '',
    column varchar(255) DEFAULT '',
    slug varchar(2048) DEFAULT '',
    answer text,
    status varchar(40) DEFAULT '',
    error text,
    crdate int(11) DEFAULT '0',

    table_name text,
    id_column text,
    table_uid int(11) DEFAULT 0,

    PRIMARY KEY (uuid, type)
);

CREATE TABLE be_groups (
    aiSuiteApiKey text,
    openAiApiKey text,
    anthropicApiKey text,
    googleTranslateApiKey text,
    deeplApiKey text,
    deeplApiMode tinyint(3) DEFAULT '0' NOT NULL,
    midjourneyApiKey text,
    midjourneyId text,
    mediaStorageFolder text,
    openTranslatedRecordInEditMode tinyint(3) DEFAULT '1' NOT NULL
);

CREATE TABLE tx_aisuite_domain_model_glossar (
    input varchar(255) DEFAULT '' NOT NULL
);

CREATE TABLE tx_aisuite_domain_model_deepl (
    glossar_uuid varchar(255) DEFAULT '' NOT NULL,
    root_page_uid int(11) DEFAULT 0,
    source_lang varchar(10) DEFAULT '' NOT NULL,
    target_lang varchar(10) DEFAULT '' NOT NULL
);
