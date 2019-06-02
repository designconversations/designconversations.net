<?php

declare(strict_types=1);

const F_EPISODE_ID = 'episodeId';
const F_SEASON_NUM = 'seasonNum';
const F_IS_DRAFT = 'isDraft';
const F_DATE = 'date';
const F_GUEST_ID = 'guestId';
const F_TITLE = 'title';
const F_TAGS = 'tags';
const F_SHOW_NOTES = 'showNotes';
const F_MP3_EMBED_URL = 'mp3EmbedUrl';
const F_PHOTO_CREDIT = 'photoCredit';

use League\CLImate\CLImate;
use League\CLImate\Logger;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Psr\Log\LogLevel;

define('APP_DIR', realpath(__DIR__));

// Bootstrap
require __DIR__ . '/vendor/autoload.php';
$climate = new CLImate();
$logger = new Logger($logLevel ?? LogLevel::INFO, $climate);
$logger->log(LogLevel::INFO, $appTitle);

// Config
$episodeDataFile = 'episode-data.ods';
$episodeSheetName = 'episodes-pivoted';

// Load the workbook and worksheet
try {
    $episodeDataFilePath = __DIR__ . '/' . $episodeDataFile;
    $logger->info(vsprintf('Loading episode data from %s', [$episodeDataFilePath]));
    $spreadsheet = IOFactory::load($episodeDataFilePath);
} catch (Exception $e) {
    $logger->error(vsprintf("Couldn't read episode data: %s", [$e->getMessage()]));
    exit(1);
}
$episodeSheet = $spreadsheet->getSheetByName($episodeSheetName);

// Extract episode data
$episodeRecords = [];
$colKeys = [];

try {
    foreach ($episodeSheet->getRowIterator() as $rowNum => $row) {
        $logger->info(vsprintf('Extracting data from row %s', [$rowNum]));
        $cellIterator = $row->getCellIterator();
        foreach ($cellIterator as $cellNum => $cell) {
            if ($rowNum == 1) {
                $colKeys[$cellNum] = $cell->getValue();
            } else {
                switch ($colKeys[$cellNum]) {
                    case F_EPISODE_ID:
                        $value = (int) $cell->getValue();

                        break;
                    case F_IS_DRAFT:
                        $value = (bool) $cell->getValue();

                        break;
                    case F_DATE:
                        $value = Date::excelToDateTimeObject($cell->getValue())->format('Y-m-d');

                        break;
                    default:
                        $value = $cell->getValue();
                }
                $episodeRecords[$rowNum - 1][$colKeys[$cellNum]] = $value;
            }
        }
    }
} catch (Exception $e) {
    $logger->error(vsprintf("Couldn't extract episode data: %s", [$e->getMessage()]));
    exit(1);
}

// Output episode data
$episodeRecordsForTable = [];
foreach ($episodeRecords as $i => $tmpEpisodeRecord) {
    unset(
        $tmpEpisodeRecord[F_TAGS],
        $tmpEpisodeRecord[F_SHOW_NOTES . '1'],
        $tmpEpisodeRecord[F_SHOW_NOTES . '2'],
        $tmpEpisodeRecord[F_SHOW_NOTES . '3'],
        $tmpEpisodeRecord[F_MP3_EMBED_URL],
        $tmpEpisodeRecord[F_PHOTO_CREDIT],
        $tmpEpisodeRecord['tmp'],
        $tmpEpisodeRecord['tmp2']
    );
    $episodeRecordsForTable[$i] = $tmpEpisodeRecord;
}
$climate->table($episodeRecordsForTable);

/**
 * Outputs episode titles in various formats for use in filenames, URLs etc.
 *
 * @param array       $episodeRecord
 * @param string|null $fileExtension
 * @param string|null $filePrefix
 *
 * @return string
 */
function getFormattedEpisodeName($episodeRecord, $fileExtension = null, $filePrefix = null): string
{
    $formattedEpisodeName = '';

    if ($filePrefix) {
        $formattedEpisodeName .= $filePrefix . '-';
    }
    $formattedEpisodeName .= vsprintf(
        '%s-episode-%03d-%s',
        [
            $episodeRecord[F_DATE],
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
