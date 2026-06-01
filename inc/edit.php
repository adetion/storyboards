<?php

class EditManager {
    private $config;
    private $editsPath;
    private $editEnabled;
    private $maxEditHistory;

    public function __construct($config) {
        $this->config = $config;
        $this->editsPath = $this->config['edits_path'];
        $this->editEnabled = $this->config['edit_enabled'];
        $this->maxEditHistory = $this->config['max_edit_history'];
        
        // 确保编辑目录存在
        if (!is_dir($this->editsPath)) {
            mkdir($this->editsPath, 0755, true);
        }
    }

    public function saveEdit($taskId, $editData) {
        if (!$this->editEnabled) {
            return false;
        }
        
        $edits = $this->getEdits($taskId);
        
        // 添加新编辑
        $newEdit = [
            'id' => uniqid(),
            'timestamp' => time(),
            'data' => $editData
        ];
        
        $edits[] = $newEdit;
        
        // 限制编辑历史记录数量
        if (count($edits) > $this->maxEditHistory) {
            array_shift($edits); // 删除最旧的记录
        }
        
        return $this->saveEditsToFile($taskId, $edits);
    }

    public function getEdits($taskId) {
        $editsFile = $this->getEditsFile($taskId);
        
        if (file_exists($editsFile)) {
            $content = file_get_contents($editsFile);
            return json_decode($content, true) ?? [];
        }
        
        return [];
    }

    public function undoEdit($taskId) {
        if (!$this->editEnabled) {
            return false;
        }
        
        $edits = $this->getEdits($taskId);
        
        if (empty($edits)) {
            return false;
        }
        
        // 移除最后一个编辑
        array_pop($edits);
        
        return $this->saveEditsToFile($taskId, $edits);
    }

    public function redoEdit($taskId) {
        // 重做功能需要额外的实现，这里简化处理
        // 实际项目中应保存撤销的编辑记录
        return false;
    }

    public function deleteEdits($taskId) {
        if (!$this->editEnabled) {
            return false;
        }
        
        $editsFile = $this->getEditsFile($taskId);
        
        if (file_exists($editsFile)) {
            return unlink($editsFile);
        }
        
        return true;
    }

    public function clearAllEdits() {
        if (!$this->editEnabled) {
            return false;
        }
        
        $files = glob($this->editsPath . '*.json');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }

    private function getEditsFile($taskId) {
        $taskId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $taskId);
        return $this->editsPath . "{$taskId}_edits.json";
    }

    private function saveEditsToFile($taskId, $edits) {
        $editsFile = $this->getEditsFile($taskId);
        $content = json_encode($edits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($editsFile, $content) !== false;
    }

    public function isEnabled() {
        return $this->editEnabled;
    }
}
