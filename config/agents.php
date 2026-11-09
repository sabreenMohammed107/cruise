<?php

/*
 * "Become an agent" applications (App\Http\Requests\AgentRequest).
 */
return [

    // How many agents (agent accounts + open applications together) one company email domain may have.
    // 1 = one agency per domain: once ahmed@company.com applied or is an agent, ali@company.com can't apply.
    // 0 = no limit (each email is still checked on its own).
    'max_per_domain' => (int) env('AGENTS_MAX_PER_DOMAIN', 1),

    // Free / public and throwaway email providers: agents must apply with their company's own domain
    // (App\Rules\BusinessEmail). Subdomains are caught too (mail.yahoo.com).
    'blocked_domains' => [
        // Google, Microsoft, Apple, Yahoo, AOL
        'gmail.com', 'googlemail.com',
        'hotmail.com', 'outlook.com', 'live.com', 'msn.com', 'passport.com', 'windowslive.com',
        'icloud.com', 'me.com', 'mac.com',
        'yahoo.com', 'ymail.com', 'rocketmail.com', 'aol.com', 'aim.com', 'verizon.net', 'att.net',
        // Other free providers
        'protonmail.com', 'protonmail.ch', 'proton.me', 'pm.me', 'tutanota.com', 'tutanota.de', 'tuta.io',
        'mail.com', 'email.com', 'usa.com', 'gmx.com', 'gmx.net', 'gmx.de', 'web.de',
        'zoho.com', 'zohomail.com', 'yandex.com', 'yandex.ru', 'ya.ru', 'mail.ru', 'inbox.ru', 'list.ru', 'bk.ru',
        'qq.com', '163.com', '126.com', 'sina.com', 'naver.com', 'daum.net', 'rediffmail.com',
        'fastmail.com', 'hushmail.com', 'lycos.com', 'inbox.com', 'hey.com', 'duck.com',
        'tiscali.it', 'libero.it', 'orange.fr', 'free.fr', 'laposte.net', 'wanadoo.fr', 'btinternet.com',
        'comcast.net', 'sbcglobal.net', 'cox.net', 'charter.net', 'shaw.ca', 'rogers.com',
        // Throwaway inboxes
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', '10minutemail.com',
        'temp-mail.org', 'tempmail.com', 'tempmail.net', 'throwawaymail.com', 'yopmail.com', 'getnada.com',
        'dispostable.com', 'trashmail.com', 'maildrop.cc', 'fakeinbox.com', 'mintemail.com', 'mohmal.com',
        'emailondeck.com', 'mailnesia.com', 'moakt.com', 'tempail.com', 'burnermail.io',
    ],

    // Providers with a domain per country (yahoo.co.uk, hotmail.fr, outlook.sa, live.com.eg…): blocked under any
    // country ending, so the list above doesn't have to name each one.
    'blocked_brands' => ['gmail', 'yahoo', 'hotmail', 'outlook', 'live', 'windowslive', 'msn', 'aol', 'gmx', 'yandex', 'protonmail', 'zoho'],

];
