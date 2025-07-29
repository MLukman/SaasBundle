<?php

namespace MLukman\SaasBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Exception;
use MLukman\SaasBundle\Base\PaymentProvider;
use MLukman\SaasBundle\Config\PrepaidConfig;
use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\Entity\Credit;
use MLukman\SaasBundle\Entity\CreditPurchase;
use MLukman\SaasBundle\Entity\CreditTransfer;
use MLukman\SaasBundle\Entity\CreditUsage;
use MLukman\SaasBundle\Entity\CreditUsagePart;
use MLukman\SaasBundle\Entity\CreditWithdrawal;
use MLukman\SaasBundle\Entity\PayoutAccount;
use MLukman\SaasBundle\Event\CreditBalanceEvent;
use MLukman\SaasBundle\InsufficientCreditBalanceException;
use MLukman\SaasBundle\InvalidSaasConfigurationException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class SaasPrepaidManager
{
    protected PrepaidConfig $configuration;
    protected ?PaymentProvider $paymentProvider = null;
    protected array $walletBalances = [];
    public static string $creditClass = Credit::class;
    public static string $creditUsageClass = CreditUsage::class;
    public static string $creditUsagePartClass = CreditUsagePart::class;
    public static string $creditPurchaseClass = CreditPurchase::class;

    public function __construct(
        protected EntityManagerInterface $em,
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $dispatcher,
        protected TranslatorInterface $translator
    ) {
        
    }

    public function getConfiguration(): PrepaidConfig
    {
        return $this->configuration;
    }

    public function setConfiguration(PrepaidConfig $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getPaymentProvider(): ?PaymentProvider
    {
        return $this->paymentProvider;
    }

    public function setPaymentProvider(?PaymentProvider $paymentProvider): void
    {
        $this->paymentProvider = $paymentProvider;
    }

    public function getTopupConfig(string $topupId): TopupConfig
    {
        $topups = $this->configuration->getTopups();
        if (!isset($topups[$topupId])) {
            throw new InvalidSaasConfigurationException($this->translator->trans("Topup %topupId% is invalid", ['%topupId%' => $topupId], 'saas'));
        }
        return $topups[$topupId];
    }

    public function getPurchasableTopups(string $wallet): array
    {
        $topups = [];
        foreach ($this->configuration->getTopups() as $topupId => $topup) {
            if (!$this->paymentProvider->isTopupPurchasable($topup)) {
                continue;
            }
            if ($topup->getLimit() > 0 && $this->getTopupCount($wallet, $topupId) >= $topup->getLimit()) {
                continue;
            }
            $topups[$topupId] = $topup;
        }
        return $topups;
    }

    public function getSessionLastCreditPurchase(): ?CreditPurchase
    {
        $transaction = $this->requestStack->getSession()->get('saas.credit.payment.transaction', '');
        try {
            return $this->em->createQuery('SELECT p FROM ' . static::$creditPurchaseClass . ' p WHERE p.transaction = :transaction')
                    ->setParameter('transaction', $transaction)
                    ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function getCreditBalance(string $wallet): int
    {
        return intval($this->em->createQuery('SELECT SUM(c.balance) FROM ' . static::$creditClass . ' c WHERE c.wallet = :wallet AND c.balance > 0 AND (c.expiry IS NULL OR c.expiry > CURRENT_TIMESTAMP())')
                ->setParameter('wallet', $wallet)
                ->getSingleScalarResult());
    }

    public function getTopupCount(string $wallet, ?string $topupId = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('count(c.id)')->from(static::$creditClass, 'c')->where('c.wallet = :wallet')->setParameter('wallet', $wallet);
        if ($topupId) {
            $qb->andWhere('c.topup = :topup')->setParameter('topup', $topupId);
        }
        return intval($qb->getQuery()->getSingleScalarResult());
    }

    public function getLatestCreditPurchaseByWalletAndTopup(string $wallet, string $topup, int $quantity, int $status = 0): ?CreditPurchase
    {
        try {
            return $this->em->createQuery('SELECT p FROM ' . static::$creditPurchaseClass . ' p WHERE p.wallet = :wallet AND p.topup = :topup AND p.quantity = :quantity AND p.status = :status ORDER BY p.created DESC')
                    ->setParameter('wallet', $wallet)
                    ->setParameter('topup', $topup)
                    ->setParameter('quantity', $quantity)
                    ->setParameter('status', $status)
                    ->setMaxResults(1)
                    ->getSingleResult();
        } catch (NoResultException $ex) {
            return null;
        }
    }

    public function getCreditRecords(string $wallet, bool $positiveBalanceOnly = false, bool $validOnly = false): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(static::$creditClass, 'c')
                ->where('c.wallet = :wallet')->setParameter('wallet', $wallet)->orderBy('c.created', 'DESC');
        if ($positiveBalanceOnly) {
            $qb->andWhere('c.balance > 0');
        }
        if ($validOnly) {
            $qb->andWhere('c.expiry IS NULL OR c.expiry > CURRENT_TIMESTAMP()');
        }
        return $qb->getQuery()->getResult();
    }

    public function getCreditUsageRecords(string $wallet, int $limit = 0): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(static::$creditUsageClass, 'c')
                ->where('c.wallet = :wallet')->setParameter('wallet', $wallet)->orderBy('c.created', 'DESC');
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        return $qb->getQuery()->getResult();
    }

    public function getLastCreditUsageRecord(string $wallet): ?CreditUsage
    {
        return $this->getCreditUsageRecords($wallet, 1)[0] ?? null;
    }

    /**
     * Initiate a credit purchase process using payment provider
     * @param string $wallet The wallet to purchase credit for
     * @param TopupConfig|string $topup Either the TopupConfig data or the name as defined in the configuration
     * @param int $quantity The multiplier for the topup credit
     * @param string $redirectBackUrl The URL to redirect back after the purchase transaction
     * @return RedirectResponse|null The redirect response to use to go to the payment provider
     */
    public function creditPurchaseInitiate(string $wallet, TopupConfig|string $topup, int $quantity, string $redirectBackUrl, bool $no_commit = false): ?RedirectResponse
    {
        if ($quantity < 1) {
            throw new Exception($this->translator->trans('Topup quantity must be more than 0', domain: 'saas'));
        }
        if (is_string($topup)) {
            $topup = $this->getTopupConfig($topup);
        }

        $trans = null;
        if (($existing = $this->getLatestCreditPurchaseByWalletAndTopup($wallet, $topup->_id, $quantity, 0))) {
            if (!($trans = $this->paymentProvider->retrieveCreditPurchaseTransaction($existing->getTransactionId()))) {
                $existing->setStatus(-1);
            }
        }
        if (!$trans) {
            $trans = $this->paymentProvider->initiateCreditPurchaseTransaction($topup, $quantity, $redirectBackUrl);
            $purchase = new self::$creditPurchaseClass($this->paymentProvider->getId(), $trans, $wallet, $topup->_id, $quantity);
            $this->em->persist($purchase);
            if (!$no_commit) {
                $this->em->flush();
            }
        }
        $this->requestStack->getSession()->set('saas.credit.payment.transaction', $trans->getReference());
        return $this->paymentProvider->generateRedirectForTransaction($trans);
    }

    /**
     * Complete the credit purchase
     * @param CreditPurchase $purchase
     * @return void
     */
    public function creditPurchaseComplete(CreditPurchase $purchase): void
    {
        $purchase->setCredit($this->topupCredit($purchase->getWallet(), $purchase->getTopup(), $purchase->getQuantity(), $purchase));
    }

    /**
     * Register a credit topup to add credit balance of a wallet.
     *
     * @param string $wallet The wallet to add credit balance
     * @param TopupConfig|string $topup Either the TopupConfig data or the name as defined in the configuration
     * @param int $quantity The multiplier for the topup credit
     * @param CreditPurchase|null $purchase Optionally relate this topup with a purchase record
     * @return Credit
     */
    public function topupCredit(string $wallet, TopupConfig|string $topup, int $quantity = 1, ?CreditPurchase $purchase = null): Credit
    {
        if ($quantity < 1) {
            throw new Exception($this->translator->trans('Topup quantity must be more than 0', domain: 'saas'));
        }
        if (is_string($topup)) {
            $topup = $this->getTopupConfig($topup);
        }
        return $this->createCreditRecord($wallet, $topup->getCredit() * $quantity, 'TOPUP', $topup->_id, $topup->getValidity(), $purchase);
    }

    /**
     * Transfer certain points of credit from one wallet to another
     * @param string $sourceWallet The wallet to transfer from
     * @param string $destinationWallet The wallet to transfer to
     * @param int $points The number of points to transfer
     * @param string $type Arbitrary type information for future analytic purpose
     * @param string|null $reference Arbitraty reference information
     * @return CreditTransfer
     */
    public function transferCredit(string $sourceWallet, string $destinationWallet, int $points, string $type, ?string $reference = null): CreditTransfer
    {
        $transfer = new CreditTransfer($sourceWallet, $points, $type, $reference);
        $this->prepareCreditUsageRecord($transfer);
        $transfer->setDestination($this->createCreditRecord($destinationWallet, $points, $type, $reference, $transfer));
        $this->commitChanges();
        return $transfer;
    }

    public function withdrawCredit(string $sourceWallet, int $points, PayoutAccount $account, string $currency, int $amount, string $type, string $reference): CreditWithdrawal
    {
        $withdrawal = new CreditWithdrawal($sourceWallet, $points, $type, $reference);
        $this->prepareCreditUsageRecord($withdrawal);
        $payoutPayment = $this->paymentProvider->performPayoutToPayoutAccount($account, $currency, $amount);
        $withdrawal->setPayment($payoutPayment);
        $this->commitChanges();
        return $withdrawal;
    }

    /**
     * Register a credit spending to subtract credit balance of a wallet.
     *
     * @param string $wallet The wallet to subtract from
     * @param string $type The type of credit usage, either predefined in configuration or arbitrary
     * @param string $reference Arbitrary reference information to store in the record
     * @param int|null $points The points to subtract. Optional if $type is predefined in configuration, otherwise this parameter is compulsory
     * @return CreditUsage
     * @throws InvalidSaasConfigurationException
     */
    public function spendCredit(string $wallet, string $type, string $reference, ?int $points = null): CreditUsage
    {
        if (is_null($points) && is_null($points = $this->configuration->getUsages()[$type] ?? null)) {
            throw new InvalidSaasConfigurationException($this->translator->trans("There is no predefined prepaid usage with type '%type%'", ['%type%' => $type], 'saas'));
        }
        return $this->createCreditUsageRecord($wallet, $points, $type, $reference);
    }

    protected function createCreditRecord(string $wallet, int $points, string $sourceType, ?string $sourceReference, DateTime|string|null $expiry, mixed $source = null): Credit
    {
        $this->cacheWalletBalance($wallet, $source);
        $credit = new static::$creditClass($wallet, $points, $sourceType, $sourceReference);
        if (is_string($expiry)) {
            $expiry = (new \DateTime())->modify($expiry);
        }
        if (!empty($expiry)) {
            $credit->setExpiry($expiry);
        }
        $this->em->persist($credit);
        return $credit;
    }

    protected function createCreditUsageRecord(string $wallet, int $points, string $type, string $reference): CreditUsage
    {
        $usage = new static::$creditUsageClass($wallet, $points, $type, $reference);
        $this->prepareCreditUsageRecord($usage);
        return $usage;
    }

    protected function prepareCreditUsageRecord(CreditUsage $usage): void
    {
        // first query those with expiry dates, followed by those without ones
        $queries = [
            'SELECT c FROM ' . static::$creditClass . ' c WHERE c.wallet = :wallet AND c.balance > 0 AND c.expiry > CURRENT_TIMESTAMP() ORDER BY c.expiry ASC',
            'SELECT c FROM ' . static::$creditClass . ' c WHERE c.wallet = :wallet AND c.balance > 0 AND c.expiry IS NULL ORDER BY c.created ASC'
        ];
        $wallet = $usage->getWallet();
        $points = $usage->getPoints();
        for ($i = 0; $i < 2 && $points > 0; $i++) {
            $credits = $this->em->createQuery($queries[$i])->setParameter('wallet', $wallet)->getResult();
            $count_credits = count($credits);
            for ($j = 0; $j < $count_credits && $points > 0; $j++) {
                $balance = $credits[$j]->getBalance();
                if ($balance >= $points) {
                    $part = new static::$creditUsagePartClass($credits[$j], $usage, $points);
                    $balance -= $points;
                    $points = 0;
                } else {
                    $part = new static::$creditUsagePartClass($credits[$j], $usage, $balance);
                    $points -= $balance;
                    $balance = 0;
                }
                $credits[$j]->getUsageParts()->add($part);
                $usage->getCreditParts()->add($part);
                $credits[$j]->recalculateBalance();
            }
        }
        if ($points > 0) {
            $this->throwInsufficientCreditBalanceException();
        }
        $this->cacheWalletBalance($wallet, $usage);
        $this->em->persist($usage);
    }

    public function cacheWalletBalance(string $wallet, mixed $source = null): int
    {
        $balance = $this->getCreditBalance($wallet);
        $this->walletBalances[$wallet] = [$balance, $source];
        return $balance;
    }

    /**
     * Commit all changes to the database & dispatch all queued events to listeners
     */
    public function commitChanges()
    {
        $this->em->flush();
        foreach ($this->walletBalances as $wallet => $info) {
            $current = $this->getCreditBalance($wallet);
            if ($current != $info[0]) {
                $this->dispatcher->dispatch(new CreditBalanceEvent($wallet, $info[0], $current, $info[1] ?? null));
            }
        }
        $this->walletBalances = [];
    }

    public function throwInsufficientCreditBalanceException(): void
    {
        throw new InsufficientCreditBalanceException($this->translator->trans('There is not enough credit balance', domain: 'saas'));
    }
}
