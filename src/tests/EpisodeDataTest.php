<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../EpisodeDataCommand.php';

class EpisodeDataTest extends TestCase
{
    public function testParseFieldValueReturnsBooleanForIncludeInPodcastFeed(): void
    {
        $this->assertTrue(parseFieldValue(F_INCLUDE_IN_PODCAST_FEED, 'true'));
        $this->assertTrue(parseFieldValue(F_INCLUDE_IN_PODCAST_FEED, '1'));
        $this->assertTrue(parseFieldValue(F_INCLUDE_IN_PODCAST_FEED, 'yes'));
        $this->assertFalse(parseFieldValue(F_INCLUDE_IN_PODCAST_FEED, 'false'));
        $this->assertFalse(parseFieldValue(F_INCLUDE_IN_PODCAST_FEED, '0'));
        $this->assertFalse(parseFieldValue(F_INCLUDE_IN_PODCAST_FEED, 'no'));
    }

    public function testParseFieldValueReturnsIntegerForEpisodeId(): void
    {
        $this->assertSame(19, parseFieldValue(F_EPISODE_ID, '19'));
        $this->assertSame(1, parseFieldValue(F_EPISODE_ID, '1'));
    }

    public function testParseFieldValueReturnsIntegerForSeasonNum(): void
    {
        $this->assertSame(2026, parseFieldValue(F_SEASON_NUM, '2026'));
    }

    public function testParseFieldValueReturnsArrayForTags(): void
    {
        $result = parseFieldValue(F_TAGS, 'graphic-design,illustration,poster-art');
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(['graphic-design', 'illustration', 'poster-art'], $result);
    }

    public function testParseFieldValueTrimsTagWhitespace(): void
    {
        $result = parseFieldValue(F_TAGS, 'graphic-design , illustration , poster-art');
        $this->assertSame(['graphic-design', 'illustration', 'poster-art'], $result);
    }

    public function testParseFieldValueReturnsStringForTitle(): void
    {
        $result = parseFieldValue(F_TITLE, 'Mimmo Cozzolino: Poster art');
        $this->assertSame('Mimmo Cozzolino: Poster art', $result);
    }

    public function testParseFieldValueReturnsStringForState(): void
    {
        $this->assertSame('Draft', parseFieldValue(F_STATE, 'Draft'));
        $this->assertSame('Published', parseFieldValue(F_STATE, 'Published'));
    }

    public function testTruncateReturnsStringUnchangedIfShorterThanLimit(): void
    {
        $this->assertSame('Short string', truncate('Short string', 40));
    }

    public function testTruncateReturnsStringUnchangedIfExactlyAtLimit(): void
    {
        $this->assertSame('1234567890', truncate('1234567890', 10));
    }

    public function testTruncateShortenStringWithEllipsis(): void
    {
        $result = truncate('This is a very long string that needs truncation', 20);
        $this->assertSame('This is a very lo...', $result);
        $this->assertSame(20, strlen($result));
    }

    public function testEpisodeDataCommandReturnsAvailableFields(): void
    {
        $command = new EpisodeDataCommand();
        $fields = $command->getEditableFields();

        $this->assertContains(F_EPISODE_ID, $fields);
        $this->assertContains(F_TITLE, $fields);
        $this->assertContains(F_STATE, $fields);
        $this->assertContains(F_TAGS, $fields);
        $this->assertContains(F_GUEST_ID, $fields);
    }

    public function testEpisodeDataCommandParsesArguments(): void
    {
        $command = new EpisodeDataCommand();

        // Test 'show' command
        $parsed = $command->parseArgs(['show', '19']);
        $this->assertSame('show', $parsed['command']);
        $this->assertSame(['19'], $parsed['args']);

        // Test 'update' command
        $parsed = $command->parseArgs(['update', '19', 'title', 'New Title']);
        $this->assertSame('update', $parsed['command']);
        $this->assertSame(['19', 'title', 'New Title'], $parsed['args']);
    }

    public function testEpisodeDataCommandDefaultsToHelp(): void
    {
        $command = new EpisodeDataCommand();

        $parsed = $command->parseArgs([]);
        $this->assertSame('help', $parsed['command']);
    }

