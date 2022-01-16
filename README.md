# redis-search

RedisSearch use [predis/predis](https://github.com/predis/predis) library.
### Instalation
`composer require vladimir50/redis-search`

You have two ways to init service

1) You init service with config
```php
    use RedisSearch\RedisSearch\RedisSearch;

    $redisSearch = new RedisSearch([
        'scheme'        => 'tcp',
        'host'          => '127.0.0.1',
        'port'          => 6379,
        'tables_prefix' => 'search_cache' // Redis search tables prefix
    ]);
```
2) Or you init with youre Predis client
```php
    use RedisSearch\RedisSearch\RedisSearch;
    
    $client = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '10.0.0.1',
        'port'   => 6379,
    ]);

    $redisSearch = new RedisSearch(['tables_prefix' => 'search_cache'], $client);
```
### Methods Guide
Search for a value in a specific column in the table, if the 4th parameter is passed, then it will search for a complete entry by the passed value
```php
    $value = 'red';
    $key = 'color';
    
    $redisSearch->search('products', $value, $key);
```
Search in range for a value in a specific column in the table
```php
    $values = [
        10,
        100
    ];
    $key = 'price';
    
    $redisSearch->search('products', $values, $key);
```
Returns the number of records in a table
```php
    $redisSearch->totalCount('products');
```
Deletes one row from the table by ID
```php
    $redisSearch->delete('products', $id);
```
Adds or updates one record in the table by ID
```php
    $fieldsData = [
        'color' => 'red',
        'type' => 'car',
        'categories' => [
            'cars',
            'red_color'
        ]
    ];
    
    $redisSearch->addOrUpdate('products', $id, $fieldsData);
```
In order to update a specific field by ID
```php
    $redisSearch->updateField('products', $id, $filedName, $fildValue);
```
In order to delete a specific field by ID
```php
    $redisSearch->deleteField('products', $id, $filedName);
```
Use for clear or Redis tables
```php
    $redisSearch->clearAll();
```
Use to get all Redis rows, if a parameter is passed, then you will get all rows from a specific table
```php
    $rows = $redisSearch->getAll();
    // or
    $rows = $redisSearch->getAll('products');
```
You can specify the table prefix when initializing the service, or specify it via the method
```php
    $redisSearch->setPrefix('products');
```
And you also have the option of using the standard Predis client
```php
    /** @var Predis\Client $predisClient */
    $predisClient = $redisSearch->getClient();
```
