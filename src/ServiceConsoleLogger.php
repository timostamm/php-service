<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 14.09.18
 * Time: 01:07
 */

namespace TS\PhpService;


use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ServiceConsoleLogger extends AbstractLogger
{


    private const QUIET_STATE = 'running';


    /** @var OutputInterface */
    private $std;

    /** @var OutputInterface */
    private $err;


    private $verbosities = [
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
    ];


    private $styles = [
        LogLevel::EMERGENCY => ['', 'red', 'default', ['bold']],
        LogLevel::ALERT => ['', 'red', 'default', ['bold']],
        LogLevel::CRITICAL => ['', 'red', 'default', ['bold']],
        LogLevel::ERROR => ['', 'red', 'default', ['bold']],
        LogLevel::WARNING => ['! ', 'default', 'default', []],
        LogLevel::NOTICE => ['', 'default', 'default', []],
        LogLevel::INFO => ['info: ', 'yellow', 'default', []],
        LogLevel::DEBUG => ['debug: ', 'yellow', 'default', []],
    ];


    /** @var bool */
    private $forHumans = true;

    public function __construct(OutputInterface $output, bool $inputIsInteractive)
    {
        $this->std = $output;
        $this->err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $this->forHumans = $inputIsInteractive && $output->isDecorated();

        //$this->forHumans = false;

        if ($this->forHumans) {
            $stdFormatter = $this->std->getFormatter();
            $errFormatter = $this->err->getFormatter();
            foreach ($this->styles as $level => list($prefix, $fg, $bg, $opt)) {
                $stdFormatter->setStyle($level, new OutputFormatterStyle($fg, $bg, $opt));
                $errFormatter->setStyle($level, new OutputFormatterStyle($fg, $bg, $opt));
            }
        } else {
            $this->std->setDecorated(false);
            $this->err->setDecorated(false);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        if (!isset($this->verbosities[$level])) {
            throw new \InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        // std out or err out?
        $out = in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR])
            ? $this->err : $this->std;

        $verbosity = $this->verbosities[$level];

        $message = $this->interpolate($message, $context);

        if ($this->forHumans) {

            $message = $this->styles[$level][0] . $message;
            $message = sprintf('<%1$s>%2$s</%1$s>', $level, $message);
            $out->writeln($message, $verbosity);

        } else {

            $message = sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), strtoupper($level), $message);
            $out->writeln($message, $verbosity);

        }
    }


    /**
     * @author PHP Framework Interoperability Group
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolate(string $message, array $context): string
    {
        if (false === strpos($message, '{')) {
            return $message;
        }

        $replacements = array();
        foreach ($context as $key => $val) {
            if (null === $val || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
                $replacements["{{$key}}"] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
            } elseif (\is_object($val)) {
                $replacements["{{$key}}"] = '[object ' . \get_class($val) . ']';
            } else {
                $replacements["{{$key}}"] = '[' . \gettype($val) . ']';
            }
        }

        return strtr($message, $replacements);
    }

}
