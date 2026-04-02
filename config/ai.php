<?php
// config/ai.php — DeepSeek AI Configuration

return [
    'deepseek' => [
        'api_key'     => getenv('DEEPSEEK_API_KEY') ?: 'sk-your-deepseek-key',
        'base_url'    => 'https://api.deepseek.com',
        'model'       => 'deepseek-chat',
        'max_tokens'  => 2000,
        'temperature' => 0.3,
        'timeout'     => 30,
    ]
];
