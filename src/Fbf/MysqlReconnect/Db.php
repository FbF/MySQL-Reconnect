<?php namespace Fbf\MysqlReconnect;

/**
 * PDO helper class that can handle auto reconnection to combat time out issues, which
 * is useful for long running CLI scripts.
 *
 * Inspired by https://gist.github.com/extraordinaire/4135119
 */
class Db {

	/**
	 * Stores the dsn string, e.g. mysql:dbname=mydb;host=localhost
	 * @var string
	 */
	protected $dsn;

	/**
	 * Stores the username
	 * @var string
	 */
	protected $user;

	/**
	 * Stores the database password
	 * @var string
	 */
	protected $pass;

	/**
	 * Stores the database host, e.g. localhost
	 * @var string
	 */
	protected $host;

	/**
	 * Stores the database name, e.g. mydb
	 * @var string
	 */
	protected $database;

	/**
	 * Stores the driver name, e.g. mysql
	 * @var string
	 */
	protected $driver;

	/**
	 * Flag to indicate whether to automatically reconnect to the database if
	 * the connection times out.
	 * @var boolean
	 */
	protected $reconnect = false;

	/**
	 * Stores the driver options passed to the PDO constructor, e.g.
	 * 	array(driver_option => option_value)
	 * @var array
	 */
	protected $driverOptions = array();

	/**
	 * The allowed driver options
	 * @var array
	 */
	protected $allowedDriverOptions = array(
        \PDO::ATTR_PERSISTENT,
        \PDO::ATTR_ERRMODE,
        \PDO::ATTR_DEFAULT_FETCH_MODE,
	);

	/**
	 * Some sensible defaults
	 * @var array
	 */
	protected $defaultDriverOptions = array(
        \PDO::ATTR_PERSISTENT => true,
        \PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
	);

	/**
	 * Stores the PDO connection object
	 * @var \PDO
	 */
	protected $dbh;

	/**
	 * Stores the config options in the object properties to refer to later
	 *
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		if (!array_key_exists('dsn', $config)
			&& array_key_exists('host', $config)
			&& array_key_exists('database', $config)
			&& array_key_exists('driver', $config))
		{
			$this->host = $config['host'];
			$this->database = $config['database'];
			$this->driver = $config['driver'];
			$this->dsn = $this->dsn();
		}
		else
		{
			$this->dsn = $config['dsn'];
		}
		if (!$this->dsn || !array_key_exists('user', $config) || !array_key_exists('pass', $config))
		{
			throw new \InvalidArgumentException('DSN or host/driver/database, and/or user and/or pass, not supplied.');
		}
		$this->user = $config['user'];
		$this->pass = $config['pass'];
		$this->driverOptions = array_intersect_key(array_merge(
			$this->defaultDriverOptions,
			$config
		), $this->allowedDriverOptions);
	}

	/**
	 * Returns the dsn string built from the driver, database name and host
	 * @return string
	 */
	protected function dsn()
	{
		return $this->driver.':dbname='.$this->database.';host='.$this->host;
	}

	/**
	 * Connects to the database using PDO and stores and returns the PDO object
	 * @return \PDO
	 */
	public function connect()
	{
        $this->dbh = new \PDO($this->dsn, $this->user, $this->pass, $this->driverOptions);
        $this->query('SET NAMES utf8');
        return $this->dbh;
	}

	/**
	 * Reconnects to the database using PDO and returns the new PDO object
	 * @return \PDO
	 */
	public function reconnect()
	{
		$this->disconnect();
		return $this->connect();
	}

	/**
	 * Disconnects from the database
	 * @return void
	 */
	public function disconnect()
	{
		$this->dbh = null;
	}

	/**
	 * Pings the database, used to keep the connection alive
	 * @return void
	 */
	public function ping()
	{
        $this->query("SELECT 1");
	}

	/**
	 * Retuns the existing PDO object, or a new one if we haven't got one yet
	 * @return \PDO
	 */
	public function connection()
	{
		return $this->dbh instanceof \PDO ? $this->dbh : $this->connect();
	}

	/**
	 * Magic method, passes the call off to the PDO object. If the call throws a
	 * PDOException that the server has gone away, and the reconnect flag is set to
	 * true, reconnect and try to issue the call to the PDO object again.
	 * @param  string $function The function that was called
	 * @param  array  $args The arguments supplied
	 * @return mixed Whatever PDO::$function($args) returns
	 */
	public function __call($function, array $args = array())
    {
        try {
	        $result = call_user_func_array(array($this->connection(), $function), $args);
        } catch(\PDOException $e) {
            if ($e->getCode() != 'HY000' || !stristr($e->getMessage(), 'server has gone away')) {
	            throw $e;
            }
            $this->reconnect();
	        $result = call_user_func_array(array($this->connection(), $function), $args);
        }
        return $result;
    }

    /**
     * Helper method for preparing a PDO statement, binding parameters and executing it
     * @param  string $sql
     * @param  array  $params
     * @param  array  $options
     * @return PDOStatement
     */
	public function query($sql, array $params = array(), array $options = array())
	{
		$stmt = $this->prepare($sql);
        if (!empty($params))
        {
            foreach ($params as $key => $value)
            {
                $param  = (is_int($key) ? ($key + 1) : ':'.$key);
                $stmt->bindParam($param, $params[$key]);
            }
        }
        $stmt->execute();
        return $stmt;
	}

}