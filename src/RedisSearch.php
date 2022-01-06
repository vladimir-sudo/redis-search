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
     * @param string $varName
     * @return string
     */
    private function getVarKey(string $varName): string
    {
        return $this->prefix . ':' . $varName;
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
     * @param $tableName
     * @param $partText
     * @param null $fieldName
     * @return mixed
     */
    public function searchFull($tableName, $partText, $fieldName = null)
    {
        $prefix = $this->getTablePrefix($tableName);

        if ($fieldName) {
            $prefix = $prefix . $fieldName . ':';
        } else {
            $prefix = $prefix . '*:';
        }

        $keys = $this->client->keys($prefix . '*' . urlencode($this->strtolower($partText)) . '*');

        $idList = [];
        foreach ($keys as $key) {
            $idList[] = $this->client->get($key);
        }

        return $idList;
    }

    /**
     * @param $tableName
     * @return mixed
     */
    public function totalCount($tableName)
    {
        return $this->client->get($this->prefix . ':' . $tableName . ':total_count');
    }

    /**
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
     * @param $tableName
     * @param $id
     * @param $data
     * @return bool
     */
    public function addOrUpdate($tableName, $id, $data)
    {
        $prefix = $this->getTablePrefix($tableName);

        $this->delete($tableName, $id);

        foreach ($data as $field => $val) {
            $this->client->set($prefix . $field . ':' . urlencode($this->strtolower($val)) . ':' . $id, $id);
        }

        return true;
    }

    /**
     * @param $tableName
     * @param $id
     * @param $data
     * @return bool
     */
    public function addOrUpdateRepeatField($tableName, $id, $data)
    {
        $prefix = $this->getTablePrefix($tableName);

        $this->delete($tableName, $id);

        foreach ($data as $key => $value) {
            foreach ($value as $val) {
                $this->client->set($prefix . $key . ':' . urlencode($this->strtolower($val)) . ':' . $id, $id);
            }
        }

        return true;
    }

    /**
     * @param $str
     * @return bool|false|mixed|string|string[]|null
     */
    public function strtolower($str)
    {
        return mb_strtolower($str);
    }

    /**
     * @param string $key
     * @param $data
     * @return mixed
     */
    public function refreshJSONString(string $key, $data)
    {
        $redisKey = $this->getVarKey($key);

        return $this->client->set($redisKey, json_encode($data));
    }

    /**
     * @param string $varName
     * @return mixed
     */
    public function getJsonData(string $varName)
    {
        $redisKey = $this->getVarKey($varName);

        return json_decode($this->client->get($redisKey), true);
    }

    /**
     * @param array $data
     * @param string $search
     * @return array
     */
    public function filterDataByString(array $data, string $search): array
    {
        $filteredData = [];

        foreach ($data as $item) {
            foreach ($item as $value) {
                if (strpos($value, $search) !== false) {
                    $filteredData[] = $item;
                    break;
                }
            }
        }

        return $filteredData;
    }

    /**
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
    }

    /**
     * Clear all cache
     */
    public function clearAll()
    {
        $this->client->flushAll();
    }

    /**
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
     * @return mixed|PredisClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param string $prefix
     * @return RedisSearch
     */
    public function setPrefix(string $prefix): RedisSearch
    {
        $this->prefix = $prefix;

        return $this;
    }
}
