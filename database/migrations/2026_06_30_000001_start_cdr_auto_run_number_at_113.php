<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StartCdrAutoRunNumberAt113 extends Migration
{
    private const TABLE = 'controlled_document_requests';
    private const SEQUENCE_TABLE = 'controlled_document_request_number_sequences';
    private const SEQUENCE_KEY = 'cdr';
    private const START_SEQUENCE = 113;
    private const PAD_LENGTH = 3;
    private const UNIQUE_INDEX = 'controlled_document_requests_cdr_no_unique';
    private const INSERT_TRIGGER = 'trg_controlled_document_requests_cdr_no_bi';

    public function up()
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'cdr_no')) {
            return;
        }

        $this->createSequenceTable();

        $lastSequence = $this->backfillExistingCdrNumbers();
        $this->seedSequence($lastSequence);
        $this->ensureUniqueIndex();
        $this->createInsertTrigger();
    }

    public function down()
    {
        $this->dropInsertTrigger();
        $this->dropUniqueIndex();

        if (Schema::hasTable(self::SEQUENCE_TABLE)) {
            Schema::dropIfExists(self::SEQUENCE_TABLE);
        }

        // Existing CDR numbers are not restored because the old values were random.
    }

    private function createSequenceTable(): void
    {
        if (Schema::hasTable(self::SEQUENCE_TABLE)) {
            return;
        }

        Schema::create(self::SEQUENCE_TABLE, function (Blueprint $table) {
            $table->string('document_key', 50)->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    private function backfillExistingCdrNumbers(): int
    {
        $rows = DB::table(self::TABLE)
            ->select('id', 'date', 'created_at', 'updated_at')
            ->get()
            ->sortBy(function ($row) {
                return sprintf(
                    '%012d-%010d',
                    $this->timestampFromRow($row),
                    (int) $row->id
                );
            });

        if ($rows->isEmpty()) {
            return self::START_SEQUENCE - 1;
        }

        $sequence = self::START_SEQUENCE;

        foreach ($rows as $row) {
            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->update([
                    'cdr_no' => $this->formatCdrNumber($this->datePartFromRow($row), $sequence),
                ]);

            $sequence++;
        }

        return $sequence - 1;
    }

    private function seedSequence(int $lastSequence): void
    {
        DB::table(self::SEQUENCE_TABLE)->updateOrInsert(
            ['document_key' => self::SEQUENCE_KEY],
            [
                'last_number' => max($lastSequence, self::START_SEQUENCE - 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function ensureUniqueIndex(): void
    {
        if ($this->indexExists(self::TABLE, self::UNIQUE_INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unique('cdr_no', self::UNIQUE_INDEX);
        });
    }

    private function dropUniqueIndex(): void
    {
        if (!$this->indexExists(self::TABLE, self::UNIQUE_INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_INDEX);
        });
    }

    private function createInsertTrigger(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropInsertTrigger();

        $startSequence = self::START_SEQUENCE;
        $minimumLastSequence = self::START_SEQUENCE - 1;
        $padLength = self::PAD_LENGTH;

        DB::unprepared("
            CREATE TRIGGER `" . self::INSERT_TRIGGER . "`
            BEFORE INSERT ON `" . self::TABLE . "`
            FOR EACH ROW
            BEGIN
                DECLARE next_sequence INT DEFAULT 0;
                DECLARE document_date DATE;

                SET document_date = COALESCE(DATE(NEW.`date`), CURRENT_DATE());

                INSERT INTO `" . self::SEQUENCE_TABLE . "` (`document_key`, `last_number`, `created_at`, `updated_at`)
                VALUES ('" . self::SEQUENCE_KEY . "', {$startSequence}, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    `last_number` = GREATEST(`last_number`, {$minimumLastSequence}) + 1,
                    `updated_at` = NOW();

                SELECT `last_number`
                INTO next_sequence
                FROM `" . self::SEQUENCE_TABLE . "`
                WHERE `document_key` = '" . self::SEQUENCE_KEY . "'
                LIMIT 1;

                SET NEW.`cdr_no` = CONCAT('CDR-', DATE_FORMAT(document_date, '%Y%m%d'), '-', LPAD(next_sequence, {$padLength}, '0'));
            END
        ");
    }

    private function dropInsertTrigger(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::INSERT_TRIGGER . '`');
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::select(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
            [$index]
        );

        return !empty($result);
    }

    private function formatCdrNumber(string $datePart, int $sequence): string
    {
        return 'CDR-' . $datePart . '-' . str_pad((string) $sequence, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    private function datePartFromRow($row): string
    {
        $value = $row->date ?: ($row->created_at ?: $row->updated_at);

        if ($value) {
            try {
                return Carbon::parse($value)->format('Ymd');
            } catch (\Throwable $e) {
                return now()->format('Ymd');
            }
        }

        return now()->format('Ymd');
    }

    private function timestampFromRow($row): int
    {
        $value = $row->date ?: ($row->created_at ?: $row->updated_at);

        if ($value) {
            try {
                return Carbon::parse($value)->timestamp;
            } catch (\Throwable $e) {
                return 0;
            }
        }

        return 0;
    }
}
