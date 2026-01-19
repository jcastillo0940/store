<?php
namespace Custom\DebugApi\Api;

interface LogReaderInterface
{
    /**
     * Get exception logs
     * @return string
     */
    public function getExceptionLog();
    
    /**
     * Get system logs
     * @return string
     */
    public function getSystemLog();
    
    /**
     * Get debug logs
     * @return string
     */
    public function getDebugLog();
}