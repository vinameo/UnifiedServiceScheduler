<?php

namespace App\Support\Cache;

use DateTimeInterface;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use ReflectionClass;

class DatabaseRateLimiter extends RateLimiter
{
    /**
     * Increment the counter for a given key for a given decay time by a given amount.
     *
     * In PostgreSQL with DatabaseStore, executes atomic lock-for-update transaction
     * with retry on unique constraint collision, eliminating race condition resets.
     *
     * @param  string  $key
     * @param  DateTimeInterface|\DateInterval|int  $decaySeconds
     * @param  int  $amount
     * @return int
     */
    public function increment($key, $decaySeconds = 60, $amount = 1)
    {
        $key = $this->cleanRateLimiterKey($key);

        $this->cache->add(
            $key.':timer', $this->availableAt($decaySeconds), $decaySeconds
        );

        $store = $this->cache->getStore();
        if ($store instanceof DatabaseStore) {
            return $this->atomicDatabaseHit($store, $key, $this->secondsUntil($decaySeconds), $amount);
        }

        return parent::increment($key, $decaySeconds, $amount);
    }

    private function atomicDatabaseHit(DatabaseStore $store, string $key, int $decaySeconds, int $amount): int
    {
        $ref = new ReflectionClass($store);

        $connProp = $ref->getProperty('connection');
        $connProp->setAccessible(true);
        /** @var Connection $connection */
        $connection = $connProp->getValue($store);

        $tableProp = $ref->getProperty('table');
        $tableProp->setAccessible(true);
        /** @var string $tableName */
        $tableName = $tableProp->getValue($store);

        $prefixProp = $ref->getProperty('prefix');
        $prefixProp->setAccessible(true);
        /** @var string $prefix */
        $prefix = $prefixProp->getValue($store);

        $prefixedKey = $prefix.$key;
        $expiration = time() + $decaySeconds;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return (int) $connection->transaction(function () use ($connection, $tableName, $prefixedKey, $expiration, $amount) {
                    $row = $connection->table($tableName)
                        ->where('key', $prefixedKey)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        $connection->table($tableName)->insert([
                            'key' => $prefixedKey,
                            'value' => serialize($amount),
                            'expiration' => $expiration,
                        ]);

                        return $amount;
                    }

                    $current = (int) unserialize($row->value);
                    $new = $current + $amount;

                    $connection->table($tableName)
                        ->where('key', $prefixedKey)
                        ->update([
                            'value' => serialize($new),
                            'expiration' => $expiration,
                        ]);

                    return $new;
                });
            } catch (QueryException $e) {
                // 23505 = UniqueViolation on concurrent initial insert; retry to hit the lockForUpdate branch
                if ($e->getCode() === '23505') {
                    continue;
                }
                throw $e;
            }
        }

        return $amount;
    }
}
