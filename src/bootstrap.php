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

const STATE_DRAFT='Draft';
const STATE_PUBLISHED='Published';

const FORMAT_COMMENTS_OPTION_NONE = 0;
const FORMAT_COMMENTS_OPTION_STRIP_PARAGRAPHS = 1;
const FORMAT_COMMENTS_OPTION_FULLY_QUALIFY_LINKS = 2;

use Armetiz\AirtableSDK\Airtable;
use josegonzalez\Dotenv\Loader as DotenvLoader;
use League\CLImate\CLImate;
use League\CLImate\Logger;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LogLevel;

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
    $logger = new Logger($logLevel ?? LogLevel::INFO, $climate);
    $logger->log(LogLevel::INFO, $appTitle ?? 'Undefined app title');

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
    $climate->table($episodeRecordsForTable);
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
    $formattedEpisodeTitle = "{$episodeRecord[F_EPISODE_ID]} {$separator} {$episodeRecord[F_TITLE]}";
    if ($prefix) {
        $formattedEpisodeTitle = "{$prefix} {$formattedEpisodeTitle}";
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
