<?php

namespace Omnisign\Docusign\Action;

use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisign\Request\Fetch;
use Omnisign\Request\Request;

/**
 * GET /envelopes/{id}?include=recipients. DocuSign allows one status request
 * per envelope every 15 minutes: the guard the factory gives counts it under
 * "pollEnvelope", the envelope named (a second one within the 15 minutes is
 * a UsageLimitReachedException, never sent).
 */
final class FetchAction extends AbstractAction
{
    /** @param ?PolicyGuard $envelopes the policy's limits per envelope (PolicyGuard::perResource()); null: none counted here */
    public function __construct(private readonly ?PolicyGuard $envelopes = null)
    {
        parent::__construct();
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Fetch;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Fetch);
        $reference = $request->envelope->reference();
        $poll = fn (): array => $this->api->json('GET', $this->api->envelopes().'/'.$reference, [], ['include' => 'recipients']);
        $request->setResult(self::read($request->envelope, null === $this->envelopes ? $poll() : $this->envelopes->call('pollEnvelope', $poll, ['resource' => $reference])));
    }
}
