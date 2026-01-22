<?php

declare(strict_types=1);

const F_EPISODE_ID = 'episodeId';
const F_SEASON_NUM = 'seasonNum';
const F_STATE = 'state';
const F_DATE = 'date';
const F_GUEST_ID = 'guestId';
const F_TITLE = 'title';
const F_TAGS = 'tags';
const F_SHOW_NOTES = 'showNotes';
const F_MP3_EMBED_URL = 'mp3EmbedUrl';
const F_PHOTO_CREDIT = 'photoCredit';
const F_INCLUDE_IN_PODCAST_FEED = 'includeInPodcastFeed';

const STATE_DRAFT = 'Draft';
const STATE_PUBLISHED = 'Published';

const FORMAT_COMMENTS_OPTION_NONE = 0;
const FORMAT_COMMENTS_OPTION_STRIP_PARAGRAPHS = 1;
const FORMAT_COMMENTS_OPTION_FULLY_QUALIFY_LINKS = 2;

use Armetiz\AirtableSDK\Airtable;
use josegonzalez\Dotenv\Loader as DotenvLoader;
use League\CLImate\CLImate;
use League\CLImate\Logger;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LogLevel;
use wapmorgan\Mp3Info\Mp3Info;

define('APP_DIR', realpath(__DIR__));

// Bootstrap
require __DIR__ . '/vendor/autoload.php';

/**
 * @param string  $logLevel
 * @param CLImate $climate
 * @param string  $appTitle
 *
 * @return Logger
 */
function getLogger(string $logLevel, CLImate $climate, string $appTitle): Logger
{
    $logger = new Logger($logLevel, $climate);
    $logger->log(LogLevel::INFO, $appTitle);

    return $logger;
}

/**
 * @param Logger $logger
 *
 * @return array|void
 */
function getEpisodeRecords(Logger $logger)
{
    $fieldKeys = [
        F_EPISODE_ID,
        F_SEASON_NUM,
        F_STATE,
        F_DATE,
        F_GUEST_ID,
        F_TITLE,
        F_TAGS,
        F_SHOW_NOTES . '1',
        F_SHOW_NOTES . '2',
        F_SHOW_NOTES . '3',
        F_MP3_EMBED_URL,
        F_PHOTO_CREDIT,
        F_INCLUDE_IN_PODCAST_FEED,
    ];

    // Config
    (new DotenvLoader('.env'))
        ->parse()
        ->toEnv();

    // Load the episode data
    try {
        $airtable = new Airtable($_ENV['AIRTABLE_KEY'], $_ENV['AIRTABLE_BASE']);
        $records = $airtable->findRecords($_ENV['AIRTABLE_TABLE'], []);
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't read episode data: %s", [$e->getMessage()]));
        exit(1);
    }

    // Extract episode data
    $episodeRecords = [];
    try {
        foreach ($records as $recordNum => $record) {
            $logger->info(vsprintf('Extracting data from row %s', [$recordNum]));
            $fields = $record->getFields();
            foreach ($fieldKeys as $fieldId) {
                $episodeRecords[$fields['episodeId']][$fieldId] = $fields[$fieldId] ?? null;
            }
        }
        ksort($episodeRecords);
    } catch (Exception $e) {
        $logger->error(vsprintf("Couldn't extract episode data: %s", [$e->getMessage()]));
        exit(1);
    }

    return $episodeRecords;
}

// Output episode data
/**
 * @param array   $episodeRecords
 * @param CLImate $climate
 */
function outputEpisodeData(array $episodeRecords, CLImate $climate): void
{
    $episodeRecordsForTable = [];
    foreach ($episodeRecords as $i => $tmpEpisodeRecord) {
        unset(
            $tmpEpisodeRecord[F_TAGS],
            $tmpEpisodeRecord[F_SHOW_NOTES . '1'],
            $tmpEpisodeRecord[F_SHOW_NOTES . '2'],
            $tmpEpisodeRecord[F_SHOW_NOTES . '3'],
            $tmpEpisodeRecord[F_MP3_EMBED_URL],
            $tmpEpisodeRecord[F_PHOTO_CREDIT],
        );
        $episodeRecordsForTable[$i] = $tmpEpisodeRecord;
    }
    @$climate->table($episodeRecordsForTable);
}

/**
 * Outputs episode titles in various formats for use in filenames, URLs etc.
 *
 * @param array       $episodeRecord
 * @param string|null $fileExtension
 * @param string|null $filePrefix
 *
 * @return string
 */
function getFormattedEpisodeName(array $episodeRecord, string $fileExtension = null, string $filePrefix = null): string
{
    $formattedEpisodeName = '';

    if ($filePrefix) {
        $formattedEpisodeName .= $filePrefix . '-';
    }
    $formattedEpisodeName .= vsprintf(
        '%s-episode-%03d-%s',
        [
            $episodeRecord[F_DATE] ?? 'TBC',
            $episodeRecord[F_EPISODE_ID],
            str_replace('_', '-', $episodeRecord[F_GUEST_ID]),
            $fileExtension,
        ]
    );
    if ($fileExtension) {
        $formattedEpisodeName .= '.' . $fileExtension;
    }

    return $formattedEpisodeName;
}

