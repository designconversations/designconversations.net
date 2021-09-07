#!/usr/bin/env php
<?php

declare(strict_types=1);

use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;
use AdamBrett\ShellWrapper\Runners\Exec;
use League\CLImate\CLImate;
use wapmorgan\Mp3Info\Mp3Info;

// Bootstrap
require __DIR__ . '/bootstrap.php';

$appLogChannel = 'prepare-mp3s';
$logger = getLogger('debug', new CLImate(), 'Prepare MP3s');
$episodeRecords = getEpisodeRecords($logger);

// List episode data
outputEpisodeData($episodeRecords, new CLImate());

// Process episode MP3s
foreach ($episodeRecords as $i => $episodeRecord) {
    $episodeId = $episodeRecord[F_EPISODE_ID];
    if (!in_array($episodeRecord[F_STATE], [STATE_DRAFT, STATE_PUBLISHED])) {
        $logger->info(sprintf('Skipping episode %d, state is %s', $episodeId, $episodeRecord[F_STATE]));
        continue;
    }
    $logger->info(vsprintf('Processing episode %s', [$episodeId]));

    // File paths
    $mp3FileName = getFormattedEpisodeName($episodeRecord, 'mp3');
    $mp3EncDir = realpath(__DIR__ . '/mp3s/enc');
    $mp3TagDir = realpath(__DIR__ . '/mp3s/tag');
    $mp3EncFilePath = $mp3EncDir . '/' . $mp3FileName;
    $mp3TagFilePath = $mp3TagDir . '/' . $mp3FileName;

    // Grab MP3 info for input file
    try {
        $mp3EncFileInfo = new Mp3Info($mp3EncFilePath, true);
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't extract MP3 data: %s", [$e->getMessage()]));
        continue;
        //exit(1);
    }

    $logger->debug(json_encode($mp3EncFileInfo, JSON_UNESCAPED_SLASHES));

    // Tag the MP3
    try {
        $logger->info(vsprintf('Tagging %s', [$mp3FileName]));

        // Define additional tag data
        $episodeArtist = 'Geoff Fitzpatrick';
        $episodeAlbum = 'Design Conversations Podcast';
        $episodeGenre = 'Podcast';
        $episodeReleaseYear = $episodeRecord[F_DATE] ? date('Y', strtotime($episodeRecord[F_DATE])) : null;
        $episodePublisher = 'DesignConversations.net';
        $episodeImagePathAndType = realpath(__DIR__ . '/mp3s/artwork.jpg') . ':BAND';
        $episodeComment = formatEpisodeNotesAsComment($episodeRecord, FORMAT_COMMENTS_OPTION_STRIP_PARAGRAPHS);

        // Build the command to tag
        $command = new CommandBuilder('/opt/homebrew/bin/eyeD3');
        $command
            ->addArgument('title', formatEpisodeTitle($episodeRecord, '|', 'Design Conversations Episode'))
            ->addArgument('artist', $episodeArtist)
            ->addArgument('album', $episodeAlbum)
            ->addArgument('album-artist', $episodeArtist)
            ->addArgument('track', $episodeRecord[F_EPISODE_ID])
            ->addArgument('disc-num', $episodeRecord[F_SEASON_NUM])
            ->addArgument('genre', $episodeGenre)
            ->addArgument('recording-date', $episodeRecord[F_DATE])
            ->addArgument('release-year', $episodeReleaseYear)
            ->addArgument('publisher', $episodePublisher)
            ->addArgument('add-image', $episodeImagePathAndType)
            ->addArgument('comment', $episodeComment)
            ->addParam($mp3EncFilePath);
        $logger->info($command);

        // Run the command
        $shell = new Exec();
        $shell->run($command);

        $logger->info(vsprintf('File %s tagged', [$mp3EncFilePath]));
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't tag MP3 file: %s", [$e->getMessage()]));
        continue;
        //exit(1);
    }

    // Grab MP3 info for output file
    try {
        $mp3TagFileInfo = new Mp3Info($mp3EncFilePath, true);
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't extract MP3 data: %s", [$e->getMessage()]));
        continue;
        //exit(1);
    }

    $logger->debug(json_encode($mp3TagFileInfo, JSON_UNESCAPED_SLASHES));
}
