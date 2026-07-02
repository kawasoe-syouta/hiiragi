<?php

return [
    // 管理画面のログインパスワード(.envで設定)
    'admin_password' => env('ADMIN_PASSWORD', ''),
    // 管理APIのトークン(.envでランダムな長い文字列を設定)
    'admin_token' => env('ADMIN_API_TOKEN', ''),
    // お問い合わせメールの届け先
    'mail_to' => env('MAIL_TO_ADDRESS', 'hiiragi@example.com'),
];
