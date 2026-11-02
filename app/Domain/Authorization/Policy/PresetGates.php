<?php

namespace App\Domain\Authorization\Policy;

use App\Domain\Authorization\Policy\ActionClassifier as C;

/**
 * Default gate per action class and governance preset — the preset table in
 * docs/architecture/governance-model.md ("Action classes and default gates").
 *
 * Owns defaults only. Invariants (floor deny, explicit deny, trigger-started
 * external actions always asking, no "always" exception for purge/exposure/
 * structural) are enforced by AgentPermissionResolver BEFORE and AFTER this
 * lookup and can never be relaxed by a preset. Two-approver and
 * admin-approver requirements are represented as plain approval for now; the
 * approver policy is a later Governance UI concern.
 */
final class PresetGates
{
    public const ALLOW = 'allow';
    public const APPROVAL = 'approval';
    public const DENY = 'deny';

    /** Classes where an `always` exception is never honoured: each instance asks. */
    public const NO_ALWAYS_EXCEPTION = [C::PURGE, C::EXPOSURE_WIDENING, C::STRUCTURAL];

    /** @var array<string, array{personal: string, team: string, company: string}> */
    private const TABLE = [
        C::READ => ['personal' => self::ALLOW, 'team' => self::ALLOW, 'company' => self::ALLOW],
        C::REVERSIBLE_EDIT => ['personal' => self::ALLOW, 'team' => self::ALLOW, 'company' => self::ALLOW],
        C::DELETE => ['personal' => self::ALLOW, 'team' => self::ALLOW, 'company' => self::APPROVAL],
        C::PURGE => ['personal' => self::ALLOW, 'team' => self::APPROVAL, 'company' => self::APPROVAL],
        C::EXTERNAL_DRAFT => ['personal' => self::ALLOW, 'team' => self::ALLOW, 'company' => self::ALLOW],
        C::EXTERNAL_SEND => ['personal' => self::APPROVAL, 'team' => self::APPROVAL, 'company' => self::APPROVAL],
        C::SPEND => ['personal' => self::APPROVAL, 'team' => self::APPROVAL, 'company' => self::APPROVAL],
        C::EXPOSURE_WIDENING => ['personal' => self::APPROVAL, 'team' => self::APPROVAL, 'company' => self::APPROVAL],
        C::STRUCTURAL => ['personal' => self::APPROVAL, 'team' => self::APPROVAL, 'company' => self::APPROVAL],
        C::GOVERNANCE => ['personal' => self::APPROVAL, 'team' => self::APPROVAL, 'company' => self::APPROVAL],
        C::FLOOR => ['personal' => self::DENY, 'team' => self::DENY, 'company' => self::DENY],
    ];

    public function gate(string $actionClass, string $preset): string
    {
        // Unknown classes fail closed to the external_send gate.
        $row = self::TABLE[$actionClass] ?? self::TABLE[C::EXTERNAL_SEND];

        return $row[$preset] ?? $row['team'];
    }
}