/**
 * Formats episode title with number, separator and title.
 *
 * @param mixed       $episodeRecord
 * @param string      $separator
 * @param string|null $prefix
 *
 * @return string
 */
function formatEpisodeTitle(mixed $episodeRecord, string $separator, ?string $prefix = null): string
{
    $formattedEpisodeTitle = "{$episodeRecord[F_EPISODE_ID]} $separator {$episodeRecord[F_TITLE]}";
    if ($prefix) {
        $formattedEpisodeTitle = "$prefix $formattedEpisodeTitle";
    }

    return $formattedEpisodeTitle;
}

/**
 * Formats episode notes in a single 'comment'.
 *
 * Used for ID3 tag and Internet Archive metadata content.
 *
 * @param array $episodeRecord
 * @param int   $options
 *
 * @return string
 */
function formatEpisodeNotesAsComment(
    array $episodeRecord,
    int $options = FORMAT_COMMENTS_OPTION_NONE
): string {
    $episodeComment = '';
    foreach ([1, 2, 3] as $showNoteNum) {
        if (!$episodeRecord) {
            continue;
        }
        $episodeComment .= $showNoteNum > 1 ? "\n\n" : '';
        $episodeComment .= $episodeRecord[F_SHOW_NOTES . $showNoteNum];
    }
    // Convert comments to HTML, strip non-paragraphs and then convert back to Markdown
    $markdownParser = new Parsedown();
    $markdownConverter = new HtmlConverter();
    $episodeCommentHtml = $markdownParser->parse($episodeComment);
    if ($options & FORMAT_COMMENTS_OPTION_STRIP_PARAGRAPHS) {
        $episodeCommentHtml = strip_tags($episodeCommentHtml, '<p>');
    }
    if ($options & FORMAT_COMMENTS_OPTION_FULLY_QUALIFY_LINKS) {
        $episodeCommentHtml = str_replace('href="/', 'href="https://designconversations.net/', $episodeCommentHtml);
    }
    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    $episodeComment = $markdownConverter->convert($episodeCommentHtml);

    return $episodeComment;
}

/**
 * Gets the MP3 information for an episode.
 *
 * @param array  $episodeRecord
 * @param string $origOrEnc get the info for the 'orig' or 'enc' MP3; defaults to 'enc'
 *
 * @return Mp3Info
 * @throws Exception
 */
function getEpisodeMp3Info(array $episodeRecord, string $origOrEnc = 'enc'): Mp3Info
{
    $mp3FileName = getFormattedEpisodeName($episodeRecord, 'mp3');
    $realpath = realpath(__DIR__ . '/mp3s/' . $origOrEnc);
    $filename = $realpath . "/" . $mp3FileName;
    return new Mp3Info($filename, true);
}

/**
 * Generates episode artwork by compositing guest photo onto podcast logo.
 *
 * Creates a 1400x1400 image with the podcast logo as base and a circular
 * guest avatar in the bottom-right corner.
 *
 * @param array  $episodeRecord Episode data containing guest ID
 * @param string $guestPhotoPath Absolute path to guest photo
 * @param string $outputPath Absolute path for output image
 * @param int    $avatarSize Size of the circular avatar in pixels
 * @param int    $padding Padding from edge in pixels
 *
 * @return bool True on success, false on failure
 */
function generateEpisodeArtwork(
    array $episodeRecord,
    string $guestPhotoPath,
    string $outputPath,
    int $avatarSize = 150,
    int $padding = 60
): bool {
    $logoPath = realpath(APP_DIR . '/../assets/images/site/site-logo-dc-itunes.png');

    if (!file_exists($logoPath)) {
        return false;
    }

    if (!file_exists($guestPhotoPath)) {
        return false;
    }

    // Ensure output directory exists
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    // Calculate circle centre for the draw command
    $centre = (int) ($avatarSize / 2);
    $edge = $centre;

    // Use two-step process for reliable alpha compositing:
    // Step 1: Create circular avatar as temporary PNG
    $tempAvatarPath = sys_get_temp_dir() . '/episode_avatar_' . uniqid() . '.png';

    $avatarCommand = sprintf(
        '/opt/homebrew/bin/magick %s -resize %dx%d^ -gravity center -extent %dx%d ' .
        '\( +clone -threshold -1 -negate -fill white -draw "circle %d,%d %d,0" \) ' .
        '-alpha off -compose CopyOpacity -composite %s 2>&1',
        escapeshellarg($guestPhotoPath),
        $avatarSize,
        $avatarSize,
        $avatarSize,
        $avatarSize,
        $centre,
        $centre,
        $edge,
        escapeshellarg($tempAvatarPath)
    );

    $output = [];
    $returnCode = 0;
    exec($avatarCommand, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($tempAvatarPath)) {
        return false;
    }

    // Step 2: Composite avatar onto logo
    $compositeCommand = sprintf(
        '/opt/homebrew/bin/magick %s %s -gravity SouthEast -geometry +%d+%d -composite %s 2>&1',
        escapeshellarg($logoPath),
        escapeshellarg($tempAvatarPath),
        $padding,
        $padding,
        escapeshellarg($outputPath)
    );

    exec($compositeCommand, $output, $returnCode);

    // Clean up temp file
    @unlink($tempAvatarPath);

    return $returnCode === 0 && file_exists($outputPath);
}
