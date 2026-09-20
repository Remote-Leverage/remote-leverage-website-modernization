<?php
// Exit 0 only if Acorn's own client can reach, authenticate against and select the cache database.
if (! extension_loaded('redis')) {
    exit(1);
}

$host = (string) getenv('PROBE_HOST');
$port = (int) getenv('PROBE_PORT');
$pass = (string) getenv('PROBE_PASS');
$db = (int) getenv('PROBE_DB');

try {
    $redis = new Redis;

    if (! $redis->connect($host, $port, 1.0)) {
        exit(1);
    }

    if ($pass !== '' && ! $redis->auth($pass)) {
        exit(1);
    }

    if (! $redis->select($db)) {
        exit(1);
    }

    $redis->ping();
    $redis->close();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

exit(0);
