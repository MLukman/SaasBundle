<?php

namespace MLukman\SaasBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MLukman\SaasBundle\Entity\Credit;
use MLukman\SaasBundle\Service\SaasUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
        name: 'saas:wallet:topup:undo',
        description: 'Undo a wallet topup',
    )]
class WalletUndoTopupCommand extends Command
{
    protected EntityManagerInterface $em;
    protected SaasUtil $saas;

    #[Required]
    public function required(EntityManagerInterface $em, SaasUtil $saas)
    {
        $this->em = $em;
        $this->saas = $saas;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('walletId', InputArgument::REQUIRED, 'The wallet id to top up')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prepaidManager = $this->saas->getPrepaidManager();

        $io = new SymfonyStyle($input, $output);
        $walletId = $input->getArgument('walletId');

        $balance = $prepaidManager->cacheWalletBalance($walletId);

        $io->title(sprintf("Wallet <options=bold>%s</> has a current balance of <options=bold>%d</>.", $walletId, $balance));
        $dql = sprintf("SELECT c FROM %s c INDEX BY c.id LEFT JOIN c.usageParts up LEFT JOIN c.purchase p WHERE c.wallet = :walletId AND p IS NULL AND up IS NULL ORDER BY c.created DESC", Credit::class);
        $undoables = [];
        $choices = [];
        foreach ($this->em->createQuery($dql)->setParameter("walletId", $walletId)->getResult() as $undoable) {
            /** @var Credit $undoable */
            $id = str_pad($undoable->getId(), 6, "0", STR_PAD_LEFT);
            $choices[$id] = sprintf("+%d credits on %s", $undoable->getPoints(), $undoable->getCreated()->format("Y-m-d H:i"));
            $undoables[$id] = $undoable;
        }
        if (empty($choices)) {
            $io->warning('Sorry, the wallet has no topup that can be undone.');
            return Command::SUCCESS;
        }

        $undoId = $io->choice("Please which topup to undo", $choices);

        if ($io->confirm(sprintf('Proceed to undo topup %s created on %s?', $undoId, $undoables[$undoId]->getCreated()->format("Y-m-d H:i")))) {
            $this->em->remove($undoables[$undoId]);
            $prepaidManager->commitChanges();
            $io->success(sprintf("Done. The new balance is %d.", $prepaidManager->getCreditBalance($walletId)));
            return Command::SUCCESS;
        }

        $io->error('You did not proceed');
        return Command::FAILURE;
    }
}