    public function testFormatRecordForListReturnsFormattedRow(): void
    {
        $command = new EpisodeDataCommand();

        $fields = [
            F_EPISODE_ID => 19,
            F_DATE => '2026-02-03',
            F_GUEST_ID => 'mimmo_cozzolino',
            F_TITLE => 'Mimmo Cozzolino: Poster art and graphic design',
            F_STATE => 'Draft',
            F_INCLUDE_IN_PODCAST_FEED => false,
        ];

        $row = $command->formatRecordForList($fields);

        $this->assertSame(19, $row['ID']);
        $this->assertSame('2026-02-03', $row['Date']);
        $this->assertSame('mimmo_cozzolino', $row['Guest']);
        $this->assertSame('Draft', $row['State']);
        $this->assertSame('No', $row['Feed']);
        // Title should be truncated to 40 chars
        $this->assertLessThanOrEqual(40, strlen($row['Title']));
    }

    public function testFormatRecordForShowReturnsAllFields(): void
    {
        $command = new EpisodeDataCommand();

        $fields = [
            F_EPISODE_ID => 19,
            F_TITLE => 'Test Title',
            F_TAGS => ['design', 'poster'],
            F_INCLUDE_IN_PODCAST_FEED => true,
        ];

        $formatted = $command->formatRecordForShow($fields);

        $this->assertSame('19', $formatted[F_EPISODE_ID]);
        $this->assertSame('Test Title', $formatted[F_TITLE]);
        $this->assertSame('design, poster', $formatted[F_TAGS]);
        $this->assertSame('true', $formatted[F_INCLUDE_IN_PODCAST_FEED]);
    }

    public function testFormatRecordForShowHandlesEmptyValues(): void
    {
        $command = new EpisodeDataCommand();

        $fields = [
            F_EPISODE_ID => 19,
            F_TITLE => null,
        ];

        $formatted = $command->formatRecordForShow($fields);

        $this->assertSame('(empty)', $formatted[F_TITLE]);
    }

    public function testGetDefaultFieldsForCreate(): void
    {
        $command = new EpisodeDataCommand();

        $defaults = $command->getDefaultFieldsForCreate(19);

        $this->assertSame(19, $defaults[F_EPISODE_ID]);
        $this->assertSame(STATE_DRAFT, $defaults[F_STATE]);
        $this->assertSame((int) date('Y'), $defaults[F_SEASON_NUM]);
    }

    public function testGetDefaultFieldsForCreateWithGuestId(): void
    {
        $command = new EpisodeDataCommand();

        $defaults = $command->getDefaultFieldsForCreate(19, 'mimmo_cozzolino');

        $this->assertSame(19, $defaults[F_EPISODE_ID]);
        $this->assertSame('mimmo_cozzolino', $defaults[F_GUEST_ID]);
    }

    public function testFormatRecordsForDumpReturnsJsonEncodableArray(): void
    {
        $command = new EpisodeDataCommand();

        $records = [
            [
                F_EPISODE_ID => 1,
                F_TITLE => 'Test Episode',
                F_TAGS => ['design', 'art'],
                F_INCLUDE_IN_PODCAST_FEED => true,
            ],
            [
                F_EPISODE_ID => 2,
                F_TITLE => 'Another Episode',
                F_TAGS => ['illustration'],
                F_INCLUDE_IN_PODCAST_FEED => false,
            ],
        ];

        $dump = $command->formatRecordsForDump($records);

        $this->assertIsArray($dump);
        $this->assertArrayHasKey('exported_at', $dump);
        $this->assertArrayHasKey('episodes', $dump);
        $this->assertCount(2, $dump['episodes']);

        // Verify JSON encoding works
        $json = json_encode($dump, JSON_PRETTY_PRINT);
        $this->assertNotFalse($json);
    }

    public function testGetDumpFilenameIncludesTimestamp(): void
    {
        $command = new EpisodeDataCommand();

        $filename = $command->getDumpFilename();

        $this->assertMatchesRegularExpression('/^airtable-dump-\d{4}-\d{2}-\d{2}T\d{6}\.json$/', $filename);
    }
}
