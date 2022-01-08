<?php

namespace RedisSearch\RedisSearch;

use Predis\Client as PredisClient;

class RedisSearch
{
    private $client;

    private $prefix = 'search_cache';

    public function __construct(array $config = [], $predisClient = null)
    {
        if (isset($predisClient)) {
            $this->client = $predisClient;
        } else {
            $this->client = new PredisClient($config);
        }

        if (isset($config['tables_prefix'])) {
            $this->setPrefix($config['tables_prefix']);
        }
    }

    /**
     * @param string $tableName
     * @return string
     */
    private function getTablePrefix(string $tableName): string
    {
        return $this->prefix . ':' . $tableName . ':';
    }

    /**
     * @param $tableName
     * @param $data
     * @return bool
     */
    public function refreshByData($tableName, $data): bool
    {
        $prefix = $this->getTablePrefix($tableName);

        $currentKeyList = $this->client->keys($prefix . '*');

        if ($currentKeyList) {
            $this->client->del($currentKeyList);
        }

        $totalCount = 0;

        foreach ($data as $item) {
            if (isset($item['id'])) {
                $id = $item['id'];
                $totalCount++;

                foreach ($item as $field => $val) {
                    $this->client->set($prefix . $field . ':' . urlencode($this->strtolower($val)) . ':' . $id, $id);
                }
            }
        }

        $this->client->set($this->prefix . ':' . $tableName . ':total_count', $totalCount);

        return true;
    }

    /**
     * Search for a value in a specific column in the table, if the 4th parameter is passed, then it will search for a complete entry by the passed value
     *
     * @param $tableName
     * @param $partText
     * @param null $fieldName
     * @param bool $fullMatch
     * @return mixed
     */
    public function search($tableName, $partText, $fieldName = null, $fullMatch = false)
    {
        $prefix = $this->getTablePrefix($tableName);

        if ($fieldName) {
            $prefix .= $fieldName . ':';
        } else {
            $prefix .= '*:';
        }

        $postfix = $fullMatch ? ':*' : '*';
        $keys = $this->client->keys($prefix . urlencode($this->strtolower($partText)) . $postfix);

        $idList = [];
        foreach ($keys as $key) {
            $idList[] = $this->client->get($key);
        }

        return $idList;
    }

    /**
     * Returns the number of records in a table
     *
     * @param $tableName
     * @return mixed
     */
    public function totalCount($tableName)
    {
        return $this->client->get($this->prefix . ':' . $tableName . ':total_count');
    }

    /**
     * Deletes one row from the table by ID
     *
     * @param $tableName
     * @param $id
     * @return mixed
     */
    public function delete($tableName, $id)
    {
        $keys = $this->client->keys($this->prefix . ':' . $tableName . ':*:*:' . $id);

        if ($keys) {
            return $this->client->del($keys);
        }

        return true;
    }

    /**
     * Adds or updates one record in the table by ID
     *
     * @param $tableName
     * @param $id
     * @param $data
     * @return bool
     */
    public function addOrUpdate($tableName, $id, $data)
    {
        $prefix = $this->getTablePrefix($tableName);

        $this->delete($tableName, $id);

        foreach ($data as $field => $value) {
            if (is_array($value)) {
                foreach ($value as $val) {
                    $this->client->set($prefix . $field . ':' . urlencode($this->strtolower($val)) . ':' . $id, $id);
                }
            } else {
                $this->client->set($prefix . $field . ':' . urlencode($this->strtolower($value)) . ':' . $id, $id);
            }
        }

        return true;
    }

    /**
     * @param $str
     * @return bool|false|mixed|string|string[]|null
     */
    private function strtolower($str)
    {
        return mb_strtolower($str);
    }

    /**
     * In order to update a specific field by ID
     *
     * @param $tableName
     * @param $id
     * @param $field
     * @param $value
     * @return bool
     */
    public function updateField($tableName, $id, $field, $value)
    {
        $prefix = $this->getTablePrefix($tableName);

        $this->deleteField($tableName, $id, $field);

        $this->client->set($prefix . $field . ':' . urlencode($this->strtolower($value)) . ':' . $id, $id);

        return true;
    }

    /**
     * In order to delete a specific field by ID
     *
     * @param $tableName
     * @param $id
     * @param $field
     */
    private function deleteField($tableName, $id, $field)
    {
        $prefix = $this->getTablePrefix($tableName);

        $keys = $this->client->keys($prefix . $field . ':*:' . $id);
        if ($keys) {
            return $this->client->del($keys);
        }
        return null;
    }

    /**
     * Use for clear or Redis tables
     */
    public function clearAll()
    {
        $this->client->flushAll();
    }

    /**
     * Use to get all Redis rows, if a parameter is passed, then you will get all rows from a specific table
     *
     * @param null $table
     * @return mixed
     */
    public function getAll($table = null)
    {
        if (empty($table)) {
            return $this->client->keys('*');
        } else {
            $prefix = $this->getTablePrefix($table);

            return $this->client->keys($prefix . ':*');
        }
    }

    /**
     * You can specify the table prefix when initializing the service, or specify it via the method
     *
     * @return mixed|PredisClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * And you also have the option of using the standard Predis client
     *
     * @param string $prefix
     * @return RedisSearch
     */
    public function setPrefix(string $prefix): RedisSearch
    {
        $this->prefix = $prefix;

        return $this;
    }
}
