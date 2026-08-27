<?php

namespace App\Controller\Auth;

use App\Entity\Company;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide.'], 400);
        }

        $email = strtolower(trim($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $companyName = trim($data['companyName'] ?? '');
        $firstName = trim($data['firstName'] ?? '');
        $lastName = trim($data['lastName'] ?? '');

        if ($firstName === '' || $lastName === '') {
            return $this->json([
                'message' => 'Le prénom et le nom sont obligatoires.',
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse email invalide.'], 422);
        }

        if (strlen($password) < 8) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir au moins 8 caractères.',
            ], 422);
        }

        if ($companyName === '') {
            return $this->json([
                'message' => 'Le nom de l’entreprise est obligatoire.',
            ], 422);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'message' => 'Un compte existe déjà avec cette adresse email.',
            ], 409);
        }

        $company = new Company();
        $company
            ->setName($companyName)
            ->setSiret($data['siret'] ?? null)
            ->setAddress($data['address'] ?? null)
            ->setPostalCode($data['postalCode'] ?? null)
            ->setCity($data['city'] ?? null)
            ->setWebsite($data['website'] ?? null)
            ->setContactEmail($data['contactEmail'] ?? null)
            ->setPhone($data['phone'] ?? null)
            ->setVatNumber($data['vatNumber'] ?? null)
            ->setDefaultPaymentTerms($data['defaultPaymentTerms'] ?? null);

        $rawToken = bin2hex(random_bytes(32));

        $user = new User();
        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles(['ROLE_USER'])
            ->setCompany($company)
            ->setIsVerified(false)
            ->setEmailVerificationToken(hash('sha256', $rawToken))
            ->setEmailVerificationExpiresAt(
                new \DateTimeImmutable('+24 hours'),
            );

        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($company);
        $entityManager->persist($user);
        $entityManager->flush();

        $verificationUrl = $urlGenerator->generate(
            'api_auth_verify_email',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $mailer->send(
            new TemplatedEmail()
                ->from('Ardoise <no-reply@ardoise.test>')
                ->to($user->getEmail())
                ->subject('Confirmez votre adresse email')
                ->htmlTemplate('emails/verify-email.html.twig')
                ->context([
                    'companyName' => $company->getName(),
                    'verificationUrl' => $verificationUrl,
                ]),
        );

        return $this->json([
            'message' => 'Un email de confirmation vient de vous être envoyé.',
        ], 201);
    }

    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $rawToken = (string) $request->query->get('token', '');

        $user = $userRepository->findOneBy([
            'emailVerificationToken' => hash('sha256', $rawToken),
        ]);

        if (
            !$user ||
            !$user->getEmailVerificationExpiresAt() ||
            $user->getEmailVerificationExpiresAt() < new \DateTimeImmutable()
        ) {
            return new RedirectResponse(
                'http://localhost:4200/connexion?verified=invalid',
            );
        }

        $user
            ->setIsVerified(true)
            ->setEmailVerificationToken(null)
            ->setEmailVerificationExpiresAt(null);

        $entityManager->flush();

        return new RedirectResponse(
            'http://localhost:4200/connexion?verified=success',
        );
    }
}
