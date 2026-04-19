#!/usr/bin/env php
<?php

declare(strict_types=1);

use Armetiz\AirtableSDK\Airtable;
use josegonzalez\Dotenv\Loader as DotenvLoader;
use League\CLImate\CLImate;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/EpisodeDataCommand.php';

$climate = new CLImate();
$command = new EpisodeDataCommand();

// Load environment
(new DotenvLoader(APP_DIR . '/.env'))
    ->parse()
    ->toEnv();

$airtable = new Airtable($_ENV['AIRTABLE_KEY'], $_ENV['AIRTABLE_BASE']);
$table = $_ENV['AIRTABLE_TABLE'];

// Parse command line
$parsed = $command->parseArgs(array_slice($argv, 1));
$cmd = $parsed['command'];
$args = $parsed['args'];

switch ($cmd) {
    case 'list':
        listEpisodes($airtable, $table, $climate, $command);
        break;

    case 'show':
        if (empty($args[0])) {
            $climate->error('Usage: 00-episode-data.php show <episodeId>');
            exit(1);
        }
        showEpisode($airtable, $table, $climate, $command, (int) $args[0]);
        break;

    case 'create':
        if (empty($args[0])) {
            $climate->error('Usage: 00-episode-data.php create <episodeId> [guestId]');
            exit(1);
        }
        createEpisode($airtable, $table, $climate, $command, (int) $args[0], $args[1] ?? null);
        break;

    case 'update':
    case 'set':
        if (count($args) < 3) {
            $climate->error('Usage: 00-episode-data.php update <episodeId> <field> <value>');
            $climate->out('');
            $climate->out('Editable fields: ' . implode(', ', $command->getEditableFields()));
            exit(1);
        }
        updateEpisode($airtable, $table, $climate, $command, (int) $args[0], $args[1], $args[2]);
        break;

    case 'delete':
        if (empty($args[0])) {
            $climate->error('Usage: 00-episode-data.php delete <episodeId>');
            exit(1);
        }
        deleteEpisode($airtable, $table, $climate, (int) $args[0]);
        break;

    case 'dump':
        dumpEpisodes($airtable, $table, $climate, $command, $args[0] ?? null);
        break;

    case 'fields':
        $climate->out('<bold>Editable fields:</bold>');
        foreach ($command->getEditableFields() as $field) {
            $climate->out("  - $field");
        }
        break;

    case 'help':
    default:
        showHelp($climate);
        break;
}

function showHelp(CLImate $climate): void
{
    $climate->out('<bold>Episode Data CLI</bold>');
    $climate->out('');
    $climate->out('<bold>Usage:</bold>');
    $climate->out('  00-episode-data.php <command> [arguments]');
    $climate->out('');
    $climate->out('<bold>Commands:</bold>');
    $climate->out('  list                              List all episodes');
    $climate->out('  show <episodeId>                  Show details for an episode');
    $climate->out('  create <episodeId> [guestId]      Create a new episode record');
    $climate->out('  update <episodeId> <field> <val>  Update a field value');
    $climate->out('  delete <episodeId>                Delete an episode record');
    $climate->out('  dump [filename]                   Export all episodes to JSON');
    $climate->out('  fields                            List editable fields');
    $climate->out('  help                              Show this help message');
    $climate->out('');
    $climate->out('<bold>Examples:</bold>');
    $climate->out('  00-episode-data.php list');
    $climate->out('  00-episode-data.php show 19');
    $climate->out('  00-episode-data.php create 19 mimmo_cozzolino');
    $climate->out('  00-episode-data.php update 19 title "Mimmo Cozzolino: Poster art"');
    $climate->out('  00-episode-data.php update 19 state Draft');
    $climate->out('  00-episode-data.php update 19 tags "graphic-design,illustration"');
    $climate->out('  00-episode-data.php dump');
    $climate->out('  00-episode-data.php dump backup.json');
}

function listEpisodes(Airtable $airtable, string $table, CLImate $climate, EpisodeDataCommand $command): void
{
    $climate->out('<bold>Episode List</bold>');
    $climate->out('');

    try {
        $records = $airtable->findRecords($table, []);
        $rows = [];

        foreach ($records as $record) {
            $rows[] = $command->formatRecordForList($record->getFields());
        }

        // Sort by episode ID
        usort($rows, fn($a, $b) => ($a['ID'] ?? 0) <=> ($b['ID'] ?? 0));

        $climate->table($rows);
    } catch (Exception $e) {
        $climate->error('Failed to list episodes: ' . $e->getMessage());
        exit(1);
    }
}

