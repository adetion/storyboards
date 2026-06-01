<?php
class Logger {
    private $logFile;
    
    public function __construct($filename = 'script_converter') {
        $this->logFile = Config::LOG_DIR . $filename . '_' . date('Y-m-d') . '.log';
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function error($message) {
        $this->log($message, 'ERROR');
    }
    
    public function info($message) {
        $this->log($message, 'INFO');
    }
    
    public function debug($message) {
        $this->log($message, 'DEBUG');
    }
    
    // 添加 warning 方法
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
}
?>