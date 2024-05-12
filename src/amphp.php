<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\ByteStream;
use Amp\Process\Process;

$command = 'pigz -d -c';

$process = Process::start($command);

$gz_file = 'testMP4.tar.gz';

$target_file_path = 'unzip.tar';

if (file_exists($target_file_path)) {
    unlink($target_file_path);
}

$read_stream  = new ByteStream\ReadableResourceStream(fopen($gz_file, 'r'));
$write_stream = new ByteStream\WritableResourceStream(fopen($target_file_path, 'w'));

Amp\async(fn() => Amp\ByteStream\pipe($read_stream, $process->getStdin()));
Amp\async(fn() => Amp\ByteStream\pipe($process->getStdout(), $write_stream));
Amp\async(fn() => Amp\ByteStream\pipe($process->getStderr(), ByteStream\getStderr()));

$read_stream->onClose(function () use ($process, $write_stream): void {
    echo 'Read Stream ended!' . PHP_EOL;
    $process->getStdin()->close(); // Close child process STDIN so the process exits to prevent it from hanging forever and never exiting
});

$write_stream->onClose(function () use ($process): void {
    echo 'I\'m not triggered! Don\'t know why.' . PHP_EOL;
    echo 'Write Stream ended!' . PHP_EOL;
});

$process->getStdout()->onClose(function () use ($write_stream) {
    echo 'Process STDOUT closed!' . PHP_EOL;
    echo 'I\'m not triggered either! Don\'t know why.' . PHP_EOL;
    $write_stream->close();
});

$exitCode = $process->join();

$process->getStdout()->close(); // Need to close STDOUT manually.
$write_stream->close(); // Need to manually close write steam either, the wrapped file handler will be automatically closed as well.

echo "Process exited with {$exitCode}.\n";
