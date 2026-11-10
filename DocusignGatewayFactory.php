<?php

namespace Omnisign\Docusign;

use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisign\Config;
use Omnisign\Docusign\Action\CancelAction;
use Omnisign\Docusign\Action\CreateAction;
use Omnisign\Docusign\Action\DownloadAction;
use Omnisign\Docusign\Action\FetchAction;
use Omnisign\Docusign\Action\NotifyAction;
use Omnisign\Docusign\Action\RemindAction;
use Omnisign\Docusign\Action\SendAction;
use Omnisign\Docusign\Action\SigningUrlAction;
use Omnisign\GatewayFactory;
use Omnisign\Model\Capabilities;
use Omnisign\Model\Level;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * DocuSign eSignature: envelopes, by e-mail or signed in the application's
 * page, the JWT grant for an integration acting as one of the account's
 * users.
 *
 *   options:
 *     integration_key: '%env(DOCUSIGN_INTEGRATION_KEY)%'   # required: the app's client id
 *     user_id: '%env(DOCUSIGN_USER_ID)%'                   # required: the user it acts as (who consented once)
 *     private_key: '%env(DOCUSIGN_PRIVATE_KEY)%'           # required: the RSA key's PEM, or its path
 *     account_id: '%env(DOCUSIGN_ACCOUNT_ID)%'             # required: the API account id (GUID)
 *     sandbox: true                                        # account-d.docusign.com and demo.docusign.net; false: production
 *     base_uri: ~                                          # https://demo.docusign.net/restapi; production: the userinfo's when left empty
 *     hmac_keys: '%env(default::DOCUSIGN_HMAC_KEYS)%'      # Connect's HMAC keys, comma-separated: notify() checks X-DocuSign-Signature-n
 */
final class DocusignGatewayFactory extends GatewayFactory
{
    private static ?UsagePolicy $policy = null;

    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    /** Its usage policy, as policy.json declares it (read once). */
    public static function usagePolicy(): UsagePolicy
    {
        return self::$policy ??= UsagePolicy::fromFile(__DIR__.'/policy.json');
    }

    /** In the sandbox, its usage policy with the sandbox's own limits. */
    public static function sandboxPolicy(): UsagePolicy
    {
        return self::usagePolicy()->tighten(['rateLimits' => [['max' => 200, 'window' => 'second', 'every' => 30, 'scope' => 'key', 'note' => 'Developer environment: a burst of 200 calls in 30 seconds']]]);
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnisign.factory_name' => 'docusign',
            'omnisign.factory_title' => 'DocuSign',
            'omnisign.required_options' => ['integration_key', 'user_id', 'private_key', 'account_id'],
            'sandbox' => true,
            'base_uri' => null,
            'hmac_keys' => [],
            'omnisign.capabilities' => static fn (Config $c) => new Capabilities([Level::SIMPLE], embedded: true, ordered: true, identityCheck: false, thirdParty: true, policy: $c->bool('sandbox') ? self::sandboxPolicy() : self::usagePolicy()),
            'omnisign.api' => function (Config $c): Api {
                $sandbox = $c->bool('sandbox');

                return new Api(
                    $this->http ?? HttpClient::create(),
                    (string) $c['integration_key'],
                    (string) $c['user_id'],
                    (string) $c['private_key'],
                    (string) $c['account_id'],
                    $c->get('base_uri') ? rtrim((string) $c['base_uri'], '/') : ($sandbox ? 'https://demo.docusign.net/restapi' : null),
                    $sandbox ? Api::DEMO_OAUTH : Api::OAUTH,
                    $c->list('hmac_keys'),
                    backoff: $c->backoff(),
                );
            },
            'omnisign.action.create' => new CreateAction(),
            'omnisign.action.send' => new SendAction(),
            'omnisign.action.fetch' => new FetchAction(),
            'omnisign.action.remind' => new RemindAction(),
            'omnisign.action.cancel' => new CancelAction(),
            'omnisign.action.download' => new DownloadAction(),
            'omnisign.action.signing_url' => new SigningUrlAction(),
            'omnisign.action.notify' => static fn (Config $c) => $c->list('hmac_keys') ? new NotifyAction() : null,
        ]);
    }
}
