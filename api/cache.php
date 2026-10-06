<?php
// той самий файловий кеш з КП№7, але ключ тепер обов'язково
// включає owner_token - інакше перший-ліпший відвідувач міг би отримати
// закешований баланс іншого власника (витік чужих фінансових даних черезспільний кеш - ще один клас проблеми, знайдений під час аудиту)
function cacheFilePath(string $key): string
{
    return sys_get_temp_dir() . '/practicum8_cache_' . md5($key) . '.json';
}

function cachedQuery(PDO $pdo, string $key, string $sql, array $params, int $ttlSeconds = 45): array
{
    $file = cacheFilePath($key);

    if (is_file($file) && (time() - filemtime($file)) < $ttlSeconds) {
        $cached = json_decode(file_get_contents($file), true);
        if (is_array($cached)) {
            $cached['_cache'] = 'hit';
            return $cached;
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $result = is_array($result) ? $result : [];

    file_put_contents($file, json_encode($result));

    $result['_cache'] = 'miss';
    return $result;
}

function invalidateCache(string $key): void
{
    $file = cacheFilePath($key);
    if (is_file($file)) {
        unlink($file);
    }
}
