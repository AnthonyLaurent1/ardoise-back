<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\QuoteLine;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\Client;
use App\Repository\ClientRepository;

#[Route('/api/invoices')]
final class InvoiceController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        InvoiceRepository $invoiceRepository,
    ): JsonResponse {
        $invoices = $invoiceRepository->findBy(
            ['company' => $user->getCompany()],
            ['issuedAt' => 'DESC'],
        );

        return $this->json(array_map(
            fn (Invoice $invoice) => $this->data($invoice),
            $invoices,
        ));
    }

    #[Route('/from-quote', methods: ['POST'])]
    public function createFromQuote(
        Request $request,
        #[CurrentUser] User $user,
        QuoteRepository $quoteRepository,
        InvoiceRepository $invoiceRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = $request->toArray();

        $quote = $quoteRepository->findOneBy([
            'id' => (int) ($data['quoteId'] ?? 0),
            'company' => $user->getCompany(),
        ]);

        if (!$quote) {
            return $this->json(['message' => 'Devis introuvable.'], 404);
        }

        if ($quote->getStatus() !== 'accepted') {
            return $this->json(
                ['message' => 'Seul un devis accepté peut être facturé.'],
                422,
            );
        }

        if ($invoiceRepository->findOneBy(['quote' => $quote])) {
            return $this->json(
                ['message' => 'Une facture existe déjà pour ce devis.'],
                422,
            );
        }

        $status = ($data['status'] ?? 'draft') === 'sent' ? 'sent' : 'draft';

        try {
            $issuedAt = new \DateTimeImmutable($data['issuedAt'] ?? 'today');
            $dueAt = new \DateTimeImmutable($data['dueAt'] ?? 'today');
        } catch (\Throwable) {
            return $this->json(['message' => 'Dates de facture invalides.'], 422);
        }

        if ($dueAt < $issuedAt) {
            return $this->json(
                ['message' => 'La date d’échéance doit être après la date d’émission.'],
                422,
            );
        }

        /** @var QuoteLine[] $quoteLines */
        $quoteLines = $entityManager->getRepository(QuoteLine::class)->findBy(
            ['quote' => $quote],
            ['position' => 'ASC'],
        );

        $lines = array_map(static fn (QuoteLine $line) => [
            'description' => $line->getDescription(),
            'quantity' => (float) $line->getQuantity(),
            'unitPrice' => (float) $line->getUnitPrice(),
            'vatRate' => (float) $line->getVatRate(),
            'totalHt' => (float) $line->getTotalHt(),
        ], $quoteLines);

        $invoice = new Invoice()
            ->setCompany($user->getCompany())
            ->setClient($quote->getClient())
            ->setQuote($quote)
            ->setReference($this->nextReference($user->getCompany(), $invoiceRepository))
            ->setStatus($status)
            ->setIssuedAt($issuedAt)
            ->setDueAt($dueAt)
            ->setPaymentTerms($this->nullable($data['paymentTerms'] ?? null))
            ->setLines($lines)
            ->setTotalHt($quote->getTotalHt())
            ->setTotalTax($quote->getTotalTax())
            ->setTotalTtc($quote->getTotalTtc());

        $entityManager->persist($invoice);
        $entityManager->flush();

        return $this->json($this->data($invoice), 201);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] User $user,
        ClientRepository $clientRepository,
        InvoiceRepository $invoiceRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = $request->toArray();

        $client = $clientRepository->findOneBy([
            'id' => (int) ($data['clientId'] ?? 0),
            'company' => $user->getCompany(),
        ]);

        if (!$client instanceof Client) {
            return $this->json(['message' => 'Client invalide.'], 422);
        }

        $rawLines = $data['lines'] ?? [];

        if (!is_array($rawLines) || count($rawLines) === 0) {
            return $this->json(
                ['message' => 'Ajoutez au moins une ligne à la facture.'],
                422,
            );
        }

        try {
            $issuedAt = new \DateTimeImmutable($data['issuedAt'] ?? 'today');
            $dueAt = new \DateTimeImmutable($data['dueAt'] ?? 'today');
        } catch (\Throwable) {
            return $this->json(['message' => 'Dates invalides.'], 422);
        }

        $totalHt = 0.0;
        $totalTax = 0.0;
        $lines = [];

        foreach ($rawLines as $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $quantity = (float) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unitPrice'] ?? 0);
            $vatRate = (float) ($line['vatRate'] ?? 0);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0 || $vatRate < 0) {
                return $this->json(
                    ['message' => 'Une ligne de facture est invalide.'],
                    422,
                );
            }

            $lineTotalHt = round($quantity * $unitPrice, 2);

            $lines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'vatRate' => $vatRate,
                'totalHt' => $lineTotalHt,
            ];

            $totalHt += $lineTotalHt;
            $totalTax += $lineTotalHt * ($vatRate / 100);
        }

        $status = ($data['status'] ?? 'draft') === 'sent' ? 'sent' : 'draft';

        $invoice = new Invoice()
            ->setCompany($user->getCompany())
            ->setClient($client)
            ->setQuote(null)
            ->setReference($this->nextReference($user->getCompany(), $invoiceRepository))
            ->setStatus($status)
            ->setIssuedAt($issuedAt)
            ->setDueAt($dueAt)
            ->setPaymentTerms($this->nullable($data['paymentTerms'] ?? null))
            ->setLines($lines)
            ->setTotalHt(number_format($totalHt, 2, '.', ''))
            ->setTotalTax(number_format($totalTax, 2, '.', ''))
            ->setTotalTtc(number_format($totalHt + $totalTax, 2, '.', ''));

        $entityManager->persist($invoice);
        $entityManager->flush();

        return $this->json($this->data($invoice), 201);
    }

    private function nextReference($company, InvoiceRepository $invoiceRepository): string
    {
        $next = $invoiceRepository->count(['company' => $company]) + 1;

        return sprintf('FAC-%s-%04d', date('Y'), $next);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function data(Invoice $invoice): array
    {
        return [
            'id' => $invoice->getId(),
            'reference' => $invoice->getReference(),
            'status' => $invoice->getStatus(),
            'clientName' => $invoice->getClient()->getName(),
            'issuedAt' => $invoice->getIssuedAt()->format(DATE_ATOM),
            'dueAt' => $invoice->getDueAt()->format(DATE_ATOM),
            'totalTtc' => $invoice->getTotalTtc(),
            'quoteReference' => $invoice->getQuote()?->getReference(),        ];
    }
}
