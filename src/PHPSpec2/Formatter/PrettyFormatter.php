<?php

namespace PHPSpec2\Formatter;

use Symfony\Component\Console\Output\OutputInterface;

use PHPSpec2\Event\SpecificationEvent;
use PHPSpec2\Event\ExampleEvent;
use PHPSpec2\Console\IO;

use ReflectionClass;
use ReflectionMethod;
use Exception;

class PrettyFormatter implements FormatterInterface
{
    private $io;

    public static function getSubscribedEvents()
    {
        $events = array('beforeSpecification', 'afterExample');

        return array_combine($events, $events);
    }

    public function setIO(IO $io)
    {
        $this->io = $io;
    }

    public function beforeSpecification(SpecificationEvent $event)
    {
        $this->writeln(sprintf(
            "\n%s", $this->formatSpecificationName($event->getSpecification())
        ));
    }

    public function afterExample(ExampleEvent $event)
    {
        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->writeln(sprintf(
                    "<passed>✔ %s</passed>",
                    $this->formatExampleName($event->getExample())
                ));
                break;
            case ExampleEvent::PENDING:
                $this->writeln(sprintf(
                    "<pending>- %s</pending>",
                    $this->formatExampleName($event->getExample())
                ));
                $this->writeln(sprintf(
                    "<pending>%s</pending>",
                    $this->formatExampleException($event->getException(), false)
                ));
                break;
            case ExampleEvent::FAILED:
                $this->writeln(sprintf(
                    "<failed>✘ %s</failed>",
                    $this->formatExampleName($event->getExample())
                ));
                $this->writeln(sprintf(
                    "<failed>%s</failed>",
                    $this->formatExampleException($event->getException(), $this->isVerbose())
                ));
                break;
        }
    }

    protected function formatSpecificationName(ReflectionClass $specification)
    {
        return str_replace('Spec\\', '', $specification->getName());
    }

    protected function formatExampleName(ReflectionMethod $example)
    {
        return str_replace('_', ' ', $example->getName());
    }

    protected function formatExampleException(Exception $exception, $verbose = false)
    {
        if (!$verbose) {
            return $this->padText(get_class($exception).': '.$exception->getMessage(), 2);
        } else {
            return $this->padText((string) $exception);
        }
    }

    private function padText($text, $indent = 2)
    {
        return implode("\n", array_map(function($line) use($indent) {
            return str_repeat(' ', $indent).$line;
        }, explode("\n", $text)));
    }

    private function writeln($text)
    {
        $this->io->getOutput()->writeln($text);
    }

    private function isVerbose()
    {
        return $this->io->getOutput()->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE;
    }
}
