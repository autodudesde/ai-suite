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
