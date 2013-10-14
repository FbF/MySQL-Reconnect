MySQL-Reconnect
===============

A PDO wrapper that reconnects automatically and re-issues queries on receiving MySQL server timeout errors

## Usage

    $config = array(
    	'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'mydb',
        'user' => 'username',
        'pass' => 'password',
    );

    $db = new Fbf\MysqlReconnect\Db($config);

    $sql = "SELECT * FROM posts WHERE id = :id";

    $data = array('id' => 1);

    $sth = $db->query($sql, $data);

    $post = $sth->fetchObj();

## How it works

Uses __call() magic method to pass off methods called to the PDO connection object, so you can call whatever you like on $db and it will pass it off to PDO. Inside the magic method we catch timeout exceptions and then reconnect before re-issuing the query.