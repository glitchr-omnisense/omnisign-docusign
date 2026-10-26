# omnisign/docusign

DocuSign eSignature for [omnisense/omnisign](https://github.com/glitchr-omnisense/omnisign):
envelopes signed by e-mail or in the application's page, through the eSignature REST API v2.1,
authenticated by the JWT grant - an integration acting as one of the account's users. The gateway
calls `https://account-d.docusign.com/oauth/token` and `https://demo.docusign.net/restapi` (the
demo environment, `sandbox: true`, the default), or `https://account.docusign.com/oauth/token` and
the account's base URI read from `/oauth/userinfo` (`sandbox: false`).

## Installation

```sh
composer require omnisign/docusign
```

PHP 8.2 or later, `ext-openssl`, `omnisense/omnisign` and `symfony/http-client`. A DocuSign
integration (its key, an RSA key pair whose public half is on the integration), the user it acts as
- who granted it consent once -, and the account's id.

## The sources

Read on 2026-10-08: the eSignature REST API v2.1 OpenAPI
([docusign/OpenAPI-Specifications](https://github.com/docusign/OpenAPI-Specifications),
`esignature.rest.swagger-v2.1.json`, MIT) - envelopes, recipients, recipient views, documents
`combined` and `certificate`; DocuSign's guides on the JWT grant (`account-d.docusign.com` /
`account.docusign.com`, `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer`, claims `iss`,
`sub`, `aud`, `iat`, `exp`, `scope: signature impersonation`, RS256) and on Connect's HMAC
(`X-DocuSign-Signature-1`, `-2`..., base64 HMAC-SHA-256 of the raw body, one per active key).

## Credentials

- An **integration** in DocuSign's admin (Apps and Keys): its integration key, and an **RSA key
  pair** whose public half is on the integration (the private half is `private_key`).
- The **user** the integration acts as (`user_id`), who granted it consent once; until then the
  token is refused with `consent_required` (`InvalidKeyException`).
- The **API account id** (a GUID).
- For webhooks, **Connect** configured to send JSON with HMAC, and its keys (`hmac_keys`).

## Options

| Option | Default | |
|---|---|---|
| `integration_key`, `user_id`, `account_id` | required | |
| `private_key` | required | the RSA private key's PEM, or its path |
| `sandbox` | `true` | the demo environment (`account-d.docusign.com`, `demo.docusign.net`); `false`: production |
| `base_uri` | | the account's `https://<host>/restapi`; production: read from `/oauth/userinfo` when left empty |
| `hmac_keys` | | Connect's keys (a list, or comma-separated); without them, `notify()` is not supported |

## From an envelope to DocuSign's

| | |
|---|---|
| `create()` | `POST /v2.1/accounts/{id}/envelopes`, `status: created`: the documents in base64 (`documentId` 1, 2...), the signers (`recipientId` 1, 2..., `routingOrder` their turn when ordered, `clientUserId` their key when embedded) and their tabs - `SIGNATURE` `signHereTabs`, `INITIALS` `initialHereTabs`, `DATE` `dateSignedTabs`, `TEXT` and `MENTION` `textTabs`; a position (`pageNumber`, `xPosition`, `yPosition`) or an `anchorString`; an expiry in days |
| `send()` | `PUT .../envelopes/{id}`, `status: sent` |
| `fetch()` | `GET .../envelopes/{id}?include=recipients` |
| `remind()` | `PUT .../envelopes/{id}/recipients?resend_envelope=true` with the signers who have not signed |
| `cancel()` | `PUT .../envelopes/{id}`, `status: voided` with its `voidedReason` |
| `download()` | `GET .../documents/combined?certificate=false`, `GET .../documents/certificate` |
| `signingUrl()` | `POST .../views/recipient` (`returnUrl`, `authenticationMethod: none`, e-mail, name, `clientUserId`) - an envelope created embedded only; use the URL at once |
| `notify()` | Connect in JSON: the HMAC checked against each key, `event` read (`recipient-completed`: `SIGNED`, `envelope-completed`: `COMPLETED`...) |

Only the **simple** level, and no signer authentication: advanced and qualified signatures, an
access code, a phone check are the DocuSign account's settings, not written here - such an
envelope is refused.

| DocuSign | Status | | Recipient | SignerStatus |
|---|---|---|---|---|
| `created` | `DRAFT` | | `created` | `WAITING` |
| `sent`, `delivered` | `SENT` | | `sent` | `NOTIFIED` |
| `completed` | `COMPLETED` | | `delivered` | `OPENED` |
| `declined` | `DECLINED` | | `signed`, `completed` | `SIGNED` |
| `voided` | `CANCELED` (`EXPIRED` when voided for expiry) | | `declined` | `DECLINED` |

## Capabilities

| Request | Supported |
|---|---|
| `Create`, `Send`, `Fetch`, `Remind`, `Cancel`, `Download` | yes |
| `SigningUrl` | yes, for an envelope created embedded |
| `Notify` | with `hmac_keys` |

`capabilities()`: simple; in the page; ordered; no identity check; third party; documents; no
templates. `Signer::$phone`, `$locale` and `$role` are not sent; `Document::$signable` is not read
(every document is sent alike).

**Token.** A JWT (RS256, `iss` the integration key, `sub` the user, `aud` the account server, an
hour's life, `scope: signature impersonation`) exchanged for an access token, kept until a minute
before it lapses. A private key it cannot sign with is an `InvalidConfigException`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnisign\Docusign\DocusignGatewayFactory;
use Omnisign\Model\Document;
use Omnisign\Model\Envelope;
use Omnisign\Model\Field;
use Omnisign\Model\File;
use Omnisign\Model\Signer;
use Symfony\Component\HttpClient\HttpClient;

$docusign = (new DocusignGatewayFactory(HttpClient::create()))->create([
    'integration_key' => getenv('DOCUSIGN_INTEGRATION_KEY'),
    'user_id' => getenv('DOCUSIGN_USER_ID'),
    'private_key' => getenv('DOCUSIGN_PRIVATE_KEY'),
    'account_id' => getenv('DOCUSIGN_ACCOUNT_ID'),
]);

$envelope = $docusign->create(new Envelope('Devis 2026-042', [new Document('quote', File::fromPath('quote.pdf'))], [new Signer('client', 'Camille Érable', 'camille@erable.example')], [new Field('client', 'quote', anchor: '/sign/')], embedded: true, redirectUrl: 'https://site.example/quote/42/back'));
$envelope = $docusign->send($envelope);
$url = $docusign->signingUrl($envelope, 'client')->url;   // the recipient view, to use at once
```

Run on 2026-10-26 on a `MockHttpClient` serving this package's fixtures (the token, then the
envelope; a key pair generated for the run), with the Connect message signed as DocuSign does:

```
docusign
  > POST https://account-d.docusign.com/oauth/token
  > POST https://demo.docusign.net/restapi/v2.1/accounts/0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d/envelopes
  7c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f draft client=1 waiting
  notify: signed recipient-completed 7c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f 1 7c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f@recipient-completed@1@2026-10-26T09:00:00Z
```

DocuSign itself was not called. In Symfony:

```yaml
omnisign:
    gateways:
        quotes:
            factory: docusign
            options:
                integration_key: '%env(DOCUSIGN_INTEGRATION_KEY)%'
                user_id: '%env(DOCUSIGN_USER_ID)%'
                private_key: '%env(DOCUSIGN_PRIVATE_KEY)%'
                account_id: '%env(DOCUSIGN_ACCOUNT_ID)%'
                sandbox: false
                hmac_keys: '%env(default::DOCUSIGN_HMAC_KEYS)%'
```

## Errors

| Answer | Exception |
|---|---|
| the JWT grant answering 400 or more, or without `access_token` | `InvalidKeyException` (`The JWT grant was refused (HTTP 400): consent_required`, `providerCode` the `error`) |
| 401, 403 | `InvalidKeyException` |
| other 4xx | `ProviderException` (`<METHOD> <path>: HTTP <status>, <errorCode> <message>`, `providerCode` the `errorCode`) |
| 5xx, 429, no answer, a timeout (10 s) | `UnreachableException` |
| the account id not among the user's accounts (production, no `base_uri`) | `InvalidConfigException` |
| a private key it cannot sign with; a level other than simple; no document or no signer; a signer `authentication` other than none; a field on no document; a signing view without a return URL or for an envelope not embedded | `InvalidConfigException`, before the call |
| a Connect message that does not hold, or is not JSON | `InvalidNotificationException` |

## Verified, and not

| | |
|---|---|
| Against DocuSign | **not verified in real: no developer account.** The answers in `Tests/Fixtures` are written from the OpenAPI and the guides |
| The JWT: its header, claims and RS256 signature | by the tests, checked with the key pair's public half |
| The envelope's body, the tabs, the recipient view, the HMAC | by the tests, against the OpenAPI and the guides |

The package's tests (`Tests/DocusignGatewayTest.php`, 5 tests) pass on 2026-10-26 (PHP 8.4).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by DocuSign, Inc. Whoever runs it complies with DocuSign's
[Developer Terms and Conditions](https://www.docusign.com/legal/terms-and-conditions/developers);
see the README's Responsibility section.
