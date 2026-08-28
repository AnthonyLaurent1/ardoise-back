<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use DateMalformedStringException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/quotes')]
final class QuoteController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(QuoteRepository $quoteRepository): JsonResponse
    {
        $quotes = $quoteRepository->findBy(
            ['company' => $this->company()],
            ['issuedAt' => 'DESC'],
        );

        return $this->json(array_map(
            fn (Quote $quote) => $this->data($quote),
            $quotes,
        ));
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ClientRepository $clientRepository,
        QuoteRepository $quoteRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide.'], 400);
        }

        $company = $this->company();
        $clientId = (int) ($data['clientId'] ?? 0);

        $client = $clientRepository->findOneBy([
            'id' => $clientId,
            'company' => $company,
        ]);

        if (!$client instanceof Client) {
            return $this->json(['message' => 'Client invalide.'], 422);
        }

        $lines = $data['lines'] ?? [];

        if (!is_array($lines) || count($lines) === 0) {
            return $this->json([
                'message' => 'Ajoutez au moins une ligne au devis.',
            ], 422);
        }

        $validityDays = max(1, min(365, (int) ($data['validityDays'] ?? 30)));
        $issuedAt = new \DateTimeImmutable();
        $quote = new Quote();

        $quote
            ->setCompany($company)
            ->setClient($client)
            ->setReference($this->nextReference($company, $quoteRepository))
            ->setStatus('draft')
            ->setIssuedAt($issuedAt)
            ->setValidUntil($issuedAt->modify("+{$validityDays} days"))
            ->setPaymentTerms($this->nullable($data['paymentTerms'] ?? null));

        $totalHt = 0.0;
        $totalTax = 0.0;

        foreach ($lines as $index => $lineData) {
            $description = trim((string) ($lineData['description'] ?? ''));
            $quantity = (float) ($lineData['quantity'] ?? 0);
            $unitPrice = (float) ($lineData['unitPrice'] ?? 0);
            $vatRate = (float) ($lineData['vatRate'] ?? 20);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0 || $vatRate < 0) {
                return $this->json([
                    'message' => 'Une ligne de devis est invalide.',
                ], 422);
            }

            $lineTotalHt = round($quantity * $unitPrice, 2);
            $lineTax = round($lineTotalHt * $vatRate / 100, 2);

            $totalHt += $lineTotalHt;
            $totalTax += $lineTax;

            $line = new QuoteLine();
            $line
                ->setQuote($quote)
                ->setPosition($index + 1)
                ->setDescription($description)
                ->setQuantity(number_format($quantity, 2, '.', ''))
                ->setUnitPrice(number_format($unitPrice, 2, '.', ''))
                ->setVatRate(number_format($vatRate, 2, '.', ''))
                ->setTotalHt(number_format($lineTotalHt, 2, '.', ''));

            $entityManager->persist($line);
        }

        $quote
            ->setTotalHt(number_format($totalHt, 2, '.', ''))
            ->setTotalTax(number_format($totalTax, 2, '.', ''))
            ->setTotalTtc(number_format($totalHt + $totalTax, 2, '.', ''));

        $entityManager->persist($quote);
        $entityManager->flush();

        return $this->json($this->data($quote), 201);
    }

    #[Route('/{id}', name: 'api_quotes_show', methods: ['GET'])]
    public function show(
        int $id,
        #[CurrentUser] User $user,
        QuoteRepository $quoteRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $quote = $quoteRepository->findOneBy([
            'id' => $id,
            'company' => $user->getCompany(),
        ]);

        if (!$quote) {
            return $this->json(['message' => 'Devis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        /** @var QuoteLine[] $lines */
        $lines = $entityManager->getRepository(QuoteLine::class)->findBy(
            ['quote' => $quote],
            ['position' => 'ASC'],
        );

        return $this->json($this->detailData($quote, $lines));
    }

    #[Route('/{id}', name: 'api_quotes_update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        #[CurrentUser] User $user,
        QuoteRepository $quoteRepository,
        ClientRepository $clientRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $quote = $quoteRepository->findOneBy([
            'id' => $id,
            'company' => $user->getCompany(),
        ]);

        if (!$quote) {
            return $this->json(['message' => 'Devis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($quote->getStatus() !== 'draft') {
            return $this->json(
                ['message' => 'Seuls les devis en brouillon peuvent être modifiés.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $clientId = $data['clientId'] ?? null;
        $lines = $data['lines'] ?? [];

        if (!$clientId || !is_array($lines) || count($lines) === 0) {
            return $this->json(
                ['message' => 'Le client et au moins une ligne sont obligatoires.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $client = $clientRepository->findOneBy([
            'id' => $clientId,
            'company' => $user->getCompany(),
        ]);

        if (!$client) {
            return $this->json(['message' => 'Client introuvable.'], Response::HTTP_NOT_FOUND);
        }

        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $quantity = (float) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unitPrice'] ?? 0);
            $vatRate = (float) ($line['vatRate'] ?? 0);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0 || $vatRate < 0) {
                return $this->json(
                    ['message' => 'Une ou plusieurs lignes du devis sont invalides.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        $quote->setClient($client);
        $quote->setPaymentTerms($this->nullable($data['paymentTerms'] ?? null));

        $validityDays = max(1, (int) ($data['validityDays'] ?? 30));
        $quote->setValidUntil(
            $quote->getIssuedAt()->modify(sprintf('+%d days', $validityDays)),
        );

        /** @var QuoteLine[] $oldLines */
        $oldLines = $entityManager->getRepository(QuoteLine::class)->findBy(['quote' => $quote]);

        foreach ($oldLines as $oldLine) {
            $entityManager->remove($oldLine);
        }

        $totalHt = 0.0;
        $totalTax = 0.0;

        foreach ($lines as $position => $line) {
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unitPrice'];
            $vatRate = (float) $line['vatRate'];
            $lineTotalHt = $quantity * $unitPrice;

            $quoteLine = new QuoteLine()
                ->setQuote($quote)
                ->setPosition($position + 1)
                ->setDescription(trim((string) $line['description']))
                ->setQuantity((string) $quantity)
                ->setUnitPrice((string) $unitPrice)
                ->setVatRate((string) $vatRate)
                ->setTotalHt((string) $lineTotalHt);

            $entityManager->persist($quoteLine);

            $totalHt += $lineTotalHt;
            $totalTax += $lineTotalHt * ($vatRate / 100);
        }

        $quote
            ->setTotalHt((string) $totalHt)
            ->setTotalTax((string) $totalTax)
            ->setTotalTtc((string) ($totalHt + $totalTax));

        $entityManager->flush();

        /** @var QuoteLine[] $updatedLines */
        $updatedLines = $entityManager->getRepository(QuoteLine::class)->findBy(
            ['quote' => $quote],
            ['position' => 'ASC'],
        );

        return $this->json($this->detailData($quote, $updatedLines));
    }

    private function company(): Company
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getCompany();
    }

    private function nextReference(
        Company $company,
        QuoteRepository $quoteRepository,
    ): string {
        $next = $quoteRepository->count(['company' => $company]) + 1;

        return sprintf('DEV-%s-%04d', date('Y'), $next);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function data(Quote $quote): array
    {
        return [
            'id' => $quote->getId(),
            'reference' => $quote->getReference(),
            'status' => $quote->getStatus(),
            'clientName' => $quote->getClient()?->getName(),
            'issuedAt' => $quote->getIssuedAt()?->format(DATE_ATOM),
            'validUntil' => $quote->getValidUntil()?->format(DATE_ATOM),
            'totalHt' => $quote->getTotalHt(),
            'totalTax' => $quote->getTotalTax(),
            'totalTtc' => $quote->getTotalTtc(),
        ];
    }

    /**
     * @param QuoteLine[] $lines
     */
    private function detailData(Quote $quote, array $lines): array
    {
        $client = $quote->getClient();

        return [
            'id' => $quote->getId(),
            'reference' => $quote->getReference(),
            'status' => $quote->getStatus(),
            'issuedAt' => $quote->getIssuedAt()->format('Y-m-d'),
            'validUntil' => $quote->getValidUntil()->format('Y-m-d'),
            'validityDays' => max(
                1,
                (int) $quote->getIssuedAt()->diff($quote->getValidUntil())->days,
            ),
            'paymentTerms' => $quote->getPaymentTerms(),
            'totalHt' => (float) $quote->getTotalHt(),
            'totalTax' => (float) $quote->getTotalTax(),
            'totalTtc' => (float) $quote->getTotalTtc(),
            'client' => [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'email' => $client->getEmail(),
                'address' => $client->getAddress(),
                'postalCode' => $client->getPostalCode(),
                'city' => $client->getCity(),
            ],
            'lines' => array_map(static fn (QuoteLine $line) => [
                'description' => $line->getDescription(),
                'quantity' => (float) $line->getQuantity(),
                'unitPrice' => (float) $line->getUnitPrice(),
                'vatRate' => (float) $line->getVatRate(),
            ], $lines),
        ];
    }
}
