<?php

namespace App\Controller\Auth;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MeController extends AbstractController
{
    #[Route('/api/me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'message' => 'Non authentifié.',
            ], 401);
        }

        $company = $user->getCompany();

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ],
            'company' => [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'siret' => $company->getSiret(),
                'address' => $company->getAddress(),
                'postalCode' => $company->getPostalCode(),
                'city' => $company->getCity(),
                'website' => $company->getWebsite(),
                'contactEmail' => $company->getContactEmail(),
                'phone' => $company->getPhone(),
                'vatNumber' => $company->getVatNumber(),
                'defaultPaymentTerms' => $company->getDefaultPaymentTerms(),
            ],
        ]);
    }
}
