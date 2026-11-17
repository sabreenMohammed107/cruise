<?php

/*
|--------------------------------------------------------------------------
| Website chat assistant (Claude)
|--------------------------------------------------------------------------
| A chat bubble on the public site answers visitors about cruises, sailings, prices, ports, the ship and the
| booking rules. Claude never touches the database: it asks for data through read-only tools
| (App\Services\Assistant\Tools) that Laravel runs and answers. See PROJECT_STEPS.md, "Chat assistant".
*/

return [

    // Off until an API key is set; ASSISTANT_ENABLED=false hides the bubble and refuses messages (kill switch)
    'enabled' => (bool) env('ASSISTANT_ENABLED', true) && filled(env('ANTHROPIC_API_KEY')),

    // Who sees it until Admin → Assistant chats says otherwise: "testers" (the super admin and the accounts chosen
    // there) or "everyone"
    'audience' => env('ASSISTANT_AUDIENCE', 'testers'),

    // The super admin's test accounts (the agents chosen in Admin → Assistant chats): their bookings are hidden from
    // the rest of the panel, and the team's "new booking" email goes here instead of CRUISE_TEAM_EMAIL
    // (App\Support\TestAccounts). The customer's own confirmation still goes to the booking's contact email.
    'test_email' => env('ASSISTANT_TEST_EMAIL', 'sabreenm312@gmail.com'),

    'api_key' => env('ANTHROPIC_API_KEY'),
    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'api_version' => '2023-06-01',

    // Fast and cheap for a website chat; claude-sonnet-5-5 answers harder questions better at a higher price
    'model' => env('ASSISTANT_MODEL', 'claude-haiku-5-5'),
    'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 4096),
    'timeout' => (int) env('ASSISTANT_TIMEOUT', 90),

    // Claude ↔ tools round trips for one visitor message, before the assistant gives up and offers WhatsApp
    'max_tool_rounds' => 6,

    // Per conversation: visitor messages, then the chat asks them to start a new one or use WhatsApp
    'max_user_messages' => (int) env('ASSISTANT_MAX_MESSAGES', 30),

    // Longest visitor message, in characters
    'max_message_length' => 1000,

    // Photos in the chat (the super admin and the accounts chosen in Admin → Assistant chats only): largest image
    // after the browser shrinks it, and images per conversation. Never saved (App\Services\Assistant\ImageInput).
    'max_image_bytes' => 5 * 1024 * 1024,
    'max_images_per_chat' => (int) env('ASSISTANT_MAX_IMAGES', 5),

    // Messages sent to Claude each time: the latest ones of the conversation (older ones are dropped)
    'history_messages' => 40,

    // Rows a list tool returns at most, so answers stay short and cheap
    'max_results' => 8,

    // USD per million tokens of ASSISTANT_MODEL, from the Anthropic pricing page. Only for the cost estimate in
    // Admin → Assistant chats; leave empty to show token counts only.
    // Defaults: Claude Haiku 5.5 (prompts up to 100K tokens). Set both when ASSISTANT_MODEL is another model.
    'price_input_per_mtok' => env('ASSISTANT_PRICE_INPUT', env('ASSISTANT_MODEL', 'claude-haiku-5-5') === 'claude-haiku-5-5' ? 0.10 : null),
    'price_output_per_mtok' => env('ASSISTANT_PRICE_OUTPUT', env('ASSISTANT_MODEL', 'claude-haiku-5-5') === 'claude-haiku-5-5' ? 0.50 : null),

];
