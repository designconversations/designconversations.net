#!/usr/bin/env php
<?php

declare(strict_types=1);

use Lame\Lame;
use Lame\Settings\Encoding\Preset;
use Lame\Settings\Settings;
use wapmorgan\Mp3Info\Mp3Info;

// Bootstrap
$appLogChannel = 'encode-mp3s';
$appTitle = 'Encode MP3s';
require __DIR__ . '/bootstrap.php';

// Set up encoder with 'radio' preset
$lameEncodingPresetType = 'radio';
$lameEncoding = new Preset();
$lameEncoding->setType($lameEncodingPresetType);
$lameSettings = new Settings($lameEncoding);
$lame = new Lame('/usr/local/bin/lame', $lameSettings);

// Process episode MP3s
foreach ($episodeRecords as $i => $episodeRecord) {
    $episodeId = $episodeRecord[F_EPISODE_ID];
    $logger->info(vsprintf('Processing episode %s', [$episodeId]));

    // File paths
    $mp3FileName = getFormattedEpisodeName($episodeRecord, 'mp3');
    $mp3OrigDir = realpath(__DIR__ . '/mp3s/orig');
    $mp3EncDir = realpath(__DIR__ . '/mp3s/enc');
    $mp3OrigFilePath = $mp3OrigDir . '/' . $mp3FileName;
    $mp3EncFilePath = $mp3EncDir . '/' . $mp3FileName;

    if (file_exists($mp3EncFilePath)) {
        $logger->notice(vsprintf("File %s already encoded", [$mp3FileName]));
        continue;
    }

    // Grab MP3 info for input file
    try {
        $mp3OrigFileInfo = new Mp3Info($mp3OrigFilePath, true);
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't extract MP3 data: %s", [$e->getMessage()]));
        exit(1);
    }

    $logger->debug(json_encode($mp3OrigFileInfo, JSON_UNESCAPED_SLASHES));

    // Re-encode the MP3
    try {
        $logger->info(vsprintf('Encoding %s with %s preset', [$mp3FileName, $lameEncodingPresetType]));
        $lame->encode($mp3OrigFilePath, $mp3EncFilePath);
        $logger->info(vsprintf('File encoded to %s', [$mp3EncFilePath]));
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't encode MP3 file: %s", [$e->getMessage()]));
        exit(1);
    }

    // Grab MP3 info for output file
    try {
        $mp3EncFileInfo = new Mp3Info($mp3EncFilePath, true);
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't extract MP3 data: %s", [$e->getMessage()]));
        exit(1);
    }

    $logger->debug(json_encode($mp3EncFileInfo, JSON_UNESCAPED_SLASHES));
}
