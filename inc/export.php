<?php

class ExportManager {
    private $config;
    private $exportPath;
    private $exportEnabled;
    private $exportFormats;

    public function __construct($config) {
        $this->config = $config;
        $this->exportPath = $this->config['exports_path'];
        $this->exportEnabled = $this->config['export_enabled'];
        $this->exportFormats = $this->config['export_formats'];
        
        // 确保导出目录存在
        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }
    }

    public function export($html, $format, $filename) {
        if (!$this->exportEnabled) {
            throw new Exception("Export functionality is disabled.");
        }
        
        if (!in_array($format, $this->exportFormats)) {
            throw new Exception("Unsupported export format: {$format}");
        }
        
        // 添加文件扩展名
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        $filename .= ".{$format}";
        
        // 根据格式执行不同的导出逻辑
        switch ($format) {
            case 'pdf':
                return $this->exportToPdf($html, $filename);
            case 'word':
                return $this->exportToWord($html, $filename);
            case 'excel':
                return $this->exportToExcel($html, $filename);
            default:
                throw new Exception("Unsupported export format: {$format}");
        }
    }

    private function exportToPdf($html, $filename) {
        // 使用TCPDF或其他PDF库生成PDF
        // 这里使用简化的实现，实际项目中应使用专业PDF库
        $pdfContent = "PDF Content: {$html}";
        $filePath = $this->exportPath . $filename;
        file_put_contents($filePath, $pdfContent);
        
        $this->downloadFile($filePath, $filename);
        return true;
    }

    private function exportToWord($html, $filename) {
        // 使用PHPOffice/PHPWord或其他库生成Word文档
        // 这里使用简化的实现，实际项目中应使用专业Word库
        $wordContent = "<!DOCTYPE html><html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
<meta charset='utf-8'>
<title>Document</title>
</head>
<body>
{$html}
</body>
</html>";
        
        $filePath = $this->exportPath . $filename;
        file_put_contents($filePath, $wordContent);
        
        $this->downloadFile($filePath, $filename);
        return true;
    }

    private function exportToExcel($html, $filename) {
        // 使用PHPOffice/PHPExcel或其他库生成Excel文档
        // 这里使用简化的实现，实际项目中应使用专业Excel库
        $excelContent = "Excel Content: {$html}";
        $filePath = $this->exportPath . $filename;
        file_put_contents($filePath, $excelContent);
        
        $this->downloadFile($filePath, $filename);
        return true;
    }

    private function downloadFile($filePath, $filename) {
        // 设置响应头，让浏览器下载文件
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function getAvailableFormats() {
        return $this->exportFormats;
    }

    public function isEnabled() {
        return $this->exportEnabled;
    }
}
