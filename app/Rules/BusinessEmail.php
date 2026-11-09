<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The email must be on a company's own domain: free / public providers (gmail.com, yahoo.co.uk, outlook.sa…)
 * and throwaway inboxes are refused. Lists in config/agents.php.
 */
class BusinessEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = self::domainOf((string) $value);

        // A malformed address is the "email" rule's job
        if ($domain !== null && self::isFreeProvider($domain)) {
            $fail(__('pages.agents.email_not_business'));
        }
    }

    /** "company.com" from "Ahmed@Company.com.", or null without an "@". */
    public static function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }
        $domain = rtrim(strtolower(trim(substr($email, $at + 1))), '.');

        return $domain === '' ? null : $domain;
    }

    public static function isFreeProvider(string $domain): bool
    {
        $domain = rtrim(strtolower(trim($domain)), '.');

        // The listed domain itself, or any subdomain of it (mail.yahoo.com)
        foreach (config('agents.blocked_domains', []) as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        // The same provider under a country ending: yahoo.co.uk, hotmail.fr, live.com.eg — but not live.company.com
        $brands = array_map(fn ($b) => preg_quote($b, '/'), config('agents.blocked_brands', []));

        return $brands !== []
            && preg_match('/^(?:[a-z0-9-]+\.)*(?:'.implode('|', $brands).')\.(?:com|net|org|co|[a-z]{2})(?:\.[a-z]{2})?$/', $domain) === 1;
    }
}
