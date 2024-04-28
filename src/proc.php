<?php
declare(strict_types=1);

$gz_file = '/Users/kenny/Downloads/Canon.tar.gz';

$gz_stream = fopen($gz_file, 'r');

$descriptions = [
    0 => $gz_stream,
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$start_time = microtime(true);

$proc = proc_open('pigz -d -c', $descriptions, $pipes);

if (!is_resource($proc)) {
    throw new RuntimeException('Failed to open process.');
}

$target_file_path = '/Users/kenny/php/pigz/unzip.tar';

if (file_exists($target_file_path)) {
    unlink($target_file_path);
}

$target_file = fopen($target_file_path, 'a');

$std_in_stream_id  = intval($pipes[1]);
$std_err_stream_id = intval($pipes[2]);
$stream_callbacks  = [$std_in_stream_id => read_std_out(...), $std_err_stream_id => read_std_err(...)];

foreach ([$pipes[1], $pipes[2]] as $stream) {
    stream_set_blocking($stream, false);
}

while (true) {
    $readable_streams = [$pipes[1], $pipes[2]];
    $writable_streams = null;
    $except_streams   = null;

    $count = stream_select($readable_streams, $writable_streams, $except_streams, 0, 200);

    if (false === $count) {
        throw new RuntimeException('Failed to select streams.');
    } elseif ($count > 0) {
        foreach ($readable_streams as $stream) {
            if (feof($stream)) {
                break 2;
            }

            $content = stream_get_contents($stream);

            if (false === $content) {
                throw new RuntimeException('Failed to get stream content.');
            }

            if (isset($stream_callbacks[intval($stream)]) && is_callable($stream_callbacks[intval($stream)])) {
                call_user_func($stream_callbacks[intval($stream)], $content, $target_file);
            }
        }
    } else {
        echo 'Streams are not readable.' . PHP_EOL;
    }
}

fclose($target_file);

proc_close($proc);
echo 'Total execution time in seconds: ' . (microtime(true) - $start_time) . PHP_EOL;

function read_std_err(string $content)
{
    echo $content . PHP_EOL;
}

function read_std_out(string $content, $target_stream)
{
    $write_count = fwrite($target_stream, $content);

    if (false === $write_count) {
        throw new RuntimeException('Failed to write decompressed content to target stream.');
    } elseif ($write_count > 0) {
        echo 'Wrote ' . $write_count . ' bytes to target stream' . PHP_EOL;
    }
}
