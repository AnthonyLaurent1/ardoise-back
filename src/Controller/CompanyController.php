<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/api/company')]
final class CompanyController extends AbstractController
{
    #[Route('/me', methods: ['PATCH'])]
    public function updateMyCompany(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        $company = $user?->getCompany();

        if (!$company instanceof Company) {
            return $this->json(['message' => 'Entreprise introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide.'], 400);
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return $this->json([
                'message' => 'Le nom de l’entreprise est obligatoire.',
            ], 422);
        }

        $company
            ->setName($name)
            ->setSiret($this->nullableValue($data['siret'] ?? null))
            ->setAddress($this->nullableValue($data['address'] ?? null))
            ->setPostalCode($this->nullableValue($data['postalCode'] ?? null))
            ->setCity($this->nullableValue($data['city'] ?? null))
            ->setWebsite($this->nullableValue($data['website'] ?? null))
            ->setContactEmail($this->nullableValue($data['contactEmail'] ?? null))
            ->setPhone($this->nullableValue($data['phone'] ?? null))
            ->setVatNumber($this->nullableValue($data['vatNumber'] ?? null))
            ->setDefaultPaymentTerms(
                $this->nullableValue($data['defaultPaymentTerms'] ?? null),
            );

        $entityManager->flush();

        return $this->json($this->companyData($company));
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function companyData(Company $company): array
    {
        return [
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
        ];
    }
}
