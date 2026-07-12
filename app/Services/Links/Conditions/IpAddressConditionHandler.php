<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use Closure;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Matches the visitor IP against an exact address, a CIDR range
 * (`10.0.0.0/24`) or an IPv4 wildcard (`11.22.*.*`). The IP is taken from the
 * request (trusted-proxy aware — the same source as click recording).
 */
final class IpAddressConditionHandler extends AbstractConditionHandler
{
    /**
     * Shared by the write-time validation and the read-time match, so the
     * admin direct-create path (which bypasses the request validator) and any
     * hand-inserted row stay fail-closed: an unrecognised pattern never
     * matches instead of coercing into an over-broad IpUtils range.
     */
    public static function isValidPattern(string $value): bool
    {
        return self::isWildcard($value)
            || self::isCidr($value)
            || filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function matches(Condition $condition, ConditionContext $context): bool
    {
        $pattern = $condition->data['ip'] ?? null;
        $ip = $context->request->ip();

        if (! is_string($pattern) || $ip === null) {
            return false;
        }

        return self::patternMatchesIp($pattern, $ip);
    }

    public static function rules(): array
    {
        return [
            'ip' => ['required', 'string', 'max:64', self::validPattern()],
        ];
    }

    private static function isCidr(string $value): bool
    {
        if (! str_contains($value, '/')) {
            return false;
        }

        [$ip, $mask] = explode('/', $value, 2);
        $family = filter_var($ip, FILTER_VALIDATE_IP);

        if ($family === false || ! ctype_digit($mask)) {
            return false;
        }

        $maxMask = str_contains($ip, ':') ? 128 : 32;

        return (int) $mask <= $maxMask;
    }

    private static function isWildcard(string $value): bool
    {
        return str_contains($value, '*')
            && preg_match('/^(\d{1,3}|\*)(\.(\d{1,3}|\*)){3}$/', $value) === 1;
    }

    private static function patternMatchesIp(string $pattern, string $ip): bool
    {
        // Fail closed on a stored pattern that isn't one of the three valid
        // forms — never hand garbage to IpUtils (a bad mask coerces to /0 and
        // would match everything).
        if (! self::isValidPattern($pattern)) {
            return false;
        }

        if (str_contains($pattern, '*')) {
            // Wildcard: each `*` stands for one IPv4 octet. Anchor the regex so
            // `11.22.*.*` cannot match `211.22.3.4`.
            $regex = '/^'.str_replace(['\.', '\*'], ['\.', '\d{1,3}'], preg_quote($pattern, '/')).'$/';

            return (bool) preg_match($regex, $ip);
        }

        // Exact address and CIDR are both handled by IpUtils::checkIp.
        return IpUtils::checkIp($ip, $pattern);
    }

    private static function validPattern(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                $fail('The IP pattern must be a string.');

                return;
            }

            if (! self::isValidPattern($value)) {
                $fail('The IP pattern must be an exact IP, a CIDR range, or an IPv4 wildcard.');
            }
        };
    }
}
