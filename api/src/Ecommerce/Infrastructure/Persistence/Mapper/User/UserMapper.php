<?php

namespace App\Ecommerce\Infrastructure\Persistence\Mapper\User;

use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineAddress;

class UserMapper
{
    public function toInfrastructure(User $domainUser): DoctrineUser
    {
        $entity = new DoctrineUser(
            $domainUser->getId(),
            $domainUser->getEmail()->getValue(),
            $domainUser->getPasswordHash(),
            $domainUser->getFirstName(),
            $domainUser->getLastName()
        );
        $entity->setRoles($domainUser->getRoles());

        if ($phone = $domainUser->getPhoneNumber()) {
            $entity->setPhoneNumber($phone->getValue());
        }

        foreach ($domainUser->getAddresses() as $address) {
            $doctrineAddress = new DoctrineAddress(
                $address->getId(),
                $entity,
                $address->getType(),
                $address->getStreet(),
                $address->getCity(),
                $address->getPostalCode(),
                $address->getCountry(),
                $address->isDefault()
            );
            $entity->addAddress($doctrineAddress);
        }

        return $entity;
    }

    public function mapDomainToEntity(User $domainUser, DoctrineUser $entity): void
    {
        $entity->setEmail($domainUser->getEmail()->getValue());
        $entity->setFirstName($domainUser->getFirstName());
        $entity->setLastName($domainUser->getLastName());
        $entity->setPassword($domainUser->getPasswordHash());
        $entity->setRoles($domainUser->getRoles());

        if ($phone = $domainUser->getPhoneNumber()) {
            $entity->setPhoneNumber($phone->getValue());
        } else {
            $entity->setPhoneNumber(null);
        }
    }

    public function toDomain(DoctrineUser $entity): User
    {
        $phoneNumber = $entity->getPhoneNumber() !== null
            ? new PhoneNumber($entity->getPhoneNumber())
            : null;

        $addresses = [];
        foreach ($entity->getAddresses() as $doctrineAddress) {
            $addresses[] = new Address(
                $doctrineAddress->getId(),
                $doctrineAddress->getType(),
                $doctrineAddress->getStreet(),
                $doctrineAddress->getCity(),
                $doctrineAddress->getPostalCode(),
                $doctrineAddress->getCountry(),
                $doctrineAddress->isDefault()
            );
        }

        return new User(
            $entity->getId(),
            new Email($entity->getEmail()),
            $entity->getPassword(),
            $entity->getFirstName(),
            $entity->getLastName(),
            $phoneNumber,
            $entity->getRoles(),
            $addresses
        );
    }
}