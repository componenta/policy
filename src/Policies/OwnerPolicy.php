<?php

declare(strict_types=1);

namespace Componenta\Policy\Policies;

use Componenta\Identity\IdentityInterface;
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Exception\InvalidPolicyContextAttributeException;
use Componenta\Policy\Exception\MissingPolicyContextAttributeException;

/**
 * Allows the action when the actor owns the resource supplied in the context.
 *
 * Requires the actor to implement {@see IdentityInterface} and the resource
 * (under {@see self::ATTR_RESOURCE}) to implement {@see OwnableInterface}.
 * Applicable directly as a PHP attribute.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class OwnerPolicy extends AbstractPolicy
{
    public const string ATTR_RESOURCE = 'resource';

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if (!$actor instanceof IdentityInterface) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: IdentityInterface::class,
            );
        }

        if (!$context->hasAttribute(self::ATTR_RESOURCE)) {
            throw new MissingPolicyContextAttributeException(
                attribute: self::ATTR_RESOURCE,
                expectedType: OwnableInterface::class,
            );
        }

        $resource = $context->getAttribute(self::ATTR_RESOURCE);

        if (!$resource instanceof OwnableInterface) {
            throw InvalidPolicyContextAttributeException::expected(
                attribute: self::ATTR_RESOURCE,
                value: $resource,
                expectedType: OwnableInterface::class,
            );
        }

        return $resource->ownerId->equals($actor->uuid) ? true :
            $this->deny('Actor is not the owner of this resource');
    }
}