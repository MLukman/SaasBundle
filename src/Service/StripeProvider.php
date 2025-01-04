<?php

namespace MLukman\SaasBundle\Service;

use Exception;
use MLukman\SaasBundle\Base\PaymentProvider;
use MLukman\SaasBundle\Base\PaymentTransactionInterface;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\DTO\StripeCheckoutTransaction;
use MLukman\SaasBundle\DTO\StripeTransferTransaction;
use MLukman\SaasBundle\Entity\PayoutAccount;
use MLukman\SaasBundle\Entity\PayoutPayment;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Transfer;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StripeProvider extends PaymentProvider
{
    protected StripeClient $stripeClient;
    protected array $params = [];

    public function getId(): string
    {
        return "stripe";
    }

    public function initialize(array $paymentConfigParams): bool
    {
        if (($secret = $paymentConfigParams['secret'] ?? null)) {
            $this->stripeClient = new StripeClient($secret);
            $this->params = $paymentConfigParams;
            return true;
        } else {
            throw new InvalidSaasConfigurationException("Stripe payment provider requires the Stripe API secret key to be configured in the 'saas.payment.secret' configuration key");
        }
        return false;
    }

    public function retrieveCreditPurchaseTransaction(string $reference): ?PaymentTransactionInterface
    {
        try {
            $checkoutSession = $this->stripeClient->checkout->sessions->retrieve($reference);
            if ("open" == ($checkoutSession->status ?? null)) {
                return new StripeCheckoutTransaction($checkoutSession);
            }
        } catch (Exception $ex) {
            
        }
        return null;
    }

    public function initiateCreditPurchaseTransaction(TopupConfig $topup, int $quantity, string $redirectBackUrl): ?PaymentTransactionInterface
    {
        $checkoutSession = $this->stripeClient->checkout->sessions->create([
            'line_items' => [
                [
                    'price' => $topup->getPaymentParams()['priceId'],
                    'quantity' => $quantity,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $redirectBackUrl,
        ]);
        return new StripeCheckoutTransaction($checkoutSession);
    }

    public function generateRedirectForTransaction(PaymentTransactionInterface $transaction): ?Response
    {
        return ($url = $transaction->getUrl()) ? new RedirectResponse($url) : null;
    }

    public function handleWebhook(Request $request)
    {
        $event = Event::constructFrom(\json_decode($request->getContent(), true));
        if (!$event) {
            http_response_code(400);
            echo json_encode(['Error parsing payload: ' => $e->getMessage()]);
            exit();
        }
        if (!empty($this->params['signing_secret'] ?? null)) {
            try {
                $event = Webhook::constructEvent($request->getContent(), $_SERVER['HTTP_STRIPE_SIGNATURE'], $this->params['signing_secret']);
            } catch (SignatureVerificationException $e) {
                // Invalid signature
                if ($this->getPaymentByTransactionId($event->data->object->id)) {
                    $this->updatePaymentTransaction($event->data->object->id, $event->data->toArray()['object'], $event->data->object->currency, $event->data->object->amount_total ?? 0, -1, 'Error verifying webhook signature');
                }
                http_response_code(400);
                echo json_encode(['Error verifying webhook signature: ' => $e->getMessage()]);
                exit();
            }
        }
        switch ($event->type) {
            case 'checkout.session.completed':
                $transaction = $event->data->object->id;
                $this->updatePaymentTransaction($transaction, $event->data->toArray()['object'], $event->data->object->currency, $event->data->object->amount_total ?? 0, 1);
                break;
        }
        http_response_code(200);
    }

    public function isTopupPurchasable(TopupConfig $topup): bool
    {
        return !empty($topup->getPaymentParams()['priceId'] ?? null);
    }

    protected function createPayoutAccount(): array
    {
        $account = $this->stripeClient->accounts->create([]);
        return $account->toArray();
    }

    protected function checkPayoutAccountReadiness(array &$accountData): bool
    {
        $accountData = $this->stripeClient->accounts->retrieve($accountData['id'], [])->toArray();
        return !empty($accountData['payouts_enabled'] ?? false);
    }

    protected function generateRedirectForPayoutAccountSetup(array $accountData, string $returnUrl, string $retryUrl): ?RedirectResponse
    {
        $link = $this->stripeClient->accountLinks->create([
            'account' => $accountData['id'],
            'type' => 'account_onboarding',
            'refresh_url' => $retryUrl,
            'return_url' => $returnUrl,
        ]);
        return new RedirectResponse($link->url);
    }

    public function performPayoutToPayoutAccount(PayoutAccount $account, string $currency, int $amount): ?PayoutPayment
    {
        if (!$account->isReady()) {
            throw new \Exception(sprintf("Payout account %s is not ready to receive payout. Please complete the account setup.", $account->getId()));
        }
        $t = new Transfer;
        $t->currency = $currency;
        $t->amount = $amount;
        $payment = new PayoutPayment('stripe', new StripeTransferTransaction($t), $account);
        $this->em->persist($payment);
        $this->commitChanges();

        $transferData = $this->stripeClient->transfers->create([
            'destination' => $account->getData()['id'],
            'currency' => $currency,
            'amount' => $amount,
        ]);

        $payment->updateTransaction(new StripeTransferTransaction($transferData));
        $payment->setStatus(1);
        $this->commitChanges();

        return $payment;
    }
}
