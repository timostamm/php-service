<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 14.09.18
 * Time: 01:07
 */

namespace TS\PhpService;


use InvalidArgumentException;
use LogicException;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ServiceConsoleLogger extends AbstractLogger
{


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
    private $useColors = true;

    /** @var bool */
    private $showDate = false;


    public function __construct(OutputInterface $output = null)
    {
        if ($output) {
            $this->setConsole($output);
        }
    }


    public function setConsole(OutputInterface $output)
    {
        $this->std = $output;
        $this->err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $stdFormatter = $this->std->getFormatter();
        $errFormatter = $this->err->getFormatter();
        foreach ($this->styles as $level => list($prefix, $fg, $bg, $opt)) {
            $stdFormatter->setStyle($level, new OutputFormatterStyle($fg, $bg, $opt));
            $errFormatter->setStyle($level, new OutputFormatterStyle($fg, $bg, $opt));
        }
    }


    public function setUseColors(bool $useColors): void
    {
        $this->useColors = $useColors;
    }

    public function setShowDate(bool $showDate): void
    {
        $this->showDate = $showDate;
    }


    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        if (!$this->std) {
            throw new LogicException('Missing console output. You have to provide a console output either in the constructor or with a setConsole() call.');
        }

        if (!isset($this->verbosities[$level])) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        // std out or err out?
        $out = in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR])
            ? $this->err : $this->std;

        $message = $this->interpolate($message, $context);

        $str = '';
        if ($this->showDate) {
            $str .= sprintf('[%s] ', date('Y-m-d H:i:s'));
        }
        if ($this->useColors) {
            $str .= sprintf('<%1$s>%2$s</%1$s>', $level, $message);
        } else {
            $str .= sprintf('%s: %s', strtoupper($level), $message);
        }

        $out->writeln($str, $this->verbosities[$level]);
    }


    /**
     * @param string $message
     * @param array $context
     * @return string
     * @author PHP Framework Interoperability Group
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
