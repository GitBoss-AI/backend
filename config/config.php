<?php
return [
    'host' => 'localhost',
    'dbname' => 'gitboss_dev',
    'user' => 'gitboss_dev',
    'pass' => 'VEv5cLmVwD54At4nRGji',
    'github' => [
        'api_url' => 'https://api.github.com',
        'token' => $_ENV['GITHUB_TOKEN'] ?? '',
        'default_owner' => $_ENV['GITHUB_OWNER'] ?? '',
        'default_repo' => $_ENV['GITHUB_REPO'] ?? ''
    ]
];
