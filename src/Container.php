<?php

namespace LdapRecord;

use LdapRecord\Log\EventLogger;
use LdapRecord\Log\LogsInformation;
use LdapRecord\Events\DispatchesEvents;

class Container
{
    use DispatchesEvents, LogsInformation;

    /**
     * Current instance of the container.
     *
     * @var Container
     */
    protected static $instance;

    /**
     * Connections in the container.
     *
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * The events to register listeners for during initialization.
     *
     * @var array
     */
    protected $listen = [
        'LdapRecord\Auth\Events\*',
        'LdapRecord\Query\Events\*',
        'LdapRecord\Models\Events\*',
    ];

    /**
     * Get or set the current instance of container.
     *
     * @return Container
     */
    public static function getInstance()
    {
        return self::$instance ?? self::getNewInstance();
    }

    /**
     * Set and get a new instance of container.
     *
     * @return Container
     */
    public static function getNewInstance()
    {
        return self::$instance = new self();
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->initEventLogger();
    }

    /**
     * Initializes the event logger.
     *
     * @return void
     */
    public function initEventLogger()
    {
        $dispatcher = static::getEventDispatcher();

        $logger = $this->newEventLogger();

        foreach ($this->listen as $event) {
            $dispatcher->listen($event, function ($eventName, $events) use ($logger) {
                foreach ($events as $event) {
                    $logger->log($event);
                }
            });
        }
    }

    /**
     * Add a new connection into the container.
     *
     * @param ConnectionInterface $connection
     * @param string              $name
     *
     * @return $this
     */
    public function add(ConnectionInterface $connection, string $name = null)
    {
        $this->connections[$name ?? $this->default] = $connection;

        return $this;
    }

    /**
     * Remove a connection from the container.
     *
     * @param $name
     *
     * @return $this
     */
    public function remove($name)
    {
        if ($this->exists($name)) {
            unset($this->connections[$name]);
        }

        return $this;
    }

    /**
     * Return all of the connections from the container.
     *
     * @return Connection[]
     */
    public function all()
    {
        return $this->connections;
    }

    /**
     * Get a connection by name or return the default.
     *
     * @param string|null $name
     *
     * @throws ContainerException If the given connection does not exist.
     *
     * @return mixed
     */
    public function get(string $name = null)
    {
        $name = $name ?? $this->default;

        if ($this->exists($name)) {
            return $this->connections[$name];
        }

        throw new ContainerException("The connection connection '$name' does not exist.");
    }

    /**
     * Return the default connection.
     *
     * @return Connection
     */
    public function getDefault()
    {
        return $this->get($this->default);
    }

    /**
     * Checks if the connection exists.
     *
     * @param $name
     *
     * @return bool
     */
    public function exists($name): bool
    {
        return array_key_exists($name, $this->connections);
    }

    /**
     * Set the default connection name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setDefault($name = null)
    {
        $this->default = $name;

        return $this;
    }

    /**
     * Returns a new event logger instance.
     *
     * @return EventLogger
     */
    protected function newEventLogger()
    {
        return new EventLogger(static::getLogger());
    }
}
