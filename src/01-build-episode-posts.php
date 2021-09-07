#!/usr/bin/env php
<?php

declare(strict_types=1);

use League\CLImate\CLImate;
use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Yaml;

// Bootstrap
require __DIR__ . '/bootstrap.php';

$appLogChannel = 'build-episode-posts';
$logger = getLogger('debug', new CLImate(), 'Build Episode Posts');
$episodeRecords = getEpisodeRecords($logger);

// List episode data
outputEpisodeData($episodeRecords, new CLImate());

// Process episode data
foreach ($episodeRecords as $i => $episodeRecord) {
    $logger->log(LogLevel::INFO, vsprintf('Processing episode %s', [$episodeRecord[F_EPISODE_ID]]));

    // File path
    switch ($episodeRecord[F_STATE]) {
        case STATE_PUBLISHED:
            $postFolder = '_posts';
            $altPostFolder = '_drafts';
            break;
        case STATE_DRAFT:
            $postFolder = '_drafts';
            $altPostFolder = '_posts';
            break;
        default:
            $logger->log(
                LogLevel::INFO,
                sprintf('Skipping episode %s, status is %s', $episodeRecord[F_EPISODE_ID], $episodeRecord[F_STATE])
            );
            continue 2;
    }
    $postDir = realpath(__DIR__ . '/../' . $postFolder);
    $altPostDir = realpath(__DIR__ . '/../' . $altPostFolder);
    $postFileName = getFormattedEpisodeName($episodeRecord, 'md');
    $postFilePath = $postDir . '/' . $postFileName;
    $altPostFilePath = $altPostDir . '/' . $postFileName;

    // Post front matter
    $frontMatter['layout'] = 'post';
    $separator = "&#124;";
    $frontMatter['title'] = formatEpisodeTitle($episodeRecord, $separator);
    $frontMatter['date'] = $episodeRecord[F_DATE] ?? null;
    $frontMatter['guest'] = $episodeRecord[F_GUEST_ID];
    $frontMatter['categories'] = ['Episodes'];
    $frontMatter['tags'] = $episodeRecord[F_TAGS] ?? [];
    $frontMatterStr = "---\n" . Yaml::dump($frontMatter, 1) . "---\n";

    // Main post content
    $content = '';
    foreach ([1, 2, 3] as $i) {
        $showNotesKey = F_SHOW_NOTES . $i;
        if (isset($episodeRecord[$showNotesKey]) && $episodeRecord[$showNotesKey]) {
            $content .= wordwrap($episodeRecord[$showNotesKey], 80) . "\n\n";
        }
    }
    if (isset($episodeRecord[F_MP3_EMBED_URL]) && $episodeRecord[F_MP3_EMBED_URL]) {
        $content .= "Listen now:\n";
        $content .= '<div class="responsive-embed" style="padding-top: 8%;">' . "\n";
        /** @noinspection HtmlUnknownTarget */
        /** @noinspection HtmlUnknownAttribute */
        /** @noinspection HtmlDeprecatedAttribute */
        $content .= sprintf(
            '  <iframe src="%s" class="responsive-embed-item" height="50" frameborder="0" '
                . 'webkitallowfullscreen="true" mozallowfullscreen="true" allowfullscreen></iframe>',
            $episodeRecord[F_MP3_EMBED_URL]
        ) . "\n";
        $content .= '</div>' . "\n";
    }
    if (isset($episodeRecord[F_PHOTO_CREDIT]) && $episodeRecord[F_PHOTO_CREDIT]) {
        $content .= "\n*Photo credit: " . $episodeRecord[F_PHOTO_CREDIT] . "*\n";
    }

    // Write the post data (also clean up alternate file if exists)
    if (file_exists($altPostFilePath)) {
        $result = unlink($altPostFilePath);
        $logger->log(LogLevel::INFO, vsprintf('Deleted episode post file %s', [$postFilePath]));
    }
    file_put_contents($postFilePath, $frontMatterStr . "\n" . $content);
    $logger->log(LogLevel::INFO, vsprintf('Saved episode post file %s', [$postFilePath]));
}
