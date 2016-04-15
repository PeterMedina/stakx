<?php

namespace allejo\stakx\Core;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleInterface extends AbstractLogger
{
    /**
     * @var ConsoleLogger
     */
    private $logger;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var bool
     */
    private $quiet;

    public function __construct (OutputInterface $output = null)
    {
        $this->output = $output;
        $this->logger = (is_null($output)) ? null : (new ConsoleLogger($output));
    }

    /**
     * @return boolean
     */
    public function isQuiet ()
    {
        return $this->quiet;
    }

    /**
     * @param boolean $quiet
     */
    public function setQuiet ($quiet)
    {
        $this->quiet = $quiet;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function log ($level, $message, array $context = array())
    {
        if (!is_null($this->output))
        {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Writes a message to the output and adds a newline at the end.
     *
     * @param string|array $messages The message as an array of lines of a single string
     * @param int          $options  A bitmask of options (one of the OUTPUT or VERBOSITY constants), 0 is considered the same as self::OUTPUT_NORMAL | self::VERBOSITY_NORMAL
     */
    public function writeln ($messages, $options = 0)
    {
        if (!$this->isQuiet())
        {
            $this->output->writeln($messages, $options);
        }
    }
}