<?php

namespace MLukman\SaasBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MLukman\SaasBundle\Base\PaymentProvider;
use MLukman\SaasBundle\Base\PaymentTransactionInterface;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\DTO\HitpayCheckoutTransaction;
use MLukman\SaasBundle\Entity\Payment;
use MLukman\SaasBundle\Entity\PayoutAccount;
use MLukman\SaasBundle\Entity\PayoutPayment;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HitpayProvider extends PaymentProvider
{
    protected const API_BASE_URL_SANDBOX = 'https://api.sandbox.hit-pay.com/v1/';
    protected const API_BASE_URL_LIVE = 'https://api.hitpayapp.com/v1/';

    protected HttpClientInterface $client;
    protected ?string $apiSalt;

    public function __construct(EntityManagerInterface $em, SaasPrepaidManager $prepaidManager, protected HttpClientInterface $baseClient)
    {
        parent::__construct($em, $prepaidManager);
    }

    public function getId(): string
    {
        return "hitpay";
    }

    public function initialize(array $paymentConfigParams): bool
    {
        if (empty($paymentConfigParams['api_key'] ?? null)) {
            throw new InvalidSaasConfigurationException("Hitpay payment provider requires the Hitpay API business API key to be configured in the 'saas.payment.api_key' configuration key");
        }
        $this->apiSalt = $paymentConfigParams['api_salt'] ?? null;

        $this->client = $this->baseClient->withOptions([
            'base_uri' => ($paymentConfigParams['sandbox'] ?? false) ? self::API_BASE_URL_SANDBOX : self::API_BASE_URL_LIVE,
            'headers' => [
                'X-BUSINESS-API-KEY' => $paymentConfigParams['api_key'],
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ]);
        return true;
    }

    public function initiateCreditPurchaseTransaction(TopupConfig $topup, int $quantity, string $redirectBackUrl): ?PaymentTransactionInterface
    {
        $currency = array_keys($topup->getPrices())[0];
        $price = $topup->getPrices()[$currency];
        return $this->initiatePaymentTransaction($currency, $price * $quantity, $topup->getName(), $redirectBackUrl);
    }

    public function initiatePaymentTransaction(string $currency, int $amount, string $productName, string $redirectBackUrl): ?PaymentTransactionInterface
    {
        $response = $this->client->request('POST', 'payment-requests', [
            'body' => http_build_query([
                'currency' => $currency,
                'amount' => $amount,
                'purpose' => $productName,
                'redirect_url' => $redirectBackUrl
            ])
        ]);
        if (!str_starts_with($response->getStatusCode(), '2')) {
            throw new \Exception("Failed to create Hitpay checkout: " . $response->getContent(false));
        }
        return new HitpayCheckoutTransaction($response->toArray());
    }

    public function retrievePaymentTransaction(string $reference): ?PaymentTransactionInterface
    {
        $qb = $this->em->createQueryBuilder()->select('p')->from(Payment::class, 'p')->where('p.transactionId = :reference')->setParameter('reference', $reference);
        if (($payment = $qb->getQuery()->getOneOrNullResult())) {
            $transactionData = $payment->getTransactionData();
            return new HitpayCheckoutTransaction($transactionData);
        }
        return null;
    }

    public function generateRedirectForTransaction(PaymentTransactionInterface $transaction): ?Response
    {
        return ($url = $transaction->getUrl()) ? new RedirectResponse($url) : null;
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        if ($this->apiSalt) {
            $signature = $request->headers->get('Hitpay-Signature');
            $expectedSignature = hash_hmac('sha256', $payload, $this->apiSalt);
            if (!hash_equals($expectedSignature, $signature)) {
                http_response_code(400);
                exit("Invalid signature");
            }
        }

        switch ($request->headers->get('Hitpay-Event-Object') . '.' . $request->headers->get('Hitpay-Event-Type')) {
            case 'payment_request.completed':
                $data = json_decode($payload, true);
                if ($data && isset($data['id'])) {
                    $transactionId = $data['id'];
                    $payment = $this->getPaymentByTransactionId($transactionId);
                    if ($payment) {
                        $this->updatePaymentTransaction($transactionId, $data, $data['currency'], $data['amount'], 1, 'Payment successful');
                    }
                }
                break;
        }
    }

    public function isTopupPurchasable(TopupConfig $topup): bool
    {
        return true;
    }

    public function createPayoutAccount(): array
    {
        return [];
    }

    public function performPayoutToPayoutAccount(PayoutAccount $account, string $currency, int $amount): ?PayoutPayment
    {
        return null;
    }

    public function checkPayoutAccountReadiness(PayoutAccount $account): bool
    {
        return false;
    }

    public function generateRedirectForPayoutAccountSetup(PayoutAccount $account, string $returnUrl, string $retryUrl): ?RedirectResponse
    {
        return null;
    }
}
