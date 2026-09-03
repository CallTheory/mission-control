<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;
use Throwable;

/**
 * Removes secrets and caller-identifying data from an event before it leaves
 * the application.
 *
 * This app handles call-center data (caller names, phone numbers, DOBs,
 * patient identifiers) and stores third-party credentials that the Livewire
 * settings components hydrate into public component properties in PLAINTEXT.
 * Anything reaching a third-party issue tracker must be scrubbed first.
 *
 * If the scrubber itself fails the event is DROPPED, never sent unscrubbed.
 */
final class ScrubSentryEvent
{
    private const REDACTED = '[Filtered]';

    private static int $nodes = 0;

    public static function handle(Event $event, ?EventHint $hint): ?Event
    {
        try {
            self::$nodes = 0;

            self::scrubRequest($event);
            self::scrubUser($event);
            self::scrubContexts($event);
            self::scrubMessages($event);
            self::scrubBreadcrumbs($event);

            return $event;
        } catch (Throwable) {
            // Fail closed: an unscrubbed event is worse than no event.
            return null;
        }
    }

    /**
     * Transactions are Sentry performance events; tracing goes to Tempo.
     */
    public static function dropTransaction(Event $event, ?EventHint $hint): null
    {
        return null;
    }

    private static function scrubRequest(Event $event): void
    {
        $request = $event->getRequest();

        if ($request === []) {
            return;
        }

        // Cookies carry the session; drop wholesale.
        unset($request['cookies']);

        if (isset($request['headers']) && is_array($request['headers'])) {
            $deny = config('observability.scrubbing.headers', []);
            foreach ($request['headers'] as $name => $value) {
                if (in_array(strtolower((string) $name), $deny, true)) {
                    $request['headers'][$name] = self::REDACTED;
                }
            }
        }

        /*
         * Livewire update payloads must be dropped WHOLESALE, not key-filtered.
         * ManagesDataSourceSettings assigns decrypted credentials straight into
         * $this->state, and those live inside a JSON-encoded
         * components[].snapshot string where key-based scrubbing never finds
         * them. Keep only the component name, which is the useful signal.
         */
        $uri = (string) ($request['url'] ?? '');
        if (config('observability.scrubbing.strip_livewire_payloads', true)
            && str_contains($uri, '/livewire/')) {
            $request['data'] = ['_stripped' => 'livewire payload omitted by observability scrubbing'];
        } elseif (isset($request['data'])) {
            $request['data'] = self::walk($request['data']);
        }

        if (isset($request['query_string'])) {
            $request['query_string'] = self::redactString((string) $request['query_string']);
        }

        $event->setRequest($request);
    }

    /**
     * Reduce the user to a numeric id. Never email, name or IP.
     */
    private static function scrubUser(Event $event): void
    {
        $user = $event->getUser();

        if ($user === null) {
            return;
        }

        $id = $user->getId();

        $event->setUser(
            $id === null
                ? UserDataBag::createFromUserIdentifier('unknown')
                : UserDataBag::createFromUserIdentifier($id)
        );
    }

    private static function scrubContexts(Event $event): void
    {
        $event->setExtra(self::walk($event->getExtra()));

        $tags = [];
        foreach ($event->getTags() as $key => $value) {
            $tags[$key] = self::isSensitiveKey($key) ? self::REDACTED : self::redactString($value);
        }
        $event->setTags($tags);

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext($name, self::walk($context));
        }
    }

    private static function scrubMessages(Event $event): void
    {
        $message = $event->getMessage();

        if ($message !== null) {
            $event->setMessage(
                self::redactString($message),
                $event->getMessageParams(),
                $event->getMessageFormatted() !== null
                    ? self::redactString($event->getMessageFormatted())
                    : null
            );
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::redactString($exception->getValue()));

            // Stack-frame locals are the sneakiest leak: with
            // zend.exception_ignore_args=Off every local at every frame is
            // serialized, including decrypted credentials. We leave that ini at
            // its default On, and scrub defensively in case it is ever changed.
            $stacktrace = $exception->getStacktrace();

            if ($stacktrace === null) {
                continue;
            }

            foreach ($stacktrace->getFrames() as $frame) {
                $vars = $frame->getVars();

                if ($vars !== []) {
                    $frame->setVars(self::walk($vars));
                }
            }
        }
    }

    private static function scrubBreadcrumbs(Event $event): void
    {
        $scrubbed = [];

        foreach ($event->getBreadcrumbs() as $breadcrumb) {
            $metadata = self::walk($breadcrumb->getMetadata());
            $message = $breadcrumb->getMessage();

            $next = $breadcrumb;

            foreach ($metadata as $key => $value) {
                $next = $next->withMetadata($key, $value);
            }

            if ($message !== null) {
                $next = $next->withMessage(self::redactString($message));
            }

            $scrubbed[] = $next;
        }

        $event->setBreadcrumb($scrubbed);
    }

    /**
     * Recursively filter an array: sensitive keys are replaced outright,
     * remaining strings are pattern-redacted.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function walk(array $data, int $depth = 0): array
    {
        $maxDepth = (int) config('observability.scrubbing.max_depth', 8);
        $maxNodes = (int) config('observability.scrubbing.max_nodes', 2000);

        if ($depth >= $maxDepth) {
            return ['_truncated' => 'max depth reached'];
        }

        $out = [];

        foreach ($data as $key => $value) {
            if (++self::$nodes > $maxNodes) {
                $out['_truncated'] = 'max nodes reached';
                break;
            }

            if (self::isSensitiveKey((string) $key)) {
                $out[$key] = self::REDACTED;

                continue;
            }

            $out[$key] = match (true) {
                is_array($value) => self::walk($value, $depth + 1),
                is_string($value) => self::redactString($value),
                default => $value,
            };
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (config('observability.scrubbing.keys', []) as $needle) {
            if (str_contains($key, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact PEM blocks, message labels, phone numbers and emails from free
     * text. Ordered cheapest-first.
     */
    private static function redactString(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        // PEM private keys / certificates (SAML SP material).
        $value = (string) preg_replace(
            '/-----BEGIN [A-Z ]*(?:PRIVATE KEY|CERTIFICATE)-----[\s\S]*?-----END [A-Z ]*(?:PRIVATE KEY|CERTIFICATE)-----/',
            '[Redacted PEM]',
            $value
        );

        $labels = config('observability.scrubbing.message_labels', []);

        if ($labels !== []) {
            $pattern = '/\b('.implode('|', array_map(
                static fn ($l) => preg_quote((string) $l, '/'),
                $labels
            )).')\s*:\s*\S[^\r\n]*/i';

            $value = (string) preg_replace($pattern, '$1: '.self::REDACTED, $value);
        }

        if (config('observability.scrubbing.redact_email_addresses', true)) {
            $value = (string) preg_replace(
                '/[\w.+-]+@[\w-]+\.[\w.-]+/',
                '[Redacted Email]',
                $value
            );
        }

        if (config('observability.scrubbing.redact_phone_numbers', true)) {
            $value = (string) preg_replace(
                '/\+?\d{0,2}[\s.(-]{0,2}\d{3}[\s.)-]{0,2}\d{3}[\s.-]?\d{4}\b/',
                '[Redacted Phone]',
                $value
            );
        }

        $max = (int) config('observability.scrubbing.max_string_length', 2048);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max).'…' : $value;
    }
}
