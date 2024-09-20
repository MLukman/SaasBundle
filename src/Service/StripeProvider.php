<?php

namespace MLukman\SaasBundle\Service;

use Exception;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use MLukman\SaasBundle\Payment\ProviderInterface;
use MLukman\SaasBundle\Payment\Stripe\StripeTransaction;
use MLukman\SaasBundle\Payment\TransactionInterface;
use Stripe\Event;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StripeProvider implements ProviderInterface
{
    protected SaasUtil $saas;
    protected StripeClient $stripeClient;

    public function getId(): string
    {
        return "stripe";
    }

    public function initialize(SaasUtil $saas, array $paymentConfigParams): bool
    {
        if (($secret = $paymentConfigParams['secret'] ?? null)) {
            $this->saas = $saas;
            $this->stripeClient = new StripeClient($secret);
            return true;
        } else {
            throw new InvalidSaasConfigurationException("Stripe payment provider requires the Stripe API secret key to be configured in the 'saas.payment.secret' configuration key");
        }
        return false;
    }

    public function retrieveCreditPurchaseTransaction(string $reference): ?TransactionInterface
    {
        try {
            $checkoutSession = $this->stripeClient->checkout->sessions->retrieve($reference);
            if ("open" == ($checkoutSession->status ?? null)) {
                $transaction = new StripeTransaction();
                $transaction->reference = $checkoutSession->id;
                $transaction->redirect = $checkoutSession->url;
                return $transaction;
            }
        } catch (Exception $ex) {

        }
        return null;
    }

    public function initiateCreditPurchaseTransaction(TopupConfig $topup, string $redirectBackUrl): ?TransactionInterface
    {
        $checkoutSession = $this->stripeClient->checkout->sessions->create([
            'line_items' => [
                [
                    'price' => $topup->getPaymentParams()['priceId'],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $redirectBackUrl,
        ]);
        $transaction = new StripeTransaction();
        $transaction->reference = $checkoutSession->id;
        $transaction->redirect = $checkoutSession->url;
        return $transaction;
    }

    public function generateRedirectForTransaction(TransactionInterface $transaction): Response
    {
        return new RedirectResponse($transaction->redirect);
    }

    public function handleWebhook(Request $request)
    {
        $event = Event::constructFrom(\json_decode($request->getContent(), true));
        switch ($event->type) {
            case 'checkout.session.completed':
                $transaction = $event->data->object->id;
                $this->saas->updatePaymentTransaction($transaction, 1);
                break;
        }
    }

    public function isTopupPurchasable(TopupConfig $topup): bool
    {
        return !empty($topup->getPaymentParams()['priceId'] ?? null);
    }
}
