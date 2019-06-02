#!/usr/bin/env php
<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Yaml;

// Bootstrap
$appLogChannel = 'build-episode-posts';
$appTitle = 'Build Episode Posts';
require __DIR__ . '/bootstrap.php';

// Process episode data
foreach ($episodeRecords as $i => $episodeRecord) {
    $logger->log(LogLevel::INFO, vsprintf('Processing episode %s', [$episodeRecord[F_EPISODE_ID]]));

    // File path
    $postDir = realpath(__DIR__ . '/../' . ($episodeRecord[F_IS_DRAFT] ? '_drafts' : '_posts'));
    $altPostDir = realpath(__DIR__ . '/../' . ($episodeRecord[F_IS_DRAFT] ? '_posts' : '_drafts'));
    $postFileName = getFormattedEpisodeName($episodeRecord, 'md');
    $postFilePath = $postDir . '/' . $postFileName;
    $altPostFilePath = $altPostDir . '/' . $postFileName;

    // Post front matter
    $frontMatter['layout'] = 'post';
    $frontMatter['title'] = $episodeRecord[F_TITLE];
    $frontMatter['date'] = $episodeRecord[F_DATE];
    $frontMatter['guest'] = $episodeRecord[F_GUEST_ID];
    $frontMatter['categories'] = ['Episodes'];
    $frontMatter['tags'] = explode(', ', $episodeRecord[F_TAGS]);
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
        /** @noinspection HtmlUnknownTarget */
        /** @noinspection HtmlDeprecatedAttribute */
        $content .= '<div class="responsive-embed" style="padding-top: 8%;">' . "\n";
        $content .= vsprintf(
                '  <iframe src="%s" class="responsive-embed-item" height="50" frameborder="0" '
                . 'webkitallowfullscreen="true" mozallowfullscreen="true" allowfullscreen></iframe>',
                [$episodeRecord[F_MP3_EMBED_URL]]
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
