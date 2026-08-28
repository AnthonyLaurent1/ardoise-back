<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\User;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/clients')]
final class ClientController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(ClientRepository $clientRepository): JsonResponse
    {
        $company = $this->company();

        $clients = $clientRepository->findBy(
            ['company' => $company],
            ['name' => 'ASC'],
        );

        return $this->json(array_map(
            fn (Client $client) => $this->data($client),
            $clients,
        ));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(
        int $id,
        ClientRepository $clientRepository,
    ): JsonResponse {
        $client = $clientRepository->findOneBy([
            'id' => $id,
            'company' => $this->company(),
        ]);

        if (!$client) {
            return $this->json(['message' => 'Client introuvable.'], 404);
        }

        return $this->json($this->data($client));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = $this->requestData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $client = new Client();
        $client->setCompany($this->company());

        $error = $this->hydrate($client, $data);

        if ($error) {
            return $this->json(['message' => $error], 422);
        }

        $entityManager->persist($client);
        $entityManager->flush();

        return $this->json($this->data($client), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        ClientRepository $clientRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $client = $clientRepository->findOneBy([
            'id' => $id,
            'company' => $this->company(),
        ]);

        if (!$client) {
            return $this->json(['message' => 'Client introuvable.'], 404);
        }

        $data = $this->requestData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $error = $this->hydrate($client, $data);

        if ($error) {
            return $this->json(['message' => $error], 422);
        }

        $entityManager->flush();

        return $this->json($this->data($client));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        int $id,
        ClientRepository $clientRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $client = $clientRepository->findOneBy([
            'id' => $id,
            'company' => $this->company(),
        ]);

        if (!$client) {
            return $this->json(['message' => 'Client introuvable.'], 404);
        }

        $entityManager->remove($client);
        $entityManager->flush();

        return $this->json(null, 204);
    }

    private function company(): Company
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getCompany();
    }

    private function requestData(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide.'], 400);
        }

        return $data;
    }

    private function hydrate(Client $client, array $data): ?string
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['type'] ?? '');

        if ($name === '') {
            return 'Le nom du client est obligatoire.';
        }

        if (!in_array($type, ['individual', 'business'], true)) {
            return 'Le type de client est invalide.';
        }

        $email = $this->nullable($data['email'] ?? null);

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse email invalide.';
        }

        $client
            ->setName($name)
            ->setType($type)
            ->setContactName($this->nullable($data['contactName'] ?? null))
            ->setEmail($email)
            ->setPhone($this->nullable($data['phone'] ?? null))
            ->setAddress($this->nullable($data['address'] ?? null))
            ->setPostalCode($this->nullable($data['postalCode'] ?? null))
            ->setCity($this->nullable($data['city'] ?? null));

        return null;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function data(Client $client): array
    {
        return [
            'id' => $client->getId(),
            'type' => $client->getType(),
            'name' => $client->getName(),
            'contactName' => $client->getContactName(),
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
            'address' => $client->getAddress(),
            'postalCode' => $client->getPostalCode(),
            'city' => $client->getCity(),
            'createdAt' => $client->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
