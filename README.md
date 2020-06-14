### Плагин кеширования данных через memcached

Принимая во внимание то, что в конфиг-файле объявлено два ключа кеширования (primary и backup)
```
'primary-memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],
        'backup-memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],
```
реализуем следующую задачу:

- Запрашиваемые страницы(далее данные) пользователем должны выдаваться из кеша
- В качестве ключа хранения/сохранения/чтения/записи выступает ссылка на данные(страницу), включая домен, значение - сами данные
- Если домен, имеет префиксы "n" или "nocache", то такие префиксы следует игнорировать при чтении по ключу из кеш-службы
- В качестве доп.опции, пользователь может дополнительно также добавить еще префиксы(через запятую) в файл окружения (.env), ключ CACHE_IGNORE_PREFIXES. Например: CACHE_IGNORE_PREFIXES = "a, b, c".
- Если результат запроса - ошибка 500-ой серии, пытаемся найти в кеше, причем только в backup, если там есть контент, отдаем его, в противном случае, отдаем ошибку без сохранения в кеш
