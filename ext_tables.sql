CREATE TABLE tx_aisuite_domain_model_server_prompt_template (
    name varchar(255) DEFAULT '',
    prompt text DEFAULT '',
    scope varchar(255) DEFAULT '',
    type varchar(255) DEFAULT '',
);

CREATE TABLE tx_aisuite_domain_model_custom_prompt_template (
    name varchar(255) DEFAULT '',
    prompt text DEFAULT '',
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
    answer text DEFAULT '',
    status varchar(40) DEFAULT '',
    error text DEFAULT '',
    crdate int(11) DEFAULT '0',

    table_name text DEFAULT '',
    id_column text DEFAULT '',
    table_uid int(11) DEFAULT 0,

    PRIMARY KEY (uuid, type)
);

CREATE TABLE be_groups (
    aiSuiteApiKey text DEFAULT '',
    openAiApiKey text DEFAULT '',
    anthropicApiKey text DEFAULT '',
    googleTranslateApiKey text DEFAULT '',
    deeplApiKey text DEFAULT '',
    deeplApiMode tinyint(3) DEFAULT '0' NOT NULL,
    midjourneyApiKey text DEFAULT '',
    midjourneyId text DEFAULT '',
    mediaStorageFolder text DEFAULT '',
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
