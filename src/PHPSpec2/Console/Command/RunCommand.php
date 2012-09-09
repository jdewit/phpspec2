<?php

namespace PHPSpec2\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;

use PHPSpec2\Console\IO;
use PHPSpec2\Locator;
use PHPSpec2\Tester;
use PHPSpec2\Matcher;
use PHPSpec2\StatisticsCollector;
use PHPSpec2\Formatter;
use PHPSpec2\Event\SuiteEvent;
use PHPSpec2\Event\ExampleEvent;
use PHPSpec2\Formatter\Representer\BasicRepresenter;

class RunCommand extends Command
{
    /**
     * Initializes command.
     */
    public function __construct()
    {
        parent::__construct('run');

        $this->setDefinition(array(
            new InputArgument('spec', InputArgument::OPTIONAL, 'Specs to run', 'spec'),
            new InputOption('example', 'e', InputOption::VALUE_REQUIRED, 'Run examples matching pattern'),
            new InputOption('fail-fast', null, InputOption::VALUE_NONE, 'Abort the run on first failure')
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // setup IO
        $io = new IO($input, $output);

        $representer = new BasicRepresenter();

        $matchers = new Matcher\MatchersCollection();
        $matchers->add(new Matcher\IdentityMatcher($representer));
        $matchers->add(new Matcher\ComparisonMatcher($representer));
        $matchers->add(new Matcher\TrueMatcher($representer));
        $matchers->add(new Matcher\ThrowMatcher($representer));
        $matchers->add(new Matcher\TypeMatcher($representer));
        $matchers->add(new Matcher\ObjectStateMatcher($representer));

        // setup specs locator and tester
        $locator = new Locator();
        $tester  = new Tester(new EventDispatcher(), $matchers, $input->getOptions());

        // setup formatter
        $formatter = new Formatter\PrettyFormatter($representer);
        $formatter->setIO($io);
        $tester->getEventDispatcher()->addSubscriber($formatter);

        // setup statistics collector
        $collector = new StatisticsCollector;
        $tester->getEventDispatcher()->addSubscriber($collector);
        $tester->getEventDispatcher()->dispatch('beforeSuite', new SuiteEvent($collector));

        foreach ($locator->getSpecifications($input->getArgument('spec')) as $spec) {
            if ($tester->wasAborted()) {
                break;
            }
            $tester->testSpecification($spec);
        }

        $tester->getEventDispatcher()->dispatch('afterSuite', new SuiteEvent($collector));

        return intval(ExampleEvent::PASSED !== $collector->getGlobalResult());
    }
}