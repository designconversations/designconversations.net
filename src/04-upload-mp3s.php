#!/usr/bin/env php
<?php

declare(strict_types=1);

use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;
use AdamBrett\ShellWrapper\Runners\Exec;
use League\CLImate\CLImate;

// Bootstrap
require __DIR__ . '/bootstrap.php';

$appLogChannel = 'upload-mp3s';
$logger = getLogger('debug', new CLImate(), 'Upload MP3s');
$episodeRecords = getEpisodeRecords($logger);

// CLI options
$metadataOnly = isset($argv[1]) && $argv[1] == '--metadataOnly';

// Process episode MP3s
foreach ($episodeRecords as $i => $episodeRecord) {
    $episodeId = $episodeRecord[F_EPISODE_ID];
    if (!in_array($episodeRecord[F_STATE], [STATE_DRAFT, STATE_PUBLISHED])) {
        $logger->info(sprintf('Skipping episode %d, state is %s', $episodeId, $episodeRecord[F_STATE]));
        continue;
    }
    $logger->info(vsprintf('Processing episode %s', [$episodeId]));

    // Check for existing MP3 URL
    if (isset($episodeRecord[F_MP3_EMBED_URL]) && !$metadataOnly) {
        $logger->notice(vsprintf('Episode %s already has an MP3 URL: skipping', [$episodeId]));

        continue;
    }

    // File paths
    $mp3FileName = getFormattedEpisodeName($episodeRecord, 'mp3');
    $mp3TagDir = realpath(__DIR__ . '/mp3s/enc');
    $mp3TagFilePath = $mp3TagDir . '/' . $mp3FileName;

    // URLs
    /** @noinspection SpellCheckingInspection */
    $internetArchiveItemId = getFormattedEpisodeName($episodeRecord, null, 'designconv');
    $logger->info($internetArchiveItemId);

    // Upload the MP3
    try {
        $logger->info(vsprintf('Uploading %s', [$mp3FileName]));

        // Define additional meta data
        $episodeArtist = 'Geoff Fitzpatrick';
        $episodeAlbum = 'Design Conversations Podcast';
        $episodeGenre = 'Podcast';
        $episodeReleaseYear = date('Y', strtotime($episodeRecord[F_DATE]));
        $episodePublisher = 'DesignConversations.net';
        $episodeImagePathAndType = realpath(__DIR__ . '/mp3s/artwork.jpg') . ':BAND';
        $episodeTags = $episodeRecord[F_TAGS];
        $episodeCommentHtml = formatEpisodeNotesAsComment(
            $episodeRecord,
            FORMAT_COMMENTS_OPTION_FULLY_QUALIFY_LINKS
        );

        /** @noinspection SpellCheckingInspection */
        $metadataValues = [
            'title:' . formatEpisodeTitle($episodeRecord, '|', 'Design Conversations Episode'),
            'collection:opensource_audio',
            //'collection:test_collection',
            'description:' . $episodeCommentHtml,
            'date:' . $episodeRecord[F_DATE],
            'creator:' . $episodeArtist,
            'licenseurl:https://creativecommons.org/licenses/by-nc-nd/4.0/',
        ];
        $subjectVal = 'subject:podcast;design;australia';
        foreach ($episodeTags as $tag) {
            $subjectVal .= ';' . trim($tag);
        }
        $metadataValues[] = $subjectVal;

        // Build the command to upload
        $command = new CommandBuilder('/opt/homebrew/bin/ia');
        if ($metadataOnly) {
            $command
                ->addSubCommand('metadata')
                ->addSubCommand($internetArchiveItemId)
                ->addArgument('modify', $metadataValues);
        } else {
            // Only set mediatype on initial upload as this can't be changed:
            // > Note that some metadata fields (e.g. mediatype) cannot be
            // > modified, and must instead be set initially on upload.
            // See: https://archive.org/services/docs/api/internetarchive/cli.html#modifying-metadata
            $metadataValues[] = 'mediatype:audio';
            $command
                ->addSubCommand('upload')
                ->addSubCommand($internetArchiveItemId)
                ->addSubCommand($mp3TagFilePath)
                ->addArgument('metadata', $metadataValues)
            ;
        }
        $logger->debug($command);

        // Run the command
//        $shell = new Exec();
//        $shell->run($command);

        $logger->info(vsprintf('File %s uploaded', [$mp3TagFilePath]));
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't upload MP3 file: %s", [$e->getMessage()]));
        exit(1);
    }
}
