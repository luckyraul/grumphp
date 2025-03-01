<?php

namespace spec\GrumPHP\Process;

use GrumPHP\Collection\ProcessArgumentsCollection;
use GrumPHP\Configuration\Model\ProcessConfig;
use GrumPHP\IO\IOInterface;
use GrumPHP\Locator\ExternalCommand;
use GrumPHP\Process\ProcessBuilder;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Process\Process;

class ProcessBuilderSpec extends ObjectBehavior
{
    function let(ExternalCommand $externalCommandLocator, IOInterface $io, ProcessConfig $config)
    {
        $this->beConstructedWith($externalCommandLocator, $io, $config);
        $config->getTimeout()->willReturn(60);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ProcessBuilder::class);
    }

    function it_should_be_able_to_create_process_arguments_based_on_taskname(ExternalCommand $externalCommandLocator)
    {
        $externalCommandLocator->locate('grumphp')->willReturn('/usr/bin/grumphp');

        $arguments = $this->createArgumentsForCommand('grumphp');
        $arguments->shouldHaveType(ProcessArgumentsCollection::class);
        $arguments[0]->shouldBe('/usr/bin/grumphp');
        $arguments->count()->shouldBe(1);
    }

    function it_should_be_able_to_create_process_arguments_based_on_taskname_and_manipulate_path(ExternalCommand $externalCommandLocator)
    {
        $externalCommandLocator->locate('grumphp')->willReturn('/usr/bin/grumphp');

        $arguments = $this->createArgumentsForCommand('grumphp', function (string $path): string {
            return $path . '.manipulated';
        });
        $arguments->shouldHaveType(ProcessArgumentsCollection::class);
        $arguments[0]->shouldBe('/usr/bin/grumphp.manipulated');
        $arguments->count()->shouldBe(1);
    }

    function it_should_build_process_based_on_process_arguments(IOInterface $io)
    {
        $io->isVeryVerbose()->willReturn(false);

        $arguments = new ProcessArgumentsCollection(['/usr/bin/grumphp']);
        $process = $this->buildProcess($arguments);

        $process->shouldHaveType(Process::class);
        $process->getCommandLine()->shouldBeQuoted('/usr/bin/grumphp');
    }

    function it_should_be_possible_to_configure_the_process_timeout(ProcessConfig $config, IOInterface $io)
    {
        $io->isVeryVerbose()->willReturn(false);

        $config->getTimeout()->willReturn(120);

        $arguments = new ProcessArgumentsCollection(['/usr/bin/grumphp']);
        $process = $this->buildProcess($arguments);
        $process->getTimeout()->shouldBe(120.0);
    }

    function it_outputs_the_command_when_run_very_very_verbose(IOInterface $io)
    {
        $io->isVeryVerbose()->willReturn(true);

        $command = '/usr/bin/grumphp';
        $io->write(Argument::withEveryEntry(Argument::containingString($command)), true)->shouldBeCalled();

        $arguments = new ProcessArgumentsCollection([$command]);
        $this->buildProcess($arguments);
    }

    public function getMatchers(): array
    {
        return [
            'beQuoted' => function ($subject, $string) {
                if ('\\' === DIRECTORY_SEPARATOR) {
                    if ($subject !== $string) {
                        throw new FailureException(sprintf(
                            'Expected %s, got %s.',
                            $string,
                            $subject
                        ));
                    }
                    return true;
                }

                $regex = sprintf('{^([\'"])%s\1$}', preg_quote($string));
                if (!preg_match($regex, $subject)) {
                    throw new FailureException(sprintf(
                        'Expected a quoted version of %s, got %s.',
                        $string,
                        $subject
                    ));
                }

                return true;
            }
        ];
    }
}
