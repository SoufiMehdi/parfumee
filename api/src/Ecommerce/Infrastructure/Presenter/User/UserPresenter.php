<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\User;

use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Infrastructure\Presenter\User\View\AddressView;
use App\Ecommerce\Infrastructure\Presenter\User\View\UserView;

/**
 * Turns a domain user into what the API returns. The views are also what
 * the OpenAPI contract is generated from.
 */
class UserPresenter
{
    public function present(User $user): UserView
    {
        return new UserView(
            $user->getId(),
            $user->getEmail()->getValue(),
            $user->getFirstName(),
            $user->getLastName(),
            $user->getPhoneNumber()?->getValue(),
            array_values($user->getRoles()),
            array_values(array_map(
                static fn (Address $address): AddressView => new AddressView(
                    $address->getId(),
                    $address->getType(),
                    $address->getStreet(),
                    $address->getCity(),
                    $address->getPostalCode(),
                    $address->getCountry(),
                    $address->isDefault(),
                ),
                $user->getAddresses(),
            )),
        );
    }

    /**
     * @param User[] $users
     *
     * @return list<UserView>
     */
    public function presentCollection(array $users): array
    {
        return array_values(array_map($this->present(...), $users));
    }
}