function showEpisode(Airtable $airtable, string $table, CLImate $climate, EpisodeDataCommand $command, int $episodeId): void
{
    try {
        $record = $airtable->findRecord($table, [F_EPISODE_ID => $episodeId]);

        if (!$record) {
            $climate->error("Episode $episodeId not found");
            exit(1);
        }

        $fields = $record->getFields();
        $formatted = $command->formatRecordForShow($fields);

        $climate->out("<bold>Episode $episodeId</bold>");
        $climate->out('');

        foreach ($formatted as $key => $value) {
            $climate->out(sprintf('  <bold>%s:</bold> %s', $key, $value));
        }
    } catch (Exception $e) {
        $climate->error('Failed to show episode: ' . $e->getMessage());
        exit(1);
    }
}

function createEpisode(Airtable $airtable, string $table, CLImate $climate, EpisodeDataCommand $command, int $episodeId, ?string $guestId): void
{
    // Check if episode already exists
    try {
        $existing = $airtable->findRecord($table, [F_EPISODE_ID => $episodeId]);
        if ($existing) {
            $climate->error("Episode $episodeId already exists");
            exit(1);
        }
    } catch (Exception $e) {
        // Record not found is expected
    }

    $fields = $command->getDefaultFieldsForCreate($episodeId, $guestId);

    try {
        $airtable->createRecord($table, $fields);
        $climate->info("Created episode $episodeId");

        // Show the created record
        showEpisode($airtable, $table, $climate, $command, $episodeId);
    } catch (Exception $e) {
        $climate->error('Failed to create episode: ' . $e->getMessage());
        exit(1);
    }
}

function updateEpisode(Airtable $airtable, string $table, CLImate $climate, EpisodeDataCommand $command, int $episodeId, string $field, string $value): void
{
    $parsedValue = parseFieldValue($field, $value);

    try {
        $airtable->updateRecord($table, [F_EPISODE_ID => $episodeId], [$field => $parsedValue]);
        $climate->info("Updated episode $episodeId: $field = $value");

        // Show the updated record
        showEpisode($airtable, $table, $climate, $command, $episodeId);
    } catch (Exception $e) {
        $climate->error('Failed to update episode: ' . $e->getMessage());
        exit(1);
    }
}

function deleteEpisode(Airtable $airtable, string $table, CLImate $climate, int $episodeId): void
{
    try {
        $record = $airtable->findRecord($table, [F_EPISODE_ID => $episodeId]);
        if (!$record) {
            $climate->error("Episode $episodeId not found");
            exit(1);
        }

        $fields = $record->getFields();
        $climate->out("<bold>About to delete:</bold>");
        $climate->out("  Episode $episodeId: " . ($fields[F_TITLE] ?? '(no title)'));
        $climate->out('');

        $input = $climate->confirm('Are you sure you want to delete this episode?');
        if (!$input->confirmed()) {
            $climate->out('Cancelled');
            exit(0);
        }

        $airtable->deleteRecord($table, [F_EPISODE_ID => $episodeId]);
        $climate->info("Deleted episode $episodeId");
    } catch (Exception $e) {
        $climate->error('Failed to delete episode: ' . $e->getMessage());
        exit(1);
    }
}

function dumpEpisodes(Airtable $airtable, string $table, CLImate $climate, EpisodeDataCommand $command, ?string $filename): void
{
    try {
        $records = $airtable->findRecords($table, []);
        $episodes = [];

        foreach ($records as $record) {
            $episodes[] = $record->getFields();
        }

        // Sort by episode ID
        usort($episodes, fn($a, $b) => ($a[F_EPISODE_ID] ?? 0) <=> ($b[F_EPISODE_ID] ?? 0));

        $dump = $command->formatRecordsForDump($episodes);
        $json = json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Determine output file
        $outputFile = $filename ?? $command->getDumpFilename();
        $outputPath = APP_DIR . '/dumps/' . $outputFile;

        file_put_contents($outputPath, $json);

        $climate->info("Exported " . count($episodes) . " episodes to $outputFile");
    } catch (Exception $e) {
        $climate->error('Failed to dump episodes: ' . $e->getMessage());
        exit(1);
    }
}
