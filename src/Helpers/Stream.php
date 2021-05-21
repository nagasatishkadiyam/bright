<?php

namespace Diviky\Bright\Helpers;

use Illuminate\Support\Collection;
use Iterator;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Stream
{
    /**
     * @var array
     */
    protected $mime_types = [
        '.txt'  => 'text/plain',
        '.json' => 'application/json',
        '.xml'  => 'application/xml',
        '.doc'  => 'application/msword',
        '.rtf'  => 'application/rtf',
        '.xls'  => 'application/vnd.ms-excel',
        '.xlsx' => 'application/vnd.ms-excel',
        '.csv'  => 'application/vnd.ms-excel',
        '.ppt'  => 'application/vnd.ms-powerpoint',
        '.pdf'  => 'application/pdf',
    ];

    /**
     * CSV file separator.
     *
     * @var string
     */
    protected $separator = ',';

    /**
     * Line ending.
     *
     * @var string
     */
    protected $lineEnd   = "\r\n";

    /**
     * File streamr.
     *
     * @var mixed
     */
    protected $stream;

    /**
     * Start the stream.
     *
     * @param string $filename
     * @param bool   $write
     *
     * @return $this
     */
    public function start($filename, $write = false): self
    {
        if ($write) {
            return $this->write($filename);
        }

        $ext  = \strtolower(\strrchr($filename, '.'));
        $type = $this->mime_types[$ext];

        if ('.csv' == $ext) {
            $this->separator = ',';
        }

        \set_time_limit(0);
        \header('Content-Type: application/octet-stream');
        \header('Content-Description: File Transfer');
        \header('Content-Type: ' . $type);
        \header('Content-Disposition: attachment;filename="' . $filename . '"');

        $seconds = 30;
        \header('Expires: ' . \gmdate('D, d M Y H:i:s', \time() + $seconds) . ' GMT');
        \header('Cache-Control: max-age=' . $seconds . ', s-maxage=' . $seconds . ', must-revalidate, proxy-revalidate');

        \session_cache_limiter(); // Disable session_start() caching headers
        if (\session_id()) {
            // Remove Pragma: no-cache generated by session_start()
            if (\function_exists('header_remove')) {
                \header_remove('Pragma');
            } else {
                \header('Pragma:');
            }
        }

        echo "\xEF\xBB\xBF";

        return $this;
    }

    /**
     * Set the headers file.
     *
     * @param array|Collection|Iterator $fields
     */
    public function setHeader($fields): self
    {
        $fields = $this->toArray($fields);
        $fields = \array_keys($fields);

        $out = [];
        foreach ($fields as $field) {
            $field = \strtoupper($field);
            if (false !== \strpos($field, ' AS ')) {
                $field = \explode(' AS ', $field);
                $field = \trim($field[1]);
            }
            $out[] = \ucwords($field);
        }

        $this->implode($out);

        return $this;
    }

    /**
     * Set the seperator.
     *
     * @param string $string
     */
    public function setSeparator($string): self
    {
        $this->separator = $string;

        return $this;
    }

    /**
     * Export as excel.
     *
     * @param array|Collection|Iterator $rows
     * @param array|Collection|Iterator $headers
     */
    public function excel($rows, $headers): void
    {
        $this->setHeader($headers);
        $this->flushRows($rows);
    }

    /**
     * Out put the file.
     *
     * @param array|Collection|Iterator $rows
     * @param array                     $fields
     */
    public function output($rows, $fields = []): self
    {
        $rows = $this->toArray($rows);

        if (empty($fields)) {
            $fields = (array) $rows[0];
        }

        $this->setHeader($fields);

        foreach ($rows as $row) {
            $this->flush($row, $fields);
        }

        return $this->stopFile();
    }

    /**
     * Write the details.
     *
     * @param array|object $row
     *
     * @SuppressWarnings(PHPMD)
     */
    public function flush($row, array $fields): self
    {
        if (!is_array($row)) {
            $row = (array) $row;
        }

        $out = [];
        foreach ($fields as $k => $v) {
            $out[] = $this->clean($row[$k]);
        }

        $this->implode($out);

        return $this;
    }

    /**
     * Write multiple rows to file.
     *
     * @param array|Collection|Iterator $rows
     */
    public function flushRows($rows): self
    {
        $rows = $this->toArray($rows);

        foreach ($rows as $row) {
            $this->implode($row, true);
        }

        return $this->stopFile();
    }

    /**
     * Clean the values.
     *
     * @param string $string
     */
    public function clean($string): string
    {
        $string = '"' . \str_replace('"', '""', $string) . '"';

        return \str_replace(["\n", "\t", "\r"], '', $string);
    }

    /**
     * Function to read local and remote file.
     *
     * @param string $filename
     */
    public function readFile($filename): bool
    {
        $chunksize = 2 * (1024 * 1024); // how many bytes per chunk
        $buffer    = '';

        $stream = \fopen($filename, 'rb');

        if (false === $stream) {
            return false;
        }

        while (!\feof($stream)) {
            $buffer = \fread($stream, $chunksize);
            echo $buffer;
            \ob_flush();
            \flush();
        }

        return \fclose($stream);
    }

    /**
     * Close the file writing stream.
     *
     * @param string $filepath
     */
    public function write($filepath): self
    {
        $ext = \strtolower(\strrchr($filepath, '.'));

        if ('.csv' == $ext) {
            $this->separator = ',';
        }

        $this->stream = \fopen($filepath, 'w');

        \set_time_limit(0);

        return $this;
    }

    /**
     * Write content to file.
     */
    public function writeFile(string $content): self
    {
        if ($this->stream) {
            \fwrite($this->stream, $content);
        }

        return $this;
    }

    /**
     * Close the file writing stream.
     */
    public function stopFile(): self
    {
        if (is_resource($this->stream)) {
            \fclose($this->stream);
        }

        return $this;
    }

    /**
     * @param array|object $row
     */
    protected function implode($row = [], bool $clean = false): self
    {
        if (\is_object($row)) {
            $row = (array) $row;
        }

        if ($clean) {
            $row = \array_map([$this, 'clean'], $row);
        }

        if ($this->stream) {
            return $this->writeFile(\implode($this->separator, $row) . $this->lineEnd);
        }

        echo \implode($this->separator, $row) . $this->lineEnd;

        \flush();
        \ob_flush();

        return $this;
    }

    /**
     * Convert rows to array.
     *
     * @param array|Collection|Iterator $rows
     */
    protected function toArray($rows): array
    {
        if ($rows instanceof Collection) {
            $rows = $rows->toArray();
            $rows = \json_decode(\json_encode($rows), true);
        }

        if ($rows instanceof Iterator) {
            $rows->rewind();
            $rows = \iterator_to_array($rows);
        }

        return $rows;
    }
}
