<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use VendingMachine\Application\VendingMachineService;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\VendingMachine;
use VendingMachine\Domain\VendingResultType;

final class VendingMachineCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('vending-machine')
            ->setDescription('Interactive vending machine simulator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $machine = new VendingMachine(
            CoinBank::withStock([
                Coin::Nickel->value => 10,
                Coin::Dime->value => 10,
                Coin::Quarter->value => 10,
                Coin::Dollar->value => 10,
            ]),
            Inventory::default(5),
        );

        $service = new VendingMachineService($machine);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $output->writeln('<info>Welcome to the Vending Machine!</info>');
        $output->writeln('');
        $this->displayMenu($output);

        while (true) {
            $this->displayState($output, $service);

            $question = new Question('<comment>Enter command: </comment>');
            $userInput = $helper->ask($input, $output, $question);

            if ($userInput === null || strtoupper(trim($userInput)) === 'EXIT') {
                $output->writeln('<info>Goodbye!</info>');

                break;
            }

            $result = $service->handleInput($userInput);

            match ($result->type) {
                VendingResultType::Dispensed => $this->displayDispensed($output, $result),
                VendingResultType::CoinsReturned => $this->displayCoinsReturned($output, $result),
                VendingResultType::Error => $output->writeln(sprintf('<comment>%s</comment>', $result->message)),
            };

            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function displayMenu(OutputInterface $output): void
    {
        $output->writeln('<info>Products:</info>');
        foreach (Product::cases() as $product) {
            $output->writeln(sprintf(
                '  GET-%s — $%s',
                $product->label(),
                number_format($product->priceInCents() / 100, 2),
            ));
        }
        $output->writeln('');
        $output->writeln('<info>Commands:</info>');
        $output->writeln('  0.05, 0.10, 0.25, 1.00 — Insert coin');
        $output->writeln('  GET-WATER, GET-JUICE, GET-SODA — Buy product');
        $output->writeln('  RETURN-COIN — Return inserted coins');
        $output->writeln('  SERVICE — Restock machine');
        $output->writeln('  EXIT — Quit');
        $output->writeln('');
    }

    private function displayState(OutputInterface $output, VendingMachineService $service): void
    {
        $state = $service->getMachine()->getState();
        $balance = $service->getMachine()->getInsertedAmount();

        $output->writeln(sprintf(
            '<info>Balance: $%s</info> | Stock: Water(%d) Juice(%d) Soda(%d)',
            number_format($balance / 100, 2),
            $state['inventory']->getCount(Product::Water),
            $state['inventory']->getCount(Product::Juice),
            $state['inventory']->getCount(Product::Soda),
        ));
    }

    private function displayDispensed(OutputInterface $output, \VendingMachine\Domain\VendingResult $result): void
    {
        $output->writeln(sprintf('<info>%s</info>', $result->message));

        if ($result->change !== []) {
            $labels = array_map(fn (Coin $c) => '$' . $c->label(), $result->change);
            $output->writeln(sprintf('Change: %s', implode(', ', $labels)));
        }
    }

    private function displayCoinsReturned(OutputInterface $output, \VendingMachine\Domain\VendingResult $result): void
    {
        if ($result->change === []) {
            $output->writeln('<comment>No coins to return.</comment>');

            return;
        }

        $labels = array_map(fn (Coin $c) => '$' . $c->label(), $result->change);
        $output->writeln(sprintf('Returned: %s', implode(', ', $labels)));
    }
}
