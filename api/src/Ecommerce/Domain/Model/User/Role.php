<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Model\User;

/**
 * The roles an account can hold. Each one includes the rights of the previous one:
 * an administrator can do what an editor can, an editor what a customer can.
 */
final class Role
{
    /** A customer: their own account and, later, their cart and orders. */
    public const string USER = 'ROLE_USER';

    /** Back-office staff in charge of the catalogue. No access to customer accounts. */
    public const string EDITOR = 'ROLE_EDITOR';

    /** Full access to the back-office. */
    public const string ADMIN = 'ROLE_ADMIN';

    public const array ALL = [self::USER, self::EDITOR, self::ADMIN];

    /** The roles that open the back-office. */
    public const array BACK_OFFICE = [self::EDITOR, self::ADMIN];
}
