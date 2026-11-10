<?php

namespace Omnisign\Docusign\Tests;

use Omnisign\Exception\UsageLimitReachedException;
use Omnisign\Model\Envelope;
use Omnisign\Registry;
use Omnisign\Docusign\DocusignGatewayFactory;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** DocuSign's usage policy: declared in policy.json, read by the gateway, enforced through the registry. */
final class PolicyTest extends TestCase
{
    private static string $privateKey = '';

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::$privateKey = \is_string($pem) ? $pem : '';
    }

    public function testThePolicyIsDeclaredAndRead(): void
    {
        $declared = UsagePolicy::fromFile(__DIR__.'/../policy.json');
        $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);

        self::assertIsArray($composer);
        $extra = $composer['extra'] ?? null;
        self::assertIsArray($extra);
        self::assertSame(['policy' => 'policy.json'], $extra['omnisense'] ?? null);
        self::assertSame('docusign', $declared->provider);
        self::assertSame('2026-11-10', $declared->provenance->verifiedAt);
        self::assertSame($declared->toArray(), DocusignGatewayFactory::usagePolicy()->toArray());
        self::assertNotNull($declared->rateLimits);
        self::assertSame([[3000, 'hour', 1], [500, 'second', 30]], array_map(static fn ($limit) => [$limit->max, $limit->window->value, $limit->every], $declared->rateLimits));

        $registry = new Registry([new DocusignGatewayFactory(new MockHttpClient())], ['docusign' => ['factory' => 'docusign', 'options' => self::options()]]);
        self::assertSame(DocusignGatewayFactory::sandboxPolicy()->toArray(), $registry->policy('docusign')->toArray());
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('{"error":"too many requests"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '30', 'content-type' => 'application/json']]);
        });
        $registry = new Registry([new DocusignGatewayFactory($http)], ['docusign' => ['factory' => 'docusign', 'options' => self::options()]]);

        try {
            $registry->get('docusign')->fetch(new Envelope('Bail', reference: 'env-1'));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        try {
            $registry->get('docusign')->fetch(new Envelope('Bail', reference: 'env-1'));
            self::fail('A call went out while DocuSign asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(29, $e->retryAfter);
        }
        self::assertSame(1, $requests, 'the wait held the next request back');
    }

    /** @return array<string, mixed> */
    private static function options(): array
    {
        return ['integration_key' => 'ik', 'user_id' => 'u', 'private_key' => self::$privateKey, 'account_id' => 'a'];
    }
}
