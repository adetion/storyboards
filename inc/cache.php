<?php

class CacheManager {
    private $config;
    private $cachePath;
    private $cacheEnabled;
    private $cacheLifetime;
    private $cachePrefix;

    public function __construct($config) {
        $this->config = $config;
        $this->cachePath = $this->config['cache_path'];
        $this->cacheEnabled = $this->config['cache_enabled'];
        $this->cacheLifetime = $this->config['cache_lifetime'];
        $this->cachePrefix = $this->config['cache_prefix'];
        
        // 确保缓存目录存在
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function set($key, $value, $lifetime = null) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $lifetime = $lifetime ?? $this->cacheLifetime;
        $cacheFile = $this->getCacheFile($key);
        $data = [
            'value' => $value,
            'expire' => time() + $lifetime
        ];
        
        return file_put_contents($cacheFile, serialize($data)) !== false;
    }

    public function get($key) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($cacheFile));
        
        if (time() > $data['expire']) {
            $this->delete($key);
            return false;
        }
        
        return $data['value'];
    }

    public function delete($key) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }

    public function clear() {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $pattern = $this->cachePath . $this->cachePrefix . '*';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }

    public function exists($key) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($cacheFile));
        
        if (time() > $data['expire']) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    private function getCacheFile($key) {
        $hash = md5($key);
        return $this->cachePath . $this->cachePrefix . $hash . '.cache';
    }

    public function flushExpired() {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $pattern = $this->cachePath . $this->cachePrefix . '*';
        $files = glob($pattern);
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = unserialize(file_get_contents($file));
                if (time() > $data['expire']) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        return $count;
    }
}
