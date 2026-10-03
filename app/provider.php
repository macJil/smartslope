<?php
declare(strict_types=1);
final class ProviderRateLimit extends RuntimeException {}

/** Shared local-host quotas and cache, protected by one OS lock per provider. */
function provider_cached(string $provider, string $key, callable $fetch, float $spacing, int $ttl, array $limits): array {
    $root=sys_get_temp_dir() . '/smartslope-' . substr(hash('sha256',APP_ROOT),0,16);
    if (!is_dir($root) && !mkdir($root,0700,true) && !is_dir($root)) throw new RuntimeException('Provider cache is unavailable.');
    $lock=fopen($root . '/' . $provider . '.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Provider lock unavailable.');
    try {
        $file=$root . '/' . $provider . '.json';
        $state=is_file($file) ? json_decode((string)file_get_contents($file),true) : [];
        if (!is_array($state)) $state=[];
        $now=microtime(true); $cache=$state['cache'][$key] ?? null;
        if ($cache && $now-$cache['at']<$ttl) return $cache['value'];
        if ($now-($state['last']??0)<$spacing) throw new ProviderRateLimit('Please wait briefly before another provider request.');
        foreach ($limits as $seconds=>$limit) {
            $bucket=(int)floor($now/$seconds);
            $count=$state['counts'][(string)$seconds]??['bucket'=>$bucket,'count'=>0];
            if ($count['bucket']!==$bucket) $count=['bucket'=>$bucket,'count'=>0];
            if ($count['count'] >= $limit) throw new ProviderRateLimit('Provider request budget reached. Try later.');
            $count['count']++; $state['counts'][(string)$seconds]=$count;
        }
        $state['last']=$now;
        if (file_put_contents($file,json_encode($state,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Cannot persist request budget.');
        $value=$fetch();
        foreach ($state['cache']??[] as $k=>$entry) if ($now-$entry['at'] >= $ttl) unset($state['cache'][$k]);
        if (count($state['cache']??[])>=100) array_shift($state['cache']);
        $state['cache'][$key]=['at'=>microtime(true),'value'=>$value];
        if (file_put_contents($file,json_encode($state,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Cannot persist provider cache.');
        return $value;
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
