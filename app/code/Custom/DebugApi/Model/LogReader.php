<?php
namespace Custom\DebugApi\Model;

use Custom\DebugApi\Api\LogReaderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class LogReader implements LogReaderInterface
{
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getExceptionLog()
    {
        return $this->readLog('exception.log', 200);
    }

    public function getSystemLog()
    {
        return $this->readLog('system.log', 200);
    }

    public function getDebugLog()
    {
        return $this->readLog('debug.log', 200);
    }

    private function readLog($filename, $lines = 100)
    {
        try {
            $logDir = $this->filesystem->getDirectoryRead(DirectoryList::LOG);
            $logPath = $logDir->getAbsolutePath($filename);
            
            if (!file_exists($logPath)) {
                return json_encode(['error' => "Log file not found: {$filename}"]);
            }

            // Leer últimas N líneas
            $file = new \SplFileObject($logPath, 'r');
            $file->seek(PHP_INT_MAX);
            $lastLine = $file->key();
            $startLine = max(0, $lastLine - $lines);
            
            $content = [];
            $file->seek($startLine);
            while (!$file->eof()) {
                $content[] = $file->current();
                $file->next();
            }

            return json_encode([
                'file' => $filename,
                'lines' => count($content),
                'content' => implode('', $content)
            ]);
        } catch (\Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}