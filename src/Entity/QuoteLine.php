<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class QuoteLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 500)]
    private string $description = '';

    #[ORM\Column(precision: 10, scale: 2)]
    private string $quantity = '1.00';

    #[ORM\Column(precision: 12, scale: 2)]
    private string $unitPrice = '0.00';

    #[ORM\Column(precision: 5, scale: 2)]
    private string $vatRate = '20.00';

    #[ORM\Column(precision: 12, scale: 2)]
    private string $totalHt = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function getTotalHt(): string
    {
        return $this->totalHt;
    }
    public function setQuote(Quote $quote): static { $this->quote = $quote; return $this; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function setQuantity(string $quantity): static { $this->quantity = $quantity; return $this; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }
    public function setVatRate(string $vatRate): static { $this->vatRate = $vatRate; return $this; }
    public function setTotalHt(string $totalHt): static { $this->totalHt = $totalHt; return $this; }
}
