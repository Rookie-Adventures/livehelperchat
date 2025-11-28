<?php
/**
 * LiveHelperChat 中文翻译工具
 * 统一的翻译管理和处理工具
 */

class TranslationTool {
    private $translationFile;
    private $backupDir;
    
    public function __construct() {
        $this->translationFile = 'lhc_web/translations/zh_CN/translation.ts';
        $this->backupDir = 'translation_backups';
        $this->ensureBackupDir();
    }
    
    private function ensureBackupDir() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function backup() {
        $backupFile = $this->backupDir . '/translation_' . date('Y-m-d_H-i-s') . '.ts';
        copy($this->translationFile, $backupFile);
        echo "备份已创建: $backupFile\n";
        return $backupFile;
    }
    
    public function getProgress() {
        $content = file_get_contents($this->translationFile);
        $total = preg_match_all('/<message>/', $content);
        $unfinished = preg_match_all('/<translation type="unfinished"\/>/', $content);
        $completed = $total - $unfinished;
        $progress = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
        
        return [
            'total' => $total,
            'completed' => $completed,
            'unfinished' => $unfinished,
            'progress' => $progress
        ];
    }
    
    public function extractUnfinished($limit = 50) {
        $content = file_get_contents($this->translationFile);
        $contexts = preg_split('/(<context>.*?<\/context>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $unfinished = [];
        $currentContext = '';
        
        foreach ($contexts as $section) {
            if (preg_match('/<context>\s*<name>(.*?)<\/name>/s', $section, $matches)) {
                $currentContext = trim($matches[1]);
                
                if (preg_match_all('/<message>\s*<source>(.*?)<\/source>\s*<translation type="unfinished"\/>\s*<\/message>/s', $section, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $sourceText = trim($match[1]);
                        if (!empty($sourceText)) {
                            $unfinished[] = [
                                'context' => $currentContext,
                                'source' => $sourceText
                            ];
                        }
                    }
                }
            }
        }
        
        return array_slice($unfinished, 0, $limit);
    }
    
    public function translateBatch($translations) {
        $this->backup();
        $content = file_get_contents($this->translationFile);
        $changedCount = 0;
        
        foreach ($translations as $source => $translation) {
            $pattern = '/(<message>\s*<source>' . preg_quote($source, '/') . '<\/source>\s*)<translation type="unfinished"\/>(\s*<\/message>)/s';
            $replacement = '$1<translation>' . htmlspecialchars($translation, ENT_XML1, 'UTF-8') . '</translation>$2';
            
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
                $changedCount++;
                echo "✓ 翻译: $source -> $translation\n";
            }
        }
        
        file_put_contents($this->translationFile, $content);
        echo "\n批次翻译完成！共翻译了 $changedCount 个条目。\n";
        return $changedCount;
    }
}

// 命令行接口
if (php_sapi_name() === 'cli') {
    $tool = new TranslationTool();
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'progress':
            $progress = $tool->getProgress();
            echo "=== 翻译进度 ===\n";
            echo "总条目: {$progress['total']}\n";
            echo "已完成: {$progress['completed']}\n";
            echo "未完成: {$progress['unfinished']}\n";
            echo "进度: {$progress['progress']}%\n";
            break;
            
        case 'extract':
            $limit = intval($argv[2] ?? 20);
            $unfinished = $tool->extractUnfinished($limit);
            echo "=== 前 $limit 个未翻译条目 ===\n\n";
            foreach ($unfinished as $index => $item) {
                echo "[" . ($index + 1) . "] Context: {$item['context']}\n";
                echo "Source: {$item['source']}\n\n";
            }
            break;
            
        case 'backup':
            $tool->backup();
            break;
            
        default:
            echo "用法:\n";
            echo "  php translation_tool.php progress     - 查看翻译进度\n";
            echo "  php translation_tool.php extract [n]  - 提取前n个未翻译条目\n";
            echo "  php translation_tool.php backup       - 创建备份\n";
    }
}
?>