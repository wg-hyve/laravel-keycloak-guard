<?php 

return [
    'realm_public_key' => env('KEYCLOAK_REALM_PUBLIC_KEY', null),

    'load_user_from_database' => env('KEYCLOAK_LOAD_USER_FROM_DATABASE', true),

    'user_provider_custom_retrieve_method' => null,

    'user_provider_credential' => env('KEYCLOAK_USER_PROVIDER_CREDENTIAL', 'username'),

    'token_principal_attribute' => env('KEYCLOAK_TOKEN_PRINCIPAL_ATTRIBUTE', 'preferred_username'),

    'append_decoded_token' => env('KEYCLOAK_APPEND_DECODED_TOKEN', false),

    'allowed_resources' => env('KEYCLOAK_ALLOWED_RESOURCES', null),

    'realm_address' => env('KEYCLOAK_REALM_ADDRESS', null),

    'key_cache_time' => env('KEYCLOAK_KEY_CACHE_TIME', 24),

    'auth_url' => env('KEYCLOAK_AUTH_URL', null),

    'client_id' => env('KEYCLOAK_CLIENT_ID', null),

    'client_secret' => env('KEYCLOAK_CLIENT_SECRET', null),

    'scope' => env('KEYCLOAK_SCOPE', null),

    'grant_type' => env('KEYCLOAK_GRANT_TYPE', null),
];
