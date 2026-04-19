<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

class EpisodeDataCommand
{
    private array $editableFields;

    public function __construct()
    {
        $this->editableFields = [
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
    }

    public function getEditableFields(): array
    {
        return $this->editableFields;
    }

    public function parseArgs(array $argv): array
    {
        $command = $argv[0] ?? 'help';
        $args = array_slice($argv, 1);

        return [
            'command' => $command,
            'args' => $args,
        ];
    }

    public function formatRecordForList(array $fields): array
    {
        return [
            'ID' => $fields[F_EPISODE_ID] ?? '-',
            'Date' => $fields[F_DATE] ?? '-',
            'Guest' => $fields[F_GUEST_ID] ?? '-',
            'Title' => truncate($fields[F_TITLE] ?? '-', 40),
            'State' => $fields[F_STATE] ?? '-',
            'Feed' => ($fields[F_INCLUDE_IN_PODCAST_FEED] ?? false) ? 'Yes' : 'No',
        ];
    }

    public function formatRecordForShow(array $fields): array
    {
        $formatted = [];

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $formatted[$key] = implode(', ', $value);
            } elseif (is_bool($value)) {
                $formatted[$key] = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $formatted[$key] = '(empty)';
            } else {
                $formatted[$key] = (string) $value;
            }
        }

        return $formatted;
    }

    public function getDefaultFieldsForCreate(int $episodeId, ?string $guestId = null): array
    {
        $fields = [
            F_EPISODE_ID => $episodeId,
            F_STATE => STATE_DRAFT,
            F_SEASON_NUM => (int) date('Y'),
        ];

        if ($guestId !== null) {
            $fields[F_GUEST_ID] = $guestId;
        }

        return $fields;
    }

    public function formatRecordsForDump(array $records): array
    {
        return [
            'exported_at' => date('c'),
            'episodes' => $records,
        ];
    }

    public function getDumpFilename(): string
    {
        return 'airtable-dump-' . date('Y-m-d\THis') . '.json';
    }
}
