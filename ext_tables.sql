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
);

CREATE TABLE be_groups (
    aiSuiteApiKey varchar(100) DEFAULT '' NOT NULL,
    openAiApiKey varchar(100) DEFAULT '' NOT NULL,
    anthropicApiKey varchar(100) DEFAULT '' NOT NULL,
    googleTranslateApiKey varchar(100) DEFAULT '' NOT NULL,
    deeplApiKey varchar(100) DEFAULT '' NOT NULL,
    deeplApiMode tinyint(3) DEFAULT '0' NOT NULL,
    midjourneyApiKey varchar(100) DEFAULT '' NOT NULL,
    midjourneyId varchar(100) DEFAULT '' NOT NULL,
    mediaStorageFolder varchar(255) DEFAULT '' NOT NULL,
    openTranslatedRecordInEditMode tinyint(3) DEFAULT '1' NOT NULL
);
