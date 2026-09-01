<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Company $company = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Client $client = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quote $quote = null;

    #[ORM\Column(length: 30)]
    private string $reference = '';

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paymentTerms = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalTax = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalTtc = '0.00';

    #[ORM\Column(type: 'json')]
    private array $lines = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int { return $this->id; }

    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(Company $company): static { $this->company = $company; return $this; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(Client $client): static { $this->client = $client; return $this; }

    public function getQuote(): ?Quote { return $this->quote; }
    public function setQuote(?Quote $quote): static { $this->quote = $quote; return $this; }

    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getIssuedAt(): ?\DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeImmutable $issuedAt): static { $this->issuedAt = $issuedAt; return $this; }

    public function getDueAt(): ?\DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(\DateTimeImmutable $dueAt): static { $this->dueAt = $dueAt; return $this; }

    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
    public function setPaymentTerms(?string $paymentTerms): static { $this->paymentTerms = $paymentTerms; return $this; }

    public function getTotalHt(): string { return $this->totalHt; }
    public function setTotalHt(string $totalHt): static { $this->totalHt = $totalHt; return $this; }

    public function getTotalTax(): string { return $this->totalTax; }
    public function setTotalTax(string $totalTax): static { $this->totalTax = $totalTax; return $this; }

    public function getTotalTtc(): string { return $this->totalTtc; }
    public function setTotalTtc(string $totalTtc): static { $this->totalTtc = $totalTtc; return $this; }

    public function getLines(): array { return $this->lines; }
    public function setLines(array $lines): static { $this->lines = $lines; return $this; }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
