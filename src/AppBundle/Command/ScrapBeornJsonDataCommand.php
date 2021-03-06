<?php

namespace AppBundle\Command;

use AppBundle\Entity\Card;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ScrapBeornJsonDataCommand extends ContainerAwareCommand {

    protected function configure() {
        $this->setName('app:beorn:json')
             ->setDescription('Download new card data from Hall of Beorn JSON Export')
             ->addOption(
                 'skip',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Number of cards to skip'
             )
             ->addOption(
                 'force-data',
                 null,
                 InputOption::VALUE_NONE,
                 'Redefine card data'
             )
             ->addOption(
                 'force-image',
                 null,
                 InputOption::VALUE_NONE,
                 'Redownload card image'
             )
             ->addOption(
                 'show-texts',
                 null,
                 InputOption::VALUE_NONE,
                 'Show card text and flavor'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $questionHelper = $this->getHelper('question');

        $assets_helper = $this->getContainer()->get('templating.helper.assets');
        $rootDir = $this->getContainer()->get('kernel')->getRootDir();

        $allSpheres = $em->getRepository('AppBundle:Sphere')->findAll();
        $allTypes = $em->getRepository('AppBundle:Type')->findAll();

        $skip = $input->getOption('skip') ?: 0;
        $forceData = $input->getOption('force-data');
        $forceImage = $input->getOption('force-image');
        $showTexts = $input->getOption('show-texts');

        if (file_exists('beorn.json')) {
            dump("Loading Local Beorn JSON");
            $json = file_get_contents('beorn.json');
        } else {
            dump("Loading Remote Beorn JSON");
            $json = file_get_contents("http://hallofbeorn.com/Export/Cards");
            file_put_contents('beorn.json', $json);
        }
        $beorn = json_decode($json);

        $i = 0;
        foreach ($beorn as $data) {
            if ($skip > $i++) {
                continue;
            }

            dump($data->Title . " $i");

            /* @var $pack \AppBundle\Entity\Pack */
            $pack = $em->getRepository('AppBundle:Pack')->findOneBy(['name' => $data->CardSet]);

            if (!$pack) {
                dump('Could not find pack ' . $data->CardSet);
                continue;
            }

            /* @var $card \AppBundle\Entity\Card */
            $card = $em->getRepository('AppBundle:Card')->findOneBy(['name' => $data->Title, 'pack' => $pack]);

            if (!$card) {
                if ($data->CardType == 'Hero' || $data->CardType == 'Ally' || $data->CardType == 'Attachment' || $data->CardType == 'Event') {
                    dump('Could not find card ' . $data->Title);
                    $question = new ConfirmationQuestion("Continue?");
                    $questionHelper->ask($input, $output, $question);
                }
                continue;
            }

            if ($card->getHasErrata() != $data->HasErrata) {
                dump('Errata Mismatch ' . $data->Title);
                dump($card->getHasErrata());
                dump($data->HasErrata);

                $question = new ConfirmationQuestion("Continue?");
                $questionHelper->ask($input, $output, $question);
            }

            if (property_exists($data->Front->Stats, 'ThreatCost') && $card->getThreat() != $data->Front->Stats->ThreatCost) {
                dump('Threat Mismatch ' . $data->Title);
                dump($card->getThreat());
                dump($data->Front->Stats->ThreatCost);

                $question = new ConfirmationQuestion("Continue?");
                $questionHelper->ask($input, $output, $question);
            }

            if (property_exists($data->Front->Stats, 'Willpower') && $card->getWillpower() != $data->Front->Stats->Willpower) {
                dump('Willpower Mismatch ' . $data->Title);
                dump($card->getWillpower());
                dump($data->Front->Stats->Willpower);

                $question = new ConfirmationQuestion("Continue?");
                $questionHelper->ask($input, $output, $question);
            }

            if (property_exists($data->Front->Stats, 'Attack') && $card->getAttack() != $data->Front->Stats->Attack) {
                dump('Attack Mismatch ' . $data->Title);
                dump($card->getAttack());
                dump($data->Front->Stats->Attack);

                $question = new ConfirmationQuestion("Continue?");
                $questionHelper->ask($input, $output, $question);
            }

            if (property_exists($data->Front->Stats, 'Defense') && $card->getDefense() != $data->Front->Stats->Defense) {
                dump('Defense Mismatch ' . $data->Title);
                dump($card->getDefense());
                dump($data->Front->Stats->Defense);

                $question = new ConfirmationQuestion("Continue?");
                $questionHelper->ask($input, $output, $question);
            }

            if (property_exists($data->Front->Stats, 'HitPoints') && $card->getHealth() != $data->Front->Stats->HitPoints) {
                dump('HitPoints Mismatch ' . $data->Title);
                dump($card->getHealth());
                dump($data->Front->Stats->HitPoints);

                $question = new ConfirmationQuestion("Continue?");
                $questionHelper->ask($input, $output, $question);
            }

            //$card->setIllustrator($data->Artist);
            //$em->flush();
        }

        //$em->flush();
        $output->writeln("Done.");
    }
}
