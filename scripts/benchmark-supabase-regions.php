<?php

declare(strict_types=1);

const WARMUP_RUNS = 5;
const MEASURED_RUNS = 30;

function readEnvValue(string $path, string $name): string
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    $value = null;
    foreach ($lines as $line) {
        if (!preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*(.*)\s*$/', $line, $matches)) {
            continue;
        }

        $candidate = trim($matches[1]);
        if (strlen($candidate) >= 2) {
            $first = $candidate[0];
            $last = $candidate[strlen($candidate) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $candidate = substr($candidate, 1, -1);
            }
        }
        $value = $candidate;
    }

    if (!$value) {
        throw new RuntimeException("Missing {$name} in {$path}");
    }

    return $value;
}

function connectionConfig(string $databaseUrl): array
{
    $parts = parse_url($databaseUrl);
    if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'])) {
        throw new RuntimeException('Invalid PostgreSQL URL.');
    }

    parse_str($parts['query'] ?? '', $query);
    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $database = ltrim($parts['path'] ?? '/postgres', '/');
    $sslMode = $query['sslmode'] ?? 'require';

    return [
        'dsn' => "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}",
        'username' => rawurldecode($parts['user']),
        'password' => rawurldecode($parts['pass']),
        'host' => $host,
    ];
}

function connect(array $config): PDO
{
    return new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 15,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function elapsedMilliseconds(callable $callback): float
{
    $startedAt = hrtime(true);
    $callback();
    return (hrtime(true) - $startedAt) / 1_000_000;
}

function percentile(array $values, float $percentile): float
{
    sort($values, SORT_NUMERIC);
    $index = max(0, (int) ceil($percentile * count($values)) - 1);
    return $values[$index];
}

function metrics(array $values): array
{
    return [
        'median' => percentile($values, 0.50),
        'p95' => percentile($values, 0.95),
        'mean' => array_sum($values) / count($values),
        'min' => min($values),
        'max' => max($values),
    ];
}

function measurePair(callable $tokyo, callable $singapore): array
{
    $samples = ['tokyo' => [], 'singapore' => []];
    $totalRuns = WARMUP_RUNS + MEASURED_RUNS;

    for ($run = 0; $run < $totalRuns; $run++) {
        $order = $run % 2 === 0
            ? ['tokyo' => $tokyo, 'singapore' => $singapore]
            : ['singapore' => $singapore, 'tokyo' => $tokyo];

        foreach ($order as $region => $callback) {
            $elapsed = elapsedMilliseconds($callback);
            if ($run >= WARMUP_RUNS) {
                $samples[$region][] = $elapsed;
            }
        }
    }

    return [
        'tokyo' => metrics($samples['tokyo']),
        'singapore' => metrics($samples['singapore']),
    ];
}

function benchmarkQuery(PDO $connection, string $sql): callable
{
    return static function () use ($connection, $sql): void {
        $statement = $connection->query($sql);
        $statement->fetchAll();
        $statement->closeCursor();
    };
}

function printResult(string $name, array $result): void
{
    $tokyo = $result['tokyo'];
    $singapore = $result['singapore'];
    $improvement = $tokyo['median'] > 0
        ? (($tokyo['median'] - $singapore['median']) / $tokyo['median']) * 100
        : 0.0;

    printf(
        "%-24s Tokyo %8.2f ms (p95 %8.2f) | Singapore %8.2f ms (p95 %8.2f) | %+7.1f%%\n",
        $name,
        $tokyo['median'],
        $tokyo['p95'],
        $singapore['median'],
        $singapore['p95'],
        $improvement
    );
}

$root = dirname(__DIR__);
$tokyo = connectionConfig(readEnvValue($root . '/.env.supabase.tokyo.rollback', 'SUPABASE_DATABASE_URL'));
$singapore = connectionConfig(readEnvValue($root . '/.env.supabase', 'SUPABASE_DATABASE_URL'));

if (strpos($tokyo['host'], 'ap-northeast-1') === false) {
    throw new RuntimeException('Tokyo benchmark URL does not point to ap-northeast-1.');
}
if (strpos($singapore['host'], 'ap-southeast-1') === false) {
    throw new RuntimeException('Singapore benchmark URL does not point to ap-southeast-1.');
}

echo "Read-only benchmark: " . MEASURED_RUNS . " measured runs after " . WARMUP_RUNS . " warmups\n";
echo "Tokyo: {$tokyo['host']}\n";
echo "Singapore: {$singapore['host']}\n\n";

$connectResult = measurePair(
    static function () use ($tokyo): void {
        $connection = connect($tokyo);
        $connection->query('select 1')->fetchColumn();
        $connection = null;
    },
    static function () use ($singapore): void {
        $connection = connect($singapore);
        $connection->query('select 1')->fetchColumn();
        $connection = null;
    }
);
printResult('connect + SELECT 1', $connectResult);

$tokyoConnection = connect($tokyo);
$singaporeConnection = connect($singapore);

$queries = [
    'warm SELECT 1' => 'select 1',
    'orders count' => 'select count(*) from himoto.orders where deleted_at is null',
    'dashboard 30 days' => <<<'SQL'
select day, sum(total) as total
from (
    select date(created_at) as day,
           coalesce(sum(cast(nullif(total, '') as decimal)), 0) as total
    from himoto.orders
    where deleted_at is null
      and created_at >= current_date - interval '30 days'
    group by date(created_at)
    union all
    select date(created_at) as day,
           coalesce(sum(value), 0) as total
    from himoto.transactions
    where created_at >= current_date - interval '30 days'
    group by date(created_at)
) daily_totals
group by day
order by day
SQL,
];

foreach ($queries as $name => $sql) {
    $result = measurePair(
        benchmarkQuery($tokyoConnection, $sql),
        benchmarkQuery($singaporeConnection, $sql)
    );
    printResult($name, $result);
}
