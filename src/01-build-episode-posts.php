#!/usr/bin/env php
<?php

declare(strict_types=1);

use League\CLImate\CLImate;
use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Yaml;
use wapmorgan\Mp3Info\Mp3Info;

// Bootstrap
require __DIR__ . '/bootstrap.php';

$appLogChannel = 'build-episode-posts';
$logger = getLogger('debug', new CLImate(), 'Build Episode Posts');
$episodeRecords = getEpisodeRecords($logger);
$forceFlag = hasForceFlag($argv);

// List episode data
outputEpisodeData($episodeRecords, new CLImate());

// Process episode data
foreach ($episodeRecords as $i => $episodeRecord) {
    $episodeId = $episodeRecord[F_EPISODE_ID];

    // Skip episodes already in podcast feed unless --force is used
    if (($episodeRecord[F_INCLUDE_IN_PODCAST_FEED] ?? false) && !$forceFlag) {
        $logger->log(LogLevel::INFO, sprintf('Skipping episode %d, already in podcast feed (use --force to override)', $episodeId));
        continue;
    }

    $logger->log(LogLevel::INFO, vsprintf('Processing episode %s', [$episodeId]));

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

    // Generate episode artwork with guest avatar
    $guestData = Yaml::parseFile(APP_DIR . '/../_data/guests.yml');
    $guestPicture = $guestData[$episodeRecord[F_GUEST_ID]]['picture'] ?? null;
    if ($guestPicture) {
        $guestPhotoPath = realpath(APP_DIR . '/../' . ltrim($guestPicture, '/'));
        $episodeArtworkFilename = sprintf('episode-%03d.jpg', $episodeRecord[F_EPISODE_ID]);
        $episodeArtworkPath = APP_DIR . '/../assets/images/episodes/' . $episodeArtworkFilename;
        $episodeArtworkUrl = '/assets/images/episodes/' . $episodeArtworkFilename;

        if ($guestPhotoPath && generateEpisodeArtwork($episodeRecord, $guestPhotoPath, $episodeArtworkPath)) {
            $logger->log(LogLevel::INFO, vsprintf('Generated episode artwork %s', [$episodeArtworkFilename]));
            $episodeArtworkGenerated = $episodeArtworkUrl;
        } else {
            $logger->log(LogLevel::WARNING, vsprintf('Could not generate episode artwork for episode %s', [$episodeRecord[F_EPISODE_ID]]));
        }
    }

    $frontMatter['categories'] = ['Episodes'];
    $frontMatter['tags'] = $episodeRecord[F_TAGS] ?? [];
    $episodeMp3Info = null;
    try {
        $episodeMp3Info = getEpisodeMp3Info($episodeRecord);
    } catch (Exception $e) {
        $logger->warning(vsprintf("Couldn't extract MP3 data: %s", [$e->getMessage()]));
        //continue;
    }
    $frontMatter['podcast'] = [
        'itunes' => [
            'episodeType' => 'full',
            'episode' => $episodeRecord[F_EPISODE_ID],
            'season' => $episodeRecord[F_SEASON_NUM],
            'title' => $episodeRecord[F_TITLE],
            'duration' => $episodeMp3Info instanceof Mp3Info ? (int) round($episodeMp3Info->duration) : 0,
            'explicit' => 'false',
            'block' => 'false',
        ],
        'length' => $episodeMp3Info instanceof Mp3Info ? (int) $episodeMp3Info->_fileSize : 0,
        'type' => 'audio/mpeg',
        'url' => sprintf(
            "https://archive.org/download/%s/%s",
            getFormattedEpisodeName($episodeRecord, null, 'designconv'),
            getFormattedEpisodeName($episodeRecord, 'mp3')
        ),
        'includeInFeed' => $episodeRecord[F_INCLUDE_IN_PODCAST_FEED] ?? false,
    ];
    if (isset($episodeArtworkGenerated)) {
        $frontMatter['podcast']['itunes']['image'] = $episodeArtworkGenerated;
    }
    $frontMatterStr = "---\n" . Yaml::dump($frontMatter, 3, 2) . "---\n";

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
        $content .= "  <!--suppress HtmlUnknownAttribute, HtmlDeprecatedAttribute -->\n";
        /** @noinspection HtmlUnknownTarget */
        /** @noinspection HtmlUnknownAttribute */
        /** @noinspection HtmlDeprecatedAttribute */
        $content .= sprintf(
            '  <iframe src="%s" class="responsive-embed-item" height="50" frameborder="0" '
                . 'webkitallowfullscreen="true" mozallowfullscreen="true" allowfullscreen></iframe>',
            $episodeRecord[F_MP3_EMBED_URL]
        ) . "\n";
        $content .= '</div>' . "\n";
    } else {
        $content .= "*Audio coming soon.*\n";
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
