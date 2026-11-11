# omnisign/docusign

**DocuSign eSignature** for [omnisense/omnisign](https://github.com/glitchr-omnisense/omnisign):
envelopes through the REST API v2.1, by e-mail or signed in the application's page, the JWT grant
for an integration acting as one of the account's users, Connect webhooks with their HMAC checked.

```php
use Omnisign\Docusign\DocusignGatewayFactory;

$gateway = (new DocusignGatewayFactory($httpClient))->create([
    'integration_key' => $integrationKey, 'user_id' => $userId,
    'private_key' => '/run/secrets/docusign.pem', 'account_id' => $accountId,
    'sandbox' => true,
]);

$envelope = $gateway->send($gateway->create($lease));     // POST /envelopes (created), then sent
$gateway->signingUrl($envelope, 'tenant', $returnUrl);     // a recipient view (embedded signers)
```

Written from DocuSign's eSignature REST API v2.1 OpenAPI and its guides. **Not verified in real:
no developer account.**

[Documentation](docs/index.md): the options and the JWT grant, from an envelope to DocuSign's,
Connect, what was verified.

## Usage policy

`policy.json`, read on 2026-11-10 in DocuSign's [rules and limits](https://developers.docusign.com/docs/esign-rest-api/esign101/rules-and-limits/)
and [developer terms](https://www.docusign.com/legal/terms-and-conditions/developers): 3,000 calls an hour per account,
a burst of 500 in 30 seconds (200 in the developer environment, declared when `sandbox` is true).
One status poll per envelope every 15 minutes, counted per envelope (`"scope": "resource"`,
`"per": "envelope"`): `fetch()` of an envelope polled less than 15 minutes ago is a
`UsageLimitReachedException`, never sent (omnisense/contracts 2f78f0e or later; with an older one
the limit is left out and nothing is counted per envelope). Prefer Connect webhooks. A 429 holds
the gateway's next calls.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Docusign, Inc. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence covers
this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Docusign's terms
([Developer Terms and Conditions](https://www.docusign.com/legal/terms-and-conditions/developers)),
for obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors publish
it free of charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
