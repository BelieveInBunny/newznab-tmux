<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Settings;
use Blacklight\ConsoleTools;
use Blacklight\NZB;

if (! isset($argv[1]) || ! isset($argv[2])) {
    exit("ERROR: You must supply the level you want to reorganize it to, and the source directory  (You would use: 3 .../newznab/resources/nzb/ to move it to 3 levels deep)\n");
}

$nzb = new NZB();
$consoleTools = new ConsoleTools();

$newLevel = $argv[1];
$sourcePath = $argv[2];
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath));

$filestoprocess = [];
$iFilesProcessed = $iFilesCounted = 0;
$time = now()->toImmutable();

echo "\nReorganizing files to Level $newLevel from: $sourcePath This could take a while...\n";
//$consoleTools = new \ConsoleTools();
foreach ($objects as $filestoprocess => $nzbFile) {
    if ($nzbFile->getExtension() != 'gz') {
        continue;
    }

    $newFileName = $nzb->getNZBPath(
        str_replace('.nzb.gz', '', $nzbFile->getBasename()),
        $newLevel,
        true
    );
    if ($newFileName != $nzbFile) {
        rename($nzbFile, $newFileName);
        chmod($newFileName, 0777);
    }
    $iFilesProcessed++;
    if ($iFilesProcessed % 100 == 0) {
        $consoleTools->overWrite("Reorganized $iFilesProcessed");
    }
}

Settings::query()->where(['setting' => 'nzbsplitlevel'])->update(['value' => $argv[1]]);
$consoleTools->overWrite("Processed $iFilesProcessed nzbs in ".$time->diffForHumans()."\n");
