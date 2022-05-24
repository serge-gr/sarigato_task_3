<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ValidateEmailsCommand extends Command
{
    protected static $defaultName = 'app:validate-emails';
 
    public function __construct($projectDir) {
        $this->projectDir = $projectDir;

        parent::__construct(); 
    }

    protected function configure()
    {
        $this->setDescription('Validates emails and separates them by validation results');
        $this->addArgument('file_name', InputArgument::REQUIRED, 'Name of source file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileName = $input->getArgument('file_name');
        $emails = $this->convertCsvToArray($fileName);

        $this->createCsvFiles($emails);

        $io = new SymfonyStyle($input, $output);
        $io->success('Emails validated, result files created');

        return Command::SUCCESS;
    }

    protected function convertCsvToArray(string $fileName): array
    {
        $inputFile = $this->projectDir . '/public/' . $fileName . '.csv';

        $decoder = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
        $rows = $decoder->decode(file_get_contents($inputFile), 'csv', [CsvEncoder::KEY_SEPARATOR_KEY => ',', CsvEncoder::NO_HEADERS_KEY => true]);
        $correctEmails = [];
        $invalidEmails = [];

        foreach ($rows as $email) {
            foreach ($email as $key => $value) {
                if (!empty($value)) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $invalidEmails[] = $value;
                    } else {
                        $correctEmails[] = $value;
                    }
                }
            }
        }

        return ['invalid' => $invalidEmails, 'correct' => $correctEmails];
    }

    protected function createCsvFiles($emails): void
    {
        // invalid emails file
        $inv = fopen($this->projectDir . '/public/invalid_emails.csv', 'w');

        foreach ($emails['invalid'] as $invalidEmail) {
            fputcsv($inv, [$invalidEmail]);
        }

        fclose($inv);

        // correct emails file
        $cor = fopen($this->projectDir . '/public/correct_emails.csv', 'w');

        foreach ($emails['correct'] as $correctEmail) {
            fputcsv($cor, [$correctEmail]);
        }

        fclose($cor);

        // summary file
        $sum = fopen($this->projectDir . '/public/summary.csv', 'w');

        foreach ($emails as $type => $addresses) {
            $types[] = $type;
        }

        // creating invalid-correct pairs array for proper handling by fputcsv
        $overlay = array_fill(0, count($emails['invalid']), NULL);
        $result = array_combine($emails['invalid'], $emails['correct'] + $overlay);

        fputcsv($sum, $types);

        foreach ($result as $invalid => $correct) {
            fputcsv($sum, [$invalid, $correct]);
        }

        fclose($sum);
    }
}
