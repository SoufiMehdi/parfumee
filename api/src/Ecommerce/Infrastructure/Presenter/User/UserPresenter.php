<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\User;

use App\Ecommerce\Domain\Model\User\User;

class UserPresenter
{
    public function present(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail()->getValue(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phoneNumber' => $user->getPhoneNumber()?->getValue(),
            'roles' => $user->getRoles(),
            'addresses' => array_map(fn($address) => [
                'id' => $address->getId(),
                'type' => $address->getType(),
                'street' => $address->getStreet(),
                'city' => $address->getCity(),
                'postalCode' => $address->getPostalCode(),
                'country' => $address->getCountry(),
                'isDefault' => $address->isDefault(),
            ], $user->getAddresses()),
        ];
    }

    /**
     * @param User[] $users
     */
    public function presentCollection(array $users): array
    {
        return array_map([$this, 'present'], $users);
    }
}
