#!/usr/bin/env php
<?php

declare(strict_types=1);

use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;
use AdamBrett\ShellWrapper\Runners\Exec;
use League\HTMLToMarkdown\HtmlConverter;

// Bootstrap
$appLogChannel = 'upload-mp3s';
$appTitle = 'Upload MP3s';
require __DIR__ . '/bootstrap.php';

// CLI options
$metadataOnly = isset($argv[1]) && $argv[1] == '--metadataOnly';

// Process episode MP3s
foreach ($episodeRecords as $i => $episodeRecord) {
    $episodeId = $episodeRecord[F_EPISODE_ID];
    $logger->info(vsprintf('Processing episode %s', [$episodeId]));

    // Check for existing MP3 URL
    if (isset($episodeRecord[F_MP3_EMBED_URL])) {
        $logger->notice(vsprintf('Episode %s already has an MP3 URL: skipping', [$episodeId]));

        continue;
    }

    // File paths
    $mp3FileName = getFormattedEpisodeName($episodeRecord, 'mp3');
    $mp3TagDir = realpath(__DIR__ . '/mp3s/enc');
    $mp3TagFilePath = $mp3TagDir . '/' . $mp3FileName;

    // URLs
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
        $episodeTags = explode(',', $episodeRecord[F_TAGS]);
        $episodeComment = '';
        foreach ([1, 2, 3] as $showNoteNum) {
            if (!$episodeRecord[F_SHOW_NOTES . $showNoteNum]) {
                continue;
            }
            $episodeComment .= $showNoteNum > 1 ? "\n\n" : '';
            $episodeComment .= $episodeRecord[F_SHOW_NOTES . $showNoteNum];
        }
        // Convert comments to HTML, strip non-paragraphs and then convert back to Markdown
        $markdownParser = new Parsedown();
        $markdownConverter = new HtmlConverter();
        $episodeCommentHtml = $markdownParser->parse($episodeComment);
        //$episodeCommentHtml = strip_tags($episodeCommentHtml, '<p>');
        $episodeCommentHtml = str_replace('href="/', 'href="https://designconversations.net/', $episodeCommentHtml);
        $episodeComment = $markdownConverter->convert($episodeCommentHtml);

        $metadataValues = [
            'mediatype:audio',
            'title:Design Conversations ' . $episodeRecord[F_TITLE],
            'collection:opensource_audio',
            //'collection:test_collection',
            'descripton:' . $episodeCommentHtml,
            'date:' . $episodeRecord[F_DATE],
            'creator:' . $episodeArtist,
            'licenseurl:https://creativecommons.org/licenses/by-nc-nd/4.0/',
            'subject:podcast',
            'subject:design',
            'subject:australia',
        ];
        foreach ($episodeTags as $tag) {
            $metadataValues[] = 'subject:' . trim($tag);
        }

        // Build the command to upload
        $command = new CommandBuilder('/usr/local/bin/ia');
        if ($metadataOnly) {
            $command
                ->addSubCommand('metadata')
                ->addSubCommand($internetArchiveItemId)
                ->addArgument('modify', $metadataValues);
        } else {
            $command
                ->addSubCommand('upload')
                ->addSubCommand($internetArchiveItemId)
                ->addSubCommand($mp3TagFilePath)
                ->addArgument('metadata', $metadataValues)
            ;
        }
        $logger->info($command);

        // Run the command
        $shell = new Exec();
        $shell->run($command);

        $logger->info(vsprintf('File %s uploaded', [$mp3TagFilePath]));
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't upload MP3 file: %s", [$e->getMessage()]));
        exit(1);
    }
}
