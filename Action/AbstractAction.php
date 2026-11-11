<?php

namespace Omnisign\Docusign\Action;

use Omnisign\Action\ActionInterface;
use Omnisign\Action\ApiAwareInterface;
use Omnisign\Action\ApiAwareTrait;
use Omnisign\Docusign\Api;
use Omnisign\Docusign\Data;
use Omnisign\Model\Envelope;
use Omnisign\Model\SignerState;
use Omnisign\Model\SignerStatus;
use Omnisign\Model\Status;

/** An action on DocuSign's eSignature REST API v2.1. */
abstract class AbstractAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    protected const STATUSES = ['created' => Status::DRAFT, 'sent' => Status::SENT, 'delivered' => Status::SENT, 'signed' => Status::SENT, 'completed' => Status::COMPLETED, 'declined' => Status::DECLINED, 'voided' => Status::CANCELED];
    protected const SIGNER_STATUSES = ['created' => SignerStatus::WAITING, 'sent' => SignerStatus::NOTIFIED, 'delivered' => SignerStatus::OPENED, 'signed' => SignerStatus::SIGNED, 'completed' => SignerStatus::SIGNED, 'declined' => SignerStatus::DECLINED, 'autoresponded' => SignerStatus::FAILED];

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * The envelope as DocuSign answered it: its status and, with the
     * recipients, each signer's - found back by recipientId.
     *
     * @param array<string, mixed> $answer
     */
    protected static function read(Envelope $envelope, array $answer): Envelope
    {
        $states = $envelope->states;
        $keys = [];
        foreach ($states as $key => $state) {
            $keys[(string) $state->reference] = $key;
        }
        $read = Data::of($answer);
        foreach ((array) ($read->data('recipients')->get('signers') ?? []) as $signer) {
            $signer = Data::of($signer);
            $key = $keys[$signer->text('recipientId')] ?? null;
            if (null === $key) {
                continue;
            }
            $old = $states[$key];
            $states[$key] = new SignerState($key, $old->reference, self::SIGNER_STATUSES[$signer->text('status')] ?? $old->status, $signer->moment('signedDateTime') ?? $old->signedAt, $old->link, $old->linkExpiresAt, $signer->textOrNull('declinedReason') ?? $old->reason);
        }
        $status = $read->text('status');

        return $envelope->with(
            reference: $read->textOrNull('envelopeId') ?? (string) $envelope->reference,
            // A voided envelope past its expiry: DocuSign voids what expires.
            status: 'voided' === $status && str_contains(strtolower($read->text('voidedReason')), 'expired') ? Status::EXPIRED : (self::STATUSES[$status] ?? Status::UNKNOWN),
            states: $states,
            completedAt: $read->moment('completedDateTime'),
            data: $answer,
        );
    }
}
