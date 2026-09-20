<?php

return [
    'ai' => [
        'provider' => 'openai',
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'ca_bundle' => env('OPENAI_CA_BUNDLE'),
            'chat_model' => env('OPENAI_CHAT_MODEL'),
            'query_rewrite_model' => env('OPENAI_QUERY_REWRITE_MODEL', env('OPENAI_CHAT_MODEL')),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL'),
            'embedding_dimensions' => env('OPENAI_EMBEDDING_DIMENSIONS') !== null
                ? (int) env('OPENAI_EMBEDDING_DIMENSIONS')
                : null,
            'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
            'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 10),
            'max_retries' => (int) env('OPENAI_MAX_RETRIES', 3),
            'chat_max_retries' => (int) env('OPENAI_CHAT_MAX_RETRIES', 2),
        ],
        'allowed_chat_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENAI_ALLOWED_CHAT_MODELS', '')),
        ))),
        'model_pricing' => json_decode((string) env('OPENAI_MODEL_PRICING_JSON', '{}'), true) ?: [],
        'defaults' => [
            'temperature' => (string) env('AI_DEFAULT_TEMPERATURE', '0.30'),
            'max_output_tokens' => (int) env('AI_DEFAULT_MAX_OUTPUT_TOKENS', 600),
            'kb_confidence' => (string) env('AI_DEFAULT_KB_CONFIDENCE', '0.200'),
        ],
    ],

    'bots' => [
        'languages' => [
            'en' => 'English',
            'bn' => 'Bengali',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'pt' => 'Portuguese',
            'ar' => 'Arabic',
        ],
        'limits' => [
            'name' => 100,
            'display_name' => 100,
            'welcome_message' => 2000,
            'persona' => 4000,
            'fallback_message' => 2000,
            'starter_questions' => 6,
            'starter_question' => 255,
            'prechat_fields' => 6,
            'prechat_options' => 20,
            'prechat_option' => 100,
            'temperature_min' => '0.00',
            'temperature_max' => '2.00',
            'max_output_tokens_min' => 1,
            'max_output_tokens_max' => 8192,
            'kb_confidence_min' => '0.000',
            'kb_confidence_max' => '1.000',
        ],
        'defaults' => [
            'welcome_message' => env('BOT_DEFAULT_WELCOME_MESSAGE', 'How can we help you today?'),
            'prechat_enabled' => false,
            'tone' => 'friendly',
            'primary_language' => env('BOT_DEFAULT_LANGUAGE', 'en'),
            'persona' => env('BOT_DEFAULT_PERSONA', 'A helpful, concise customer support assistant.'),
            'fallback_message' => env(
                'BOT_DEFAULT_FALLBACK_MESSAGE',
                'I could not find a reliable answer in the available knowledge. Would you like help from a person?',
            ),
            'offer_human_handoff' => true,
            'answer_only_from_knowledge_base' => true,
        ],
    ],

    'queues' => [
        'knowledge_ingestion' => env('KNOWLEDGE_INGESTION_QUEUE', 'knowledge-ingestion'),
        'embeddings' => env('EMBEDDINGS_QUEUE', 'embeddings'),
        'ai_responses' => env('AI_RESPONSES_QUEUE', 'ai-responses'),
        'notifications' => env('NOTIFICATIONS_QUEUE', 'notifications'),
    ],

    'storage' => [
        'knowledge_disk' => env('KNOWLEDGE_SOURCE_DISK', 'knowledge'),
    ],

    'vector_store' => [
        'driver' => env('VECTOR_STORE_DRIVER', 'mysql'),
    ],

    'knowledge' => [
        'allowed_files' => [
            'txt' => ['text/plain'],
            'md' => ['text/plain', 'text/markdown'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ],
        'max_file_kb' => (int) env('KNOWLEDGE_MAX_FILE_KB', 20480),
        'chunk_target_characters' => (int) env('KNOWLEDGE_CHUNK_TARGET_CHARACTERS', 2400),
        'chunk_overlap_characters' => (int) env('KNOWLEDGE_CHUNK_OVERLAP_CHARACTERS', 240),
        'embedding_batch_size' => (int) env('KNOWLEDGE_EMBEDDING_BATCH_SIZE', 32),
        'vector_scan_batch' => (int) env('KNOWLEDGE_VECTOR_SCAN_BATCH', 250),
        'website' => [
            'default_page_limit' => (int) env('KNOWLEDGE_WEBSITE_DEFAULT_PAGE_LIMIT', 10),
            'maximum_page_limit' => (int) env('KNOWLEDGE_WEBSITE_MAX_PAGE_LIMIT', 25),
            'maximum_redirects' => (int) env('KNOWLEDGE_WEBSITE_MAX_REDIRECTS', 3),
            'connect_timeout' => (int) env('KNOWLEDGE_WEBSITE_CONNECT_TIMEOUT', 5),
            'request_timeout' => (int) env('KNOWLEDGE_WEBSITE_REQUEST_TIMEOUT', 15),
            'maximum_response_bytes' => (int) env('KNOWLEDGE_WEBSITE_MAX_RESPONSE_BYTES', 2097152),
        ],
    ],

    'rag' => [
        'message_max_length' => (int) env('RAG_MESSAGE_MAX_LENGTH', 4000),
        'history_character_limit' => (int) env('RAG_HISTORY_CHARACTER_LIMIT', 12000),
        'evidence_character_limit' => (int) env('RAG_EVIDENCE_CHARACTER_LIMIT', 16000),
        'query_rewrite_max_output_tokens' => (int) env('RAG_QUERY_REWRITE_MAX_OUTPUT_TOKENS', 120),
        'requests_per_minute' => (int) env('RAG_REQUESTS_PER_MINUTE', 12),
    ],

    'conversations' => [
        'page_size' => (int) env('CONVERSATION_INBOX_PAGE_SIZE', 20),
        'message_page_size' => (int) env('CONVERSATION_MESSAGE_PAGE_SIZE', 50),
        'poll_seconds' => (int) env('CONVERSATION_POLL_SECONDS', 4),
        'attachment_disk' => env('CONVERSATION_ATTACHMENT_DISK', 'conversation_attachments'),
        'attachment_max_kb' => (int) env('CONVERSATION_ATTACHMENT_MAX_KB', 10240),
        'maximum_attachments' => (int) env('CONVERSATION_MAXIMUM_ATTACHMENTS', 5),
        'allowed_attachments' => [
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ],
    ],

    'widgets' => [
        'accent_colors' => [
            '#6259E8' => 'Purple',
            '#08B9E8' => 'Cyan',
            '#20C66B' => 'Green',
            '#F59E0B' => 'Orange',
            '#111827' => 'Navy',
        ],
        'default_accent_color' => '#6259E8',
        'default_position' => 'bottom_right',
        'default_welcome_message' => 'How can we help you today?',
        'welcome_message_max' => 500,
        'loader_path' => 'widgets/v1/%s/loader.js',
        'hosted_path' => 'chat/%s',
        'demo_path' => 'widgets/demo/%s',
        'maximum_origins' => (int) env('WIDGET_MAXIMUM_ORIGINS', 25),
        'session_lifetime_minutes' => (int) env('WIDGET_SESSION_LIFETIME_MINUTES', 720),
        'proof_lifetime_minutes' => (int) env('WIDGET_PROOF_LIFETIME_MINUTES', 10),
        'bootstrap_rate_per_minute' => (int) env('WIDGET_BOOTSTRAP_RATE_PER_MINUTE', 30),
        'message_rate_per_minute' => (int) env('WIDGET_MESSAGE_RATE_PER_MINUTE', 12),
        'poll_rate_per_minute' => (int) env('WIDGET_POLL_RATE_PER_MINUTE', 60),
        'prechat_value_max' => (int) env('WIDGET_PRECHAT_VALUE_MAX', 1000),
    ],
];
